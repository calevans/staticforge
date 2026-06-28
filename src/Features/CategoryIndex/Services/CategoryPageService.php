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
    private PaginationService $paginationService;
    private int $itemsPerPage;
    /** @var array<int, array{file_path: string, output_path: string, metadata: array<string, mixed>}> */
    private array $deferredFiles = [];

    public function __construct(
        Log $logger,
        CategoryService $categoryService,
        PaginationService $paginationService,
        int $itemsPerPage = 10
    ) {
        $this->logger = $logger;
        $this->categoryService = $categoryService;
        $this->paginationService = $paginationService;
        $this->itemsPerPage = $itemsPerPage;
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

        // Change: Use directory/index.html structure for "pretty URLs" and to avoid conflict with asset directories
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'index.html';

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

        // Update global features context for template
        $features = $container->getVariable('features') ?? [];
        $features['CategoryIndex']['category_files'] = $filesArray;
        $container->updateVariable('features', $features);

        try {
            // Sort files based on frontmatter settings (must happen before slicing into pages)
            $filesArray = $this->sortFiles($filesArray, $fileData['metadata']);
            $totalFiles = count($filesArray);

            $categoryUrl = $this->deriveCategoryUrl($fileData['output_path'], $container);
            $totalPages = $this->paginationService->totalPages($totalFiles, $this->itemsPerPage);

            for ($page = 1; $page <= $totalPages; $page++) {
                $pageFiles = $this->paginationService->sliceForPage($filesArray, $page, $this->itemsPerPage);
                $pagination = $this->paginationService->buildPagination($page, $totalPages, $categoryUrl);
                $outputPath = $page === 1
                    ? $fileData['output_path']
                    : $this->buildPagedOutputPath($fileData['output_path'], $page);

                $enrichedMetadata = array_merge($fileData['metadata'], [
                    'category_files_count' => count($pageFiles),
                    'category_files' => $pageFiles,
                    'total_files' => $totalFiles,
                    'current_page' => $pagination->currentPage,
                    'total_pages' => $pagination->totalPages,
                    'pagination_prev_url' => $pagination->prevUrl,
                    'pagination_next_url' => $pagination->nextUrl,
                    'per_page' => $this->itemsPerPage,
                ]);
                unset($enrichedMetadata['type']);

                // Also update the global context with this page's files
                $features['CategoryIndex']['category_files'] = $pageFiles;
                $container->updateVariable('features', $features);

                $application->renderSingleFile($filePath, [
                    'file_metadata' => $enrichedMetadata,
                    'output_path' => $outputPath,
                    'bypass_category_defer' => true
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->log('ERROR', "Failed to render category page {$filePath}: " . $e->getMessage());
            $this->logger->log('ERROR', $e->getTraceAsString());
        }
    }

    /**
     * Derives the category's page-1 URL (e.g. "/{slug}/") from its page-1 output path,
     * reusing the same relative-URL derivation convention as CategoryService::collectFile().
     */
    private function deriveCategoryUrl(string $page1OutputPath, Container $container): string
    {
        $outputDir = rtrim((string) $container->getVariable('OUTPUT_DIR'), '/\\') . DIRECTORY_SEPARATOR;
        $relative = str_replace('\\', '/', substr($page1OutputPath, strlen($outputDir)));
        $relative = preg_replace('#/?index\.html$#', '/', $relative) ?? $relative;
        return '/' . ltrim($relative, '/');
    }

    /**
     * Builds the output path for page N>1: "{slugDir}/page/{n}/index.html",
     * derived from page 1's output path "{slugDir}/index.html".
     */
    private function buildPagedOutputPath(string $page1OutputPath, int $page): string
    {
        $categoryDir = rtrim(dirname($page1OutputPath), '/\\');
        return $categoryDir . DIRECTORY_SEPARATOR . 'page' . DIRECTORY_SEPARATOR . $page
            . DIRECTORY_SEPARATOR . 'index.html';
    }

    /**
     * @param array<int, array<string, mixed>> $files
     * @param array<string, mixed> $metadata
     * @return array<int, array<string, mixed>>
     */
    private function sortFiles(array $files, array $metadata): array
    {
        if (empty($files)) {
            return $files;
        }

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
            $sortDirection = ($sortBy === 'published_date' || $sortBy === 'date') ? 'desc' : 'asc';
        }

        $sortDirection = strtolower($sortDirection);

        if ($sortBy === 'random' || $sortDirection === 'random') {
            shuffle($files);
            return $files;
        }

        try {
            usort($files, function ($a, $b) use ($sortBy, $sortDirection) {
                $key = ($sortBy === 'published_date' || $sortBy === 'date') ? 'date' : 'title';

                $valA = $a[$key] ?? '';
                $valB = $b[$key] ?? '';

                if ($key === 'date') {
                    // Date comparison
                    $timeA = strtotime((string)$valA) ?: 0;
                    $timeB = strtotime((string)$valB) ?: 0;
                    $result = $timeA <=> $timeB;
                } else {
                    // String comparison (title)
                    $result = strnatcasecmp((string)$valA, (string)$valB);
                }

                return ($sortDirection === 'desc') ? -$result : $result;
            });
        } catch (\Throwable $e) {
            $this->logger->log('ERROR', "Sorting failed: " . $e->getMessage());
            return $files;
        }

        return $files;
    }
}
