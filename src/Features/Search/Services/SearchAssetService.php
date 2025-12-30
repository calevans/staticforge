<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Search\Services;

use EICC\Utils\Container;
use EICC\Utils\Log;

class SearchAssetService
{
    private Log $logger;

    public function __construct(Log $logger)
    {
        $this->logger = $logger;
    }

    public function copyAssets(Container $container): void
    {
        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            $this->logger->log('ERROR', 'OUTPUT_DIR not set, cannot copy search assets');
            return;
        }

        $sourceDir = dirname(__DIR__) . '/assets/js';
        $destDir = $outputDir . '/assets/js';

        if (!is_dir($destDir)) {
            if (!mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                $this->logger->log('ERROR', "Failed to create directory: {$destDir}");
                return;
            }
        }

        // Copy search.js (wrapper)
        $config = $container->getVariable('site_config');
        $searchEngine = $config['search']['engine'] ?? 'minisearch';

        if ($searchEngine === 'fuse') {
            // Copy fuse.basic.min.js
            $this->copyFile($sourceDir . '/fuse.basic.min.js', $destDir . '/fuse.basic.min.js');
            // Copy search-fuse.js as search.js
            $this->copyFile($sourceDir . '/search-fuse.js', $destDir . '/search.js');
            $this->logger->log('INFO', 'Using Fuse.js for search');
        } else {
            // Copy minisearch.min.js
            $this->copyFile($sourceDir . '/minisearch.min.js', $destDir . '/minisearch.min.js');
            // Copy search.js (wrapper)
            $this->copyFile($sourceDir . '/search.js', $destDir . '/search.js');
            $this->logger->log('INFO', 'Using MiniSearch for search');
        }
    }

    private function copyFile(string $source, string $dest): void
    {
        if (file_exists($source)) {
            if (copy($source, $dest)) {
                $this->logger->log('INFO', "Copied search asset: " . basename($source));
            } else {
                $this->logger->log('ERROR', "Failed to copy search asset: " . basename($source));
            }
        } else {
            $this->logger->log('WARNING', "Search asset not found: " . basename($source));
        }
    }
}
