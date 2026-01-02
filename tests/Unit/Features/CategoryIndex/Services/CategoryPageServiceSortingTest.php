<?php

namespace EICC\StaticForge\Tests\Unit\Features\CategoryIndex\Services;

use EICC\StaticForge\Features\CategoryIndex\Services\CategoryPageService;
use EICC\StaticForge\Features\CategoryIndex\Services\CategoryService;
use EICC\StaticForge\Features\CategoryIndex\Models\Category;
use EICC\StaticForge\Features\CategoryIndex\Models\CategoryFile;
use EICC\StaticForge\Core\Application;
use EICC\Utils\Log;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class CategoryPageServiceSortingTest extends UnitTestCase
{
    private CategoryPageService $service;
    private Log $logger;
    private CategoryService $categoryService;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/staticforge_sort_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->logger = $this->createMock(Log::class);
        $this->categoryService = $this->createMock(CategoryService::class);
        $this->service = new CategoryPageService($this->logger, $this->categoryService);

        $this->setContainerVariable('OUTPUT_DIR', $this->tempDir);
        $this->setContainerVariable('features', []);
    }

    protected function tearDown(): void
    {
        $this->recursiveRemoveDirectory($this->tempDir);
        parent::tearDown();
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * @dataProvider sortingDataProvider
     */
    public function testSorting(string $sortBy, string $sortDirection, array $files, array $expectedOrder): void
    {
        $fileName = 'test_' . $sortBy . '_' . $sortDirection . '.md';
        $filePath = '/path/to/' . $fileName;

        // Setup deferred file
        $metadata = ['title' => 'Test Category'];
        if ($sortBy) $metadata['sort_by'] = $sortBy;
        if ($sortDirection) $metadata['sort_direction'] = $sortDirection;

        $this->service->deferFile($filePath, $metadata, $this->container);

        // Mock CategoryService
        $category = new Category('test', ['title' => 'Test Category']);
        foreach ($files as $f) {
            $category->addFile(new CategoryFile($f['title'], $f['url'], $f['date'], $f['metadata'] ?? []));
        }

        $this->categoryService->method('getCategory')->willReturn($category);

        // Mock Application
        $mockApp = $this->createMock(Application::class);
        $mockApp->expects($this->once())
            ->method('renderSingleFile')
            ->with(
                $this->equalTo($filePath),
                $this->callback(function ($context) use ($expectedOrder, $sortBy, $sortDirection) {
                    $files = $context['file_metadata']['category_files'];

                    if ($sortBy === 'random' || $sortDirection === 'random') {
                        return count($files) === count($expectedOrder);
                    }

                    $actualTitles = array_column($files, 'title');

                    // Check if order matches
                    $isMatch = $actualTitles === $expectedOrder;
                    if (!$isMatch) {
                        echo "\nExpected: " . implode(', ', $expectedOrder);
                        echo "\nActual:   " . implode(', ', $actualTitles);
                    }
                    return $isMatch;
                })
            );

        $this->container->add(Application::class, $mockApp);

        $this->service->processDeferredFiles($this->container);
    }

    public static function sortingDataProvider(): array
    {
        $files = [
            ['title' => 'A Post', 'url' => '/a', 'date' => '2023-01-01'],
            ['title' => 'B Post', 'url' => '/b', 'date' => '2023-01-02'],
            ['title' => 'C Post', 'url' => '/c', 'date' => '2023-01-03'],
        ];

        return [
            'date_desc' => [
                'published_date', 'desc',
                $files,
                ['C Post', 'B Post', 'A Post']
            ],
            'date_asc' => [
                'published_date', 'asc',
                $files,
                ['A Post', 'B Post', 'C Post']
            ],
            'title_asc' => [
                'title', 'asc',
                $files,
                ['A Post', 'B Post', 'C Post']
            ],
            'title_desc' => [
                'title', 'desc',
                $files,
                ['C Post', 'B Post', 'A Post']
            ],
            'default_date_desc' => [
                'published_date', '', // Default direction for date is desc
                $files,
                ['C Post', 'B Post', 'A Post']
            ],
            'default_title_asc' => [
                'title', '', // Default direction for title is asc
                $files,
                ['A Post', 'B Post', 'C Post']
            ],
             'random_sort_by' => [
                'random', '',
                $files,
                ['A Post', 'B Post', 'C Post'] // Expectation ignored in callback for random
            ],
             'random_sort_direction' => [
                'published_date', 'random',
                $files,
                ['A Post', 'B Post', 'C Post'] // Expectation ignored in callback for random
            ],
        ];
    }

    public function testSortingWithMenuOverride(): void
    {
        $files = [
            ['title' => 'Z Post', 'url' => '/z', 'date' => '2023-01-01', 'metadata' => ['menu' => 1]],
            ['title' => 'A Post', 'url' => '/a', 'date' => '2023-01-02'],
        ];

        $this->testSorting('title', 'asc', $files, ['Z Post', 'A Post']);
    }
}
