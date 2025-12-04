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

    public function deferFile(string $filePath, array $metadata, Container $container): void
    {
        $slug = pathinfo($filePath, PATHINFO_FILENAME);

        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set');
        }

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
                'file_content' => "<!-- Category file listing -->",
                'file_metadata' => $enrichedMetadata,
                'output_path' => $fileData['output_path'],
                'bypass_category_defer' => true
            ]);
        } catch (\Exception $e) {
            $this->logger->log('ERROR', "Failed to render category page {$filePath}: " . $e->getMessage());
        }
    }
}
