<?php

namespace EICC\StaticForge\Features\Categories;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Categories Feature - organizes content into category subdirectories
 * Listens to POST_RENDER to modify output paths based on category metadata
 */
class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'Categories';
    protected Log $logger;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
    'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 250],
    'POST_RENDER' => ['method' => 'handlePostRender', 'priority' => 100]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);

      // Get logger from container
        $this->logger = $container->get('logger');

        $this->logger->log('INFO', 'Categories Feature registered');
    }

    /**
     * Handle POST_GLOB event to scan category files and store their templates
     *
     * Called dynamically by EventManager when POST_GLOB event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostGlob(Container $container, array $parameters): array
    {
        try {
            $discoveredFiles = $container->getVariable('discovered_files') ?? [];
            $this->logger->log('INFO', 'Categories: Found ' . count($discoveredFiles) . ' discovered files');
        } catch (\Exception $e) {
            $this->logger->log('ERROR', 'Categories: Failed to get discovered_files: ' . $e->getMessage());
            return $parameters;
        }

        $categoryTemplates = [];

        // First pass: collect category templates
        foreach ($discoveredFiles as $fileData) {
            $metadata = $fileData['metadata'];

            if (isset($metadata['type']) && $metadata['type'] === 'category') {
                $categorySlug = pathinfo($fileData['path'], PATHINFO_FILENAME);

                // If this category file has a template, store it
                if (isset($metadata['template'])) {
                    $categoryTemplates[$categorySlug] = $metadata['template'];
                    $this->logger->log(
                        'INFO',
                        "Category '{$categorySlug}' uses template: {$metadata['template']}"
                    );
                }
            }
        }

        $this->logger->log('INFO', 'Found ' . count($categoryTemplates) . ' category templates');

        // Second pass: apply category templates to files that belong to categories
        $updatedFiles = [];
        $filesUpdated = 0;
        foreach ($discoveredFiles as $fileData) {
            $metadata = $fileData['metadata'];

            // Skip category definition files themselves
            if (isset($metadata['type']) && $metadata['type'] === 'category') {
                $updatedFiles[] = $fileData;
                continue;
            }

            // If file has a category and that category has a template, apply it
            if (isset($metadata['category'])) {
                $categorySlug = $this->slugifyCategory($metadata['category']);

                if (isset($categoryTemplates[$categorySlug])) {
                    // Only apply if no template already set in frontmatter
                    if (!isset($metadata['template']) || $metadata['template'] === 'base') {
                        $metadata['template'] = $categoryTemplates[$categorySlug];
                        $fileData['metadata'] = $metadata;
                        $filesUpdated++;
                        $this->logger->log(
                            'INFO',
                            "Applied category template '{$categoryTemplates[$categorySlug]}' to {$fileData['path']}"
                        );
                    }
                }
            }

            $updatedFiles[] = $fileData;
        }

        $this->logger->log('INFO', "Applied category templates to $filesUpdated files");

        // Update discovered_files with modified metadata
        $container->updateVariable('discovered_files', $updatedFiles);

        // Store category templates in container for renderers to use
        // Use updateVariable if it already exists (POST_GLOB can fire multiple times)
        try {
            $container->setVariable('category_templates', $categoryTemplates);
        } catch (\Exception $e) {
            // Variable already exists - update it instead
            $container->updateVariable('category_templates', $categoryTemplates);
            $this->logger->log('DEBUG', 'Updated existing category templates in container');
        }

        $this->logger->log('INFO', 'Found ' . count($categoryTemplates) . ' category templates');

        return $parameters;
    }

    /**
     * Convert category name to URL-safe slug
     */
    private function slugifyCategory(string $category): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($category)));
    }



    /**
     * Handle POST_RENDER event to modify output path based on category
     *
     * Called dynamically by EventManager when POST_RENDER event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
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
   * Avoids double-nesting when category matches existing directory
   */
    private function addCategoryToPath(string $outputPath, string $category): string
    {
      // Get directory and filename
        $dirName = dirname($outputPath);
        $fileName = basename($outputPath);

      // Sanitize category name (remove special characters, lowercase)
        $sanitizedCategory = $this->sanitizeCategoryName($category);

        // Check if the output path already ends with the category directory
        // This prevents double-nesting like public/docs/docs/
        $currentDirName = basename($dirName);
        if ($currentDirName === $sanitizedCategory) {
            // Category directory already exists in path, don't add it again
            return $outputPath;
        }

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
