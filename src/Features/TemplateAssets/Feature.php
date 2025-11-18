<?php

namespace EICC\StaticForge\Features\TemplateAssets;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'TemplateAssets';
    protected Log $logger;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_LOOP' => ['method' => 'handlePostLoop', 'priority' => 100]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);
        $this->logger = $container->get('logger');
        $this->logger->log('INFO', 'TemplateAssets Feature registered');
    }

    /**
     * Handle POST_LOOP event
     * Copies assets from template directory to output directory
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostLoop(Container $container, array $parameters): array
    {
        $templateDir = $container->getVariable('TEMPLATE_DIR') ?? 'templates';
        $templateName = $container->getVariable('TEMPLATE') ?? 'sample';
        $outputDir = $container->getVariable('OUTPUT_DIR') ?? 'public';

        $sourceAssetsDir = $templateDir . DIRECTORY_SEPARATOR . $templateName . DIRECTORY_SEPARATOR . 'assets';
        $targetAssetsDir = $outputDir . DIRECTORY_SEPARATOR . 'assets';

        if (!is_dir($sourceAssetsDir)) {
            $this->logger->log('DEBUG', "No assets directory found at: {$sourceAssetsDir}");
            return $parameters;
        }

        $this->logger->log('INFO', "Copying assets from {$sourceAssetsDir} to {$targetAssetsDir}");

        if (!$this->copyDirectory($sourceAssetsDir, $targetAssetsDir)) {
            $this->logger->log('ERROR', "Failed to copy assets");
        }

        return $parameters;
    }

    /**
     * Recursively copy a directory
     */
    private function copyDirectory(string $source, string $dest): bool
    {
        if (!is_dir($dest)) {
            if (!mkdir($dest, 0755, true) && !is_dir($dest)) {
                return false;
            }
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $subPath = $iterator->getSubPathName();
            $targetPath = $dest . DIRECTORY_SEPARATOR . $subPath;

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    if (!mkdir($targetPath, 0755, true) && !is_dir($targetPath)) {
                        return false;
                    }
                }
            } else {
                if (!copy($item->getPathname(), $targetPath)) {
                    return false;
                }
            }
        }

        return true;
    }
}
