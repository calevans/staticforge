<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\CategoryIndex\Services;

use EICC\StaticForge\Features\CategoryIndex\Models\Category;
use EICC\StaticForge\Features\CategoryIndex\Models\CategoryFile;
use EICC\Utils\Container;
use EICC\Utils\Log;

class CategoryService
{
    private Log $logger;
    private ImageService $imageService;
    /** @var array<string, Category> */
    private array $categories = [];

    public function __construct(Log $logger, ImageService $imageService)
    {
        $this->logger = $logger;
        $this->imageService = $imageService;
    }

    public function scanCategories(Container $container): void
    {
        $discoveredFiles = $container->getVariable('discovered_files') ?? [];

        foreach ($discoveredFiles as $fileData) {
            $metadata = $fileData['metadata'];

            if (isset($metadata['type']) && $metadata['type'] === 'category') {
                $slug = pathinfo($fileData['path'], PATHINFO_FILENAME);
                $this->categories[$slug] = new Category($slug, $metadata);
                $this->logger->log('INFO', "Found category: {$slug}");
            }
        }
    }

    /**
     * @return array<string, Category>
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    public function getCategory(string $slug): ?Category
    {
        return $this->categories[$slug] ?? null;
    }

    public function collectFile(Container $container, array $parameters): void
    {
        $metadata = $parameters['metadata'] ?? [];
        $categoryName = $metadata['category'] ?? null;

        if (!$categoryName) {
            return;
        }

        $outputPath = $parameters['output_path'] ?? null;
        $filePath = $parameters['file_path'] ?? null;
        $renderedContent = $parameters['rendered_content'] ?? '';
        $title = $metadata['title'] ?? 'Untitled';

        if (!$outputPath || !$filePath || !$renderedContent) {
            return;
        }

        $slug = $this->sanitizeSlug($categoryName);

        // Create category on the fly if it doesn't exist (implicit category)
        if (!isset($this->categories[$slug])) {
            $this->categories[$slug] = new Category($slug, ['title' => $categoryName]);
        }

        $category = $this->categories[$slug];

        // Process Image
        $imageUrl = $this->imageService->extractHeroImage($renderedContent, $filePath, $container);
        $date = $this->getFileDate($metadata, $filePath);

        // Calculate URL relative to output dir
        $outputDir = $container->getVariable('OUTPUT_DIR');

        // Normalize paths to ensure consistent separators
        $normalizedOutputDir = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR;

        if (str_starts_with($outputPath, $normalizedOutputDir)) {
            $url = str_replace('\\', '/', substr($outputPath, strlen($normalizedOutputDir)));
        } else {
            // Fallback if path doesn't start with output dir
            $url = $slug . '/' . basename($outputPath);
        }

        $file = new CategoryFile($title, $url, $date, $metadata);
        $file->image = $imageUrl;

        $category->addFile($file);
    }

    private function sanitizeSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug ?? '', '-');
    }

    private function getFileDate(array $metadata, string $filePath): string
    {
        if (isset($metadata['published_date'])) {
            return $metadata['published_date'];
        }
        if (file_exists($filePath)) {
            $mtime = filemtime($filePath);
            if ($mtime !== false) {
                return date('Y-m-d', $mtime);
            }
        }
        return date('Y-m-d');
    }
}
