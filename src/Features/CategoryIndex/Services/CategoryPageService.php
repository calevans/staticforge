<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\CategoryIndex\Services;

use EICC\StaticForge\Core\Application;
use EICC\StaticForge\Features\CategoryIndex\Models\Category;
use EICC\Utils\Container;
use EICC\Utils\Log;

class CategoryPageService
{
    private Log $logger;
    private CategoryService $categoryService;
    /** @var array<string, array{file_path: string, output_path: string, metadata: array<string, mixed>}> */
    private array $deferredFiles = [];

    public function __construct(Log $logger, CategoryService $categoryService)
    {
        $this->logger = $logger;
        $this->categoryService = $categoryService;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function deferFile(string $filePath, array $metadata, Container $container): void
    {
        $slug = pathinfo($filePath, PATHINFO_FILENAME);

        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set');
        }

        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $slug . '.html';

        $this->deferredFiles[] = [
            'file_path' => $filePath,
            'metadata' => $metadata,
            'output_path' => $outputPath
        ];

        $this->logger->log('INFO', "Deferring category file: {$filePath}");
    }

    public function processDeferredFiles(Container $container): void
    {
        if (empty($this->deferredFiles)) {
            return;
        }

        $this->logger->log('INFO', 'Processing ' . count($this->deferredFiles) . ' deferred category files');
        $application = $container->get(Application::class);

        foreach ($this->deferredFiles as $fileData) {
            $this->renderCategoryPage($fileData, $application, $container);
        }
    }

    /**
     * @param array{file_path: string, output_path: string, metadata: array<string, mixed>} $fileData
     */
    private function renderCategoryPage(array $fileData, Application $application, Container $container): void
    {
        $filePath = $fileData['file_path'];
        $slug = pathinfo($filePath, PATHINFO_FILENAME);
        $category = $this->categoryService->getCategory($slug);

        // Convert CategoryFile objects to arrays for the template
        $filesArray = [];
        if ($category) {
            foreach ($category->files as $file) {
                $filesArray[] = [
                    'title' => $file->title,
                    'url' => $file->url,
                    'date' => $file->date,
                    'image' => $file->image,
                    'metadata' => $file->metadata
                ];
            }
        }

        // Sort files based on frontmatter settings
        $filesArray = $this->sortFiles($filesArray, $fileData['metadata']);

        $enrichedMetadata = array_merge($fileData['metadata'], [
            'category_files_count' => count($filesArray),
            'category_files' => $filesArray,
            'total_files' => count($filesArray),
        ]);
        unset($enrichedMetadata['type']);

        // Update global features context for template
        $features = $container->getVariable('features') ?? [];
        $features['CategoryIndex']['category_files'] = $filesArray;
        $container->updateVariable('features', $features);

        try {
            $application->renderSingleFile($filePath, [
                'file_metadata' => $enrichedMetadata,
                'output_path' => $fileData['output_path'],
                'bypass_category_defer' => true
            ]);
        } catch (\Exception $e) {
            $this->logger->log('ERROR', "Failed to render category page {$filePath}: " . $e->getMessage());
        }
    }

    /**
     * @param array<int, array<string, mixed>> $files
     * @param array<string, mixed> $metadata
     * @return array<int, array<string, mixed>>
     */
    private function sortFiles(array $files, array $metadata): array
    {
        // If any file has a 'menu' property, do not sort (preserve order or let menu builder handle it)
        foreach ($files as $file) {
            if (isset($file['metadata']['menu'])) {
                return $files;
            }
        }

        $sortBy = $metadata['sort_by'] ?? 'published_date';
        $sortDirection = $metadata['sort_direction'] ?? null;

        // Determine default direction if not specified
        if ($sortDirection === null) {
            $sortDirection = ($sortBy === 'published_date') ? 'desc' : 'asc';
        }

        $sortDirection = strtolower($sortDirection);

        if ($sortBy === 'random' || $sortDirection === 'random') {
            shuffle($files);
            return $files;
        }

        usort($files, function ($a, $b) use ($sortBy, $sortDirection) {
            $valA = $a[$sortBy === 'published_date' ? 'date' : 'title'] ?? '';
            $valB = $b[$sortBy === 'published_date' ? 'date' : 'title'] ?? '';

            if ($sortBy === 'published_date') {
                // Date comparison
                $timeA = strtotime($valA) ?: 0;
                $timeB = strtotime($valB) ?: 0;
                $result = $timeA <=> $timeB;
            } else {
                // String comparison (title)
                $result = strnatcasecmp($valA, $valB);
            }

            return ($sortDirection === 'desc') ? -$result : $result;
        });

        return $files;
    }
}
