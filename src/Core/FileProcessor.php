<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core;

use EICC\StaticForge\Exceptions\FileProcessingException;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * The main processing loop that handles individual files
 * Fires PRE-RENDER, RENDER, POST-RENDER events for each file
 */
class FileProcessor
{
    private Container $container;
    private Log $logger;
    private EventManager $eventManager;
    private ErrorHandler $errorHandler;

    /**
     * Track processed output paths to detect duplicates
     * Maps output path to input file path
     * @var array<string, string>
     */
    private array $processedOutputPaths = [];

    public function __construct(Container $container, EventManager $eventManager)
    {
        $this->container = $container;
        $this->logger = $container->get('logger');
        $this->eventManager = $eventManager;
        $this->errorHandler = $container->get(ErrorHandler::class);
    }

    /**
     * Whether incremental builds are enabled (opt-in via --incremental flag).
     *
     * Read lazily at point of use rather than cached at construction time, because
     * FileProcessor is instantiated eagerly in bootstrap.php, before the CLI command
     * has had a chance to set INCREMENTAL_BUILD on the container (mirrors how
     * FileDiscovery reads SHOW_DRAFTS).
     */
    private function isIncrementalEnabled(): bool
    {
        $incrementalEnabled = $this->container->getVariable('INCREMENTAL_BUILD') ?? false;
        if (is_string($incrementalEnabled)) {
            $incrementalEnabled = filter_var($incrementalEnabled, FILTER_VALIDATE_BOOLEAN);
        }
        return (bool) $incrementalEnabled;
    }

    /**
     * Process all discovered files through the render pipeline
     */
    public function processFiles(): void
    {
        $files = $this->container->getVariable('discovered_files') ?? [];

        if (empty($files)) {
            $this->logger->log('INFO', 'No files to process');
            return;
        }

        // Ensure critical configuration exists before processing loop
        if (!$this->container->getVariable('OUTPUT_DIR')) {
            throw new \RuntimeException('OUTPUT_DIR not set in container');
        }
        if (!$this->container->getVariable('SOURCE_DIR')) {
            throw new \RuntimeException('SOURCE_DIR not set in container');
        }

        $this->logger->log('INFO', "Processing " . count($files) . " files", [
            'file_count' => count($files),
        ]);

        // Reset processed output paths for this run
        $this->processedOutputPaths = [];

        $successCount = 0;
        $failCount = 0;

        foreach ($files as $fileData) {
            $filePath = $fileData['path'];
            try {
                $this->processFile($fileData);
                $this->errorHandler->recordFileSuccess($filePath);
                $successCount++;
            } catch (\Exception $e) {
                $this->errorHandler->handleFileError($e, $filePath, 'process');
                $failCount++;
                // Continue processing other files
            }
        }

        $this->logger->log('INFO', 'File processing complete', [
            'total' => count($files),
            'success' => $successCount,
            'failed' => $failCount,
        ]);
    }

    /**
     * Process a single file through the render pipeline
     *
     * @param array{path: string, url: string, metadata: array<string, mixed>} $fileData File data from discovery
     */
    protected function processFile(array $fileData): void
    {
        $filePath = $fileData['path'];

        $this->logger->log('DEBUG', "Processing file: {$filePath}", [
            'file' => $filePath,
            'size' => file_exists($filePath) ? filesize($filePath) : 0,
        ]);

        // Check for output path conflicts before processing
        $expectedOutputPath = $this->calculateOutputPath($filePath);

        if ($expectedOutputPath && $this->hasOutputConflict($expectedOutputPath, $filePath)) {
            throw new FileProcessingException(
                "Output path conflict for {$expectedOutputPath}",
                $filePath,
                'conflict_check'
            );
        }

        // Initialize render context with pre-parsed metadata
        $renderContext = [
            'file_path' => $filePath,
            'file_url' => $fileData['url'],
            'file_metadata' => $fileData['metadata'],
            'rendered_content' => null,
            'metadata' => $fileData['metadata'], // Legacy for backwards compatibility
            'output_path' => null,
            'skip_file' => false,
            'cache_hit' => false,
        ];

        // PRE-RENDER event
        $renderContext = $this->eventManager->fire('PRE_RENDER', $renderContext);

        if ($renderContext['skip_file'] ?? false) {
            $this->logger->log('INFO', "Skipping file: {$filePath}");
            return;
        }

        // Some features (e.g. Categories) rewrite output_path at POST_RENDER, after the
        // renderer has already overwritten it once at RENDER. Such features may instead
        // predict that final path during PRE_RENDER and publish it as
        // 'expected_output_path', so the cache check below compares against the file that
        // will actually exist on disk rather than the un-rewritten path.
        $cacheCheckPath = $renderContext['expected_output_path'] ?? $expectedOutputPath;

        if ($this->isIncrementalEnabled() && $this->canReuseCachedOutput($filePath, $cacheCheckPath)) {
            $renderContext = $this->substituteCachedRender($renderContext, $cacheCheckPath);
        } else {
            // RENDER event
            $renderContext = $this->eventManager->fire('RENDER', $renderContext);
        }

        // If rendering failed (e.g. missing template), output_path might be null
        // We should not proceed to POST_RENDER or write if rendering failed
        if (!isset($renderContext['rendered_content']) || !isset($renderContext['output_path'])) {
            throw new FileProcessingException(
                "Rendering failed or produced no output",
                $filePath,
                'render'
            );
        }

        // Track the actual output path after processing
        if (isset($renderContext['output_path']) && $renderContext['output_path']) {
            $this->processedOutputPaths[$renderContext['output_path']] = $filePath;
        }

        // POST-RENDER event (always fires, cache hit or not - this is the safety invariant
        // that keeps Sitemap/RssFeed/CategoryIndex/Search aggregate output correct)
        $renderContext = $this->eventManager->fire('POST_RENDER', $renderContext);

        // Write file to disk after POST-RENDER (Core responsibility).
        // Skip the write on a cache hit - the output file on disk is already correct.
        if (
            !($renderContext['cache_hit'] ?? false)
            && isset($renderContext['rendered_content'])
            && isset($renderContext['output_path'])
        ) {
            $this->writeOutputFile($renderContext['output_path'], $renderContext['rendered_content']);
        }
    }

