<?php

namespace EICC\StaticForge\Core;

use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Discovers content files in configured directories
 * Filters by registered extensions and stores file paths in container
 */
class FileDiscovery
{
    private Container $container;
    private Log $logger;
    private ExtensionRegistry $extensionRegistry;

    public function __construct(Container $container, ExtensionRegistry $extensionRegistry)
    {
        $this->container = $container;
        $this->logger = $container->getVariable('logger');
        $this->extensionRegistry = $extensionRegistry;
    }

    /**
     * Discover all processable files and store in container
     */
    public function discoverFiles(): void
    {
        $directories = $this->getDirectoriesToScan();
        $discoveredFiles = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                $this->logger->log('WARNING', "Directory not found: {$directory}");
                continue;
            }

            $this->scanDirectory($directory, $discoveredFiles);
        }

        // Store discovered files in container
        $this->container->setVariable('discovered_files', $discoveredFiles);

        $this->logger->log('INFO', "Discovered " . count($discoveredFiles) . " processable files");
    }

    /**
     * Get list of directories to scan from container
     *
     * @return array<string>
     */
    protected function getDirectoriesToScan(): array
    {
        $directories = $this->container->getVariable('SCAN_DIRECTORIES');

        if ($directories === null) {
            // Default to SOURCE_DIR if SCAN_DIRECTORIES not configured
            $sourceDir = $this->container->getVariable('SOURCE_DIR') ?? 'content';
            return [$sourceDir];
        }

        return is_array($directories) ? $directories : [$directories];
    }

    /**
     * Recursively scan directory for processable files
     *
     * @param array<string> &$files
     */
    protected function scanDirectory(string $directory, array &$files): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $this->extensionRegistry->canProcess($file->getPathname())) {
                $files[] = $file->getPathname();
            }
        }
    }
}
