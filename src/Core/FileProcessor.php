<?php

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
            'skip_file' => false
        ];

        // PRE-RENDER event
        $renderContext = $this->eventManager->fire('PRE_RENDER', $renderContext);

        if ($renderContext['skip_file'] ?? false) {
            $this->logger->log('INFO', "Skipping file: {$filePath}");
            return;
        }

        // RENDER event
        $renderContext = $this->eventManager->fire('RENDER', $renderContext);

        // Track the actual output path after processing
        if (isset($renderContext['output_path']) && $renderContext['output_path']) {
            $this->processedOutputPaths[$renderContext['output_path']] = $filePath;
        }

        // POST-RENDER event
        $renderContext = $this->eventManager->fire('POST_RENDER', $renderContext);

        // Write file to disk after POST-RENDER (Core responsibility)
        if (isset($renderContext['rendered_content']) && isset($renderContext['output_path'])) {
            $this->writeOutputFile($renderContext['output_path'], $renderContext['rendered_content']);
        }
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

        $bytesWritten = file_put_contents($outputPath, $content);

        if ($bytesWritten === false) {
            throw new FileProcessingException(
                "Failed to write output file: {$outputPath}",
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
