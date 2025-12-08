<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Categories\Services;

use EICC\Utils\Container;
use EICC\Utils\Log;

class CategoriesService
{
    private Log $logger;

    public function __construct(Log $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Scan discovered files for category templates and apply them to files in those categories
     */
    public function processCategoryTemplates(Container $container): void
    {
        try {
            $discoveredFiles = $container->getVariable('discovered_files') ?? [];
            $this->logger->log('INFO', 'Categories: Found ' . count($discoveredFiles) . ' discovered files');
        } catch (\Exception $e) {
            $this->logger->log('ERROR', 'Categories: Failed to get discovered_files: ' . $e->getMessage());
            return;
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
                $categorySlug = $this->sanitizeCategoryName($metadata['category']);

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
        try {
            if ($container->hasVariable('category_templates')) {
                $container->updateVariable('category_templates', $categoryTemplates);
                $this->logger->log('DEBUG', 'Updated existing category templates in container');
            } else {
                $container->setVariable('category_templates', $categoryTemplates);
            }
        } catch (\Exception $e) {
            $this->logger->log('ERROR', 'Failed to update category templates: ' . $e->getMessage());
        }
    }

    /**
     * Modify output path to include category subdirectory
     */
    public function categorizeOutputPath(string $outputPath, string $category): string
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
        
        $this->logger->log('INFO', "Categorizing file: {$outputPath} -> {$newPath}");

        return $newPath;
    }

    /**
     * Sanitize category name for use in filesystem paths
     */
    public function sanitizeCategoryName(string $category): string
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
