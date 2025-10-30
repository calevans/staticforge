<?php

namespace EICC\StaticForge\Features\Categories;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;

/**
 * Categories Feature - organizes content into category subdirectories
 * Listens to POST_RENDER to modify output paths based on category metadata
 */
class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'Categories';
    protected $logger;

    protected array $eventListeners = [
    'POST_RENDER' => ['method' => 'handlePostRender', 'priority' => 100]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);

      // Get logger from container
        $this->logger = $container->getVariable('logger');

        $this->logger->log('INFO', 'Categories Feature registered');
    }

  /**
   * Handle POST_RENDER event to modify output path based on category
   */
    public function handlePostRender(Container $container, array $parameters): array
    {
      // Only process if there's metadata with a category
        $metadata = $parameters['metadata'] ?? [];
        $category = $metadata['category'] ?? null;

        if (!$category) {
            return $parameters;
        }

      // Get current output path
        $outputPath = $parameters['output_path'] ?? null;

        if (!$outputPath) {
            return $parameters;
        }

      // Modify output path to include category subdirectory
        $newOutputPath = $this->addCategoryToPath($outputPath, $category);

        $this->logger->log('INFO', "Categorizing file: {$outputPath} -> {$newOutputPath}");

      // Update the output path in parameters
        $parameters['output_path'] = $newOutputPath;

        return $parameters;
    }

  /**
   * Add category subdirectory to the output path
   */
    private function addCategoryToPath(string $outputPath, string $category): string
    {
      // Get directory and filename
        $dirName = dirname($outputPath);
        $fileName = basename($outputPath);

      // Sanitize category name (remove special characters, lowercase)
        $sanitizedCategory = $this->sanitizeCategoryName($category);

      // Build new path: output_dir/category/filename
        $newPath = $dirName . DIRECTORY_SEPARATOR . $sanitizedCategory . DIRECTORY_SEPARATOR . $fileName;

        return $newPath;
    }

  /**
   * Sanitize category name for use in filesystem paths
   */
    private function sanitizeCategoryName(string $category): string
    {
      // Convert to lowercase
        $sanitized = strtolower($category);

      // Replace spaces and special characters with hyphens
        $sanitized = preg_replace('/[^a-z0-9]+/', '-', $sanitized);

      // Remove leading/trailing hyphens
        $sanitized = trim($sanitized, '-');

        return $sanitized;
    }
}
