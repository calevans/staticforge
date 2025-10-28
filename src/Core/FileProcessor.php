<?php

namespace EICC\StaticForge\Core;

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
    private array $processedOutputPaths = [];

    public function __construct(Container $container, EventManager $eventManager)
    {
        $this->container = $container;
        $this->logger = $container->getVariable('logger');
        $this->eventManager = $eventManager;
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

        $this->logger->log('INFO', "Processing " . count($files) . " files");

        // Reset processed output paths for this run
        $this->processedOutputPaths = [];

        foreach ($files as $filePath) {
            $this->processFile($filePath);
        }

        $this->logger->log('INFO', 'File processing complete');
    }

    /**
     * Process a single file through the render pipeline
     */
    protected function processFile(string $filePath): void
    {
        $this->logger->log('DEBUG', "Processing file: {$filePath}");

        // Check for output path conflicts before processing
        $expectedOutputPath = $this->calculateOutputPath($filePath);
        
        if ($expectedOutputPath && $this->hasOutputConflict($expectedOutputPath, $filePath)) {
            return; // Skip this file due to conflict
        }

        // Initialize render context
        $renderContext = [
            'file_path' => $filePath,
            'rendered_content' => null,
            'metadata' => [],
            'output_path' => null,
            'skip_file' => false
        ];

        try {
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

        } catch (\Exception $e) {
            $this->logger->log('ERROR', "Failed to process file {$filePath}: " . $e->getMessage());
        }
    }

    /**
     * Calculate the expected output path for a given input file
     */
    private function calculateOutputPath(string $inputPath): ?string
    {
        $sourceDir = $this->container->getVariable('SOURCE_DIR') ?? 'content';
        $outputDir = $this->container->getVariable('OUTPUT_DIR') ?? 'public';

        // Remove source directory from path
        $relativePath = str_replace($sourceDir . '/', '', $inputPath);
        
        // Convert known extensions to .html
        $extension = pathinfo($inputPath, PATHINFO_EXTENSION);
        if (in_array($extension, ['md', 'html'])) {
            $relativePath = preg_replace('/\.' . preg_quote($extension) . '$/', '.html', $relativePath);
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
            
            $this->logger->log('WARNING', 
                "Output path conflict detected! Both '{$conflictingFile}' and '{$currentInputPath}' " .
                "would generate '{$expectedOutputPath}'. Skipping '{$currentInputPath}' to prevent overwrite."
            );
            
            return true;
        }
        
        // Reserve this output path for the current file
        $this->processedOutputPaths[$expectedOutputPath] = $currentInputPath;
        return false;
    }
}