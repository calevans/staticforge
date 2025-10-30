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
        $this->logger = $container->getVariable('logger');
        $this->eventManager = $eventManager;
        $this->errorHandler = $container->get('error_handler');
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

        $this->logger->log('INFO', "Processing " . count($files) . " files", [
            'file_count' => count($files),
        ]);

        // Reset processed output paths for this run
        $this->processedOutputPaths = [];

        $successCount = 0;
        $failCount = 0;

        foreach ($files as $filePath) {
            try {
                $this->processFile($filePath);
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
     */
    protected function processFile(string $filePath): void
    {
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

        // Initialize render context
        $renderContext = [
            'file_path' => $filePath,
            'rendered_content' => null,
            'metadata' => [],
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
    private function calculateOutputPath(string $inputPath): string
    {
        $sourceDir = $this->container->getVariable('SOURCE_DIR') ?? 'content';
        $outputDir = $this->container->getVariable('OUTPUT_DIR') ?? 'public';

        // Remove source directory from path
        $relativePath = str_replace($sourceDir . '/', '', $inputPath);

        // Convert known extensions to .html
        $extension = pathinfo($inputPath, PATHINFO_EXTENSION);
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
