<?php

namespace EICC\StaticForge\Features\CategoryIndex;

use EICC\Utils\Container;
use EICC\Utils\Log;

class CategoryManager
{
    private Log $logger;
    private ImageProcessor $imageProcessor;

    /**
     * Files organized by category
     * @var array<string, array{display_name?: string, files: array<int, array<string, mixed>>}>
     */
    private array $categoryFiles = [];

    /**
     * Stores metadata from category definition files
     * @var array<string, array<string, mixed>>
     */
    private array $categoryMetadata = [];

    public function __construct(Log $logger, ImageProcessor $imageProcessor)
    {
        $this->logger = $logger;
        $this->imageProcessor = $imageProcessor;
    }

    /**
     * Scan discovered files for category files (type = category in frontmatter)
     */
    public function scanCategoryFiles(Container $container): void
    {
        $discoveredFiles = $container->getVariable('discovered_files') ?? [];

        foreach ($discoveredFiles as $fileData) {
            $metadata = $fileData['metadata'];

            if (isset($metadata['type']) && $metadata['type'] === 'category') {
                $categorySlug = pathinfo($fileData['path'], PATHINFO_FILENAME);
                $this->categoryMetadata[$categorySlug] = $metadata;

                $this->logger->log('INFO', "Found category file: {$fileData['path']}");
            }
        }

        $this->logger->log('INFO', 'Found ' . count($this->categoryMetadata) . ' category files');
    }

    public function getCategoryMetadata(): array
    {
        return $this->categoryMetadata;
    }

    public function getCategoryFiles(string $categorySlug): array
    {
        return $this->categoryFiles[$categorySlug] ?? ['files' => []];
    }

    /**
     * Collect files that have categories
     */
    public function collectCategoryFile(Container $container, array $parameters): void
    {
        $metadata = $parameters['metadata'] ?? [];
        $category = $metadata['category'] ?? null;

        if ($category) {
            $outputPath = $parameters['output_path'] ?? null;
            $filePath = $parameters['file_path'] ?? null;
            $renderedContent = $parameters['rendered_content'] ?? '';
            $title = $metadata['title'] ?? 'Untitled';

            if ($outputPath && $filePath && $renderedContent) {
                // Sanitize category name to match filesystem
                $sanitizedCategory = $this->sanitizeCategoryName($category);

                if (!isset($this->categoryFiles[$sanitizedCategory])) {
                    $this->categoryFiles[$sanitizedCategory] = [
                        'display_name' => $category,
                        'files' => []
                    ];
                }

                // Extract hero image from rendered content (not from disk - file doesn't exist yet)
                $imageUrl = $this->imageProcessor->extractHeroImageFromHtml($renderedContent, $filePath, $container);
                $date = $this->getFileDate($metadata, $filePath);

                $this->categoryFiles[$sanitizedCategory]['files'][] = [
                    'title' => $title,
                    'url' => '/' . $sanitizedCategory . '/' . basename($outputPath),
                    'image' => $imageUrl,
                    'date' => $date,
                    'metadata' => $metadata
                ];
            }
        }
    }

    /**
     * Sanitize category name for use in filesystem paths
     */
    public function sanitizeCategoryName(string $category): string
    {
        $sanitized = strtolower($category);
        $sanitized = preg_replace('/[^a-z0-9]+/', '-', $sanitized);
        $sanitized = trim($sanitized, '-');
        return $sanitized;
    }

    /**
     * Get file date from metadata or filesystem
     *
     * @param array<string, mixed> $metadata File metadata
     * @param string $filePath Path to the file
     * @return string Formatted date string
     */
    private function getFileDate(array $metadata, string $filePath): string
    {
        // Check for published_date in metadata
        if (isset($metadata['published_date'])) {
            return $metadata['published_date'];
        }

        // Fall back to source file modification time
        if (file_exists($filePath)) {
            $mtime = filemtime($filePath);
            if ($mtime === false) {
                return date('Y-m-d');
            }
            return date('Y-m-d', $mtime);
        }

        return date('Y-m-d'); // Current date as last resort
    }
}