    /**
     * Determine whether a previously-written output file can be reused instead of
     * re-running the RENDER event for this source file.
     *
     * Mirrors the established mtime-comparison idiom used elsewhere in the codebase
     * (e.g. CategoryIndex\Services\ImageService, ResponsiveImages\Services\ImageVariantGenerator).
     */
    private function canReuseCachedOutput(string $sourcePath, string $outputPath): bool
    {
        if (!is_file($outputPath)) {
            return false;
        }

        $sourceMtime = filemtime($sourcePath);
        $outputMtime = filemtime($outputPath);

        if ($sourceMtime === false || $outputMtime === false) {
            // Fail safe -> full render
            return false;
        }

        return $outputMtime >= $sourceMtime;
    }

    /**
     * Substitute the RENDER step with the previously-written output file's contents,
     * read back from disk. Falls back to a full render if the cached file is unreadable.
     *
     * @param array<string, mixed> $renderContext
     * @return array<string, mixed>
     */
    private function substituteCachedRender(array $renderContext, string $outputPath): array
    {
        $cachedHtml = file_get_contents($outputPath);

        if ($cachedHtml === false) {
            // Fail safe: if we can't read it back, do a full render instead.
            return $this->eventManager->fire('RENDER', $renderContext);
        }

        $renderContext['rendered_content'] = $cachedHtml;
        $renderContext['output_path'] = $outputPath;
        $renderContext['cache_hit'] = true;

        return $renderContext;
    }

    /**
     * Calculate the expected output path for a given input file
     */
    private function calculateOutputPath(string $filePath): string
    {
        $sourceDir = $this->container->getVariable('SOURCE_DIR');
        if (!$sourceDir) {
            throw new \RuntimeException('SOURCE_DIR not set in container');
        }
        $outputDir = $this->container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set in container');
        }

        // Remove source directory from path
        $relativePath = str_replace($sourceDir . DIRECTORY_SEPARATOR, '', $filePath);

        // Convert known extensions to .html
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        if (in_array($extension, ['md', 'html'])) {
            $relativePath = preg_replace('/\.' . preg_quote($extension, '/') . '$/', '.html', $relativePath);
        }

        return $outputDir . '/' . $relativePath;
    }

    /**
     * Check if the expected output path conflicts with already processed files
     */
    private function hasOutputConflict(string $expectedOutputPath, string $currentInputPath): bool
    {
        if (isset($this->processedOutputPaths[$expectedOutputPath])) {
            $conflictingFile = $this->processedOutputPaths[$expectedOutputPath];

            $this->logger->log(
                'WARNING',
                "Output path conflict detected! Both '{$conflictingFile}' and '{$currentInputPath}' " .
                "would generate '{$expectedOutputPath}'. Skipping '{$currentInputPath}' to prevent overwrite."
            );

            return true;
        }

                // Reserve this output path for the current file
        $this->processedOutputPaths[$expectedOutputPath] = $currentInputPath;
        return false;
    }

    /**
     * Write rendered content to output file
     */
    private function writeOutputFile(string $outputPath, string $content): void
    {
        // Ensure output directory exists
        $outputDirPath = dirname($outputPath);
        if (!is_dir($outputDirPath)) {
            if (!mkdir($outputDirPath, 0755, true) && !is_dir($outputDirPath)) {
                throw new FileProcessingException(
                    "Failed to create output directory: {$outputDirPath}",
                    $outputPath,
                    'write'
                );
            }
        }

        // Write to a temp file first, then atomically rename into place. This protects
        // against partially-written output files if the process is killed mid-write,
        // which matters for incremental builds: a truncated file with a fresh mtime
        // would otherwise be wrongly treated as cacheable on the next build.
        $tempPath = $outputPath . '.tmp';

        $bytesWritten = file_put_contents($tempPath, $content);

        if ($bytesWritten === false) {
            throw new FileProcessingException(
                "Failed to write output file: {$outputPath}",
                $outputPath,
                'write'
            );
        }

        if (!rename($tempPath, $outputPath)) {
            throw new FileProcessingException(
                "Failed to finalize output file: {$outputPath}",
                $outputPath,
                'write'
            );
        }

        $this->logger->log('INFO', "Written {$bytesWritten} bytes to {$outputPath}", [
            'output' => $outputPath,
            'size' => $bytesWritten,
        ]);
    }
}
