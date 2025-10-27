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

            // POST-RENDER event
            $renderContext = $this->eventManager->fire('POST_RENDER', $renderContext);

        } catch (\Exception $e) {
            $this->logger->log('ERROR', "Failed to process file {$filePath}: " . $e->getMessage());
        }
    }
}