<?php

namespace EICC\StaticForge\Features\CategoryIndex;

use EICC\StaticForge\Core\Application;
use EICC\Utils\Container;
use EICC\Utils\Log;

class CategoryPageGenerator
{
    private Log $logger;
    private CategoryManager $categoryManager;

    /**
     * Category files to generate after main loop
     * @var array<string, array{files: array<string>, output_path: string}>
     */
    private array $deferredCategoryFiles = [];

    public function __construct(Log $logger, CategoryManager $categoryManager)
    {
        $this->logger = $logger;
        $this->categoryManager = $categoryManager;
    }

    public function deferCategoryFile(string $filePath, array $metadata, Container $container): void
    {
        $categorySlug = pathinfo($filePath, PATHINFO_FILENAME);
        
        // Determine correct output path: public/{category}/index.html
        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set in container');
        }
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $categorySlug . DIRECTORY_SEPARATOR . 'index.html';

        // Store this file for later processing
        $this->deferredCategoryFiles[] = [
            'file_path' => $filePath,
            'metadata' => $metadata,
            'output_path' => $outputPath
        ];

        $this->logger->log('INFO', "Deferring category file for later processing: {$filePath}");
    }

    public function processDeferredCategoryFiles(Container $container): void
    {
        if (empty($this->deferredCategoryFiles)) {
            $this->logger->log('INFO', 'No deferred category files to process');
            return;
        }

        $this->logger->log('INFO', 'Processing ' . count($this->deferredCategoryFiles) . ' deferred category files');

        foreach ($this->deferredCategoryFiles as $categoryFile) {
            $this->processCategoryFile($categoryFile, $container);
        }
    }

    /**
     * Process a single category file through the rendering pipeline
     *
     * @param array<string, mixed> $categoryFile Category file data with metadata
     * @param Container $container Dependency injection container
     */
    private function processCategoryFile(array $categoryFile, Container $container): void
    {
        $filePath = $categoryFile['file_path'];
        $metadata = $categoryFile['metadata'];

        // Determine category slug from file path (e.g., business.md -> business)
        $categorySlug = pathinfo($filePath, PATHINFO_FILENAME);

        // Get collected files for this category
        $categoryData = $this->categoryManager->getCategoryFiles($categorySlug);

        $this->logger->log(
            'INFO',
            "Processing category file: {$filePath} with " . count($categoryData['files'] ?? []) . " files"
        );

        // Build complete markdown content with frontmatter
        // Include the files array in metadata so Twig can access it
        $frontmatter = "---\n";
        foreach ($metadata as $key => $value) {
            if ($key !== 'type') {  // Don't include type = category in output
                $frontmatter .= "{$key} = {$value}\n";
            }
        }
        $frontmatter .= "category_files_count = " . count($categoryData['files'] ?? []) . "\n";
        $frontmatter .= "---\n\n";
        $markdownContent = $frontmatter . "<!-- Category file listing will be rendered by template -->";

        // Store category_files in container features so template can access it
        $features = $container->getVariable('features') ?? [];
        $features['CategoryIndex']['category_files'] = $categoryData['files'] ?? [];
        $container->updateVariable('features', $features);

        // Get Application instance from container
        $application = $container->get(Application::class);

        try {
            // Use Application's renderSingleFile method with additional context
            $application->renderSingleFile($filePath, [
                'file_content' => $markdownContent,  // Provide the content to MarkdownRenderer
                'metadata' => array_merge($metadata, [
                    'category_files' => $categoryData['files'] ?? [],  // Pass files to template
                    'total_files' => count($categoryData['files'] ?? []),
                ]),
                'output_path' => $categoryFile['output_path'],
                'bypass_category_defer' => true  // Tell PRE_RENDER to not defer this file
            ]);
        } catch (\Exception $e) {
            $this->logger->log('ERROR', "Failed to process category file {$filePath}: " . $e->getMessage());
        }
    }
}
