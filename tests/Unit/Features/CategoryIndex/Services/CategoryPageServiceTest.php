<?php

namespace EICC\StaticForge\Tests\Unit\Features\CategoryIndex\Services;

use EICC\StaticForge\Features\CategoryIndex\Services\CategoryPageService;
use EICC\StaticForge\Features\CategoryIndex\Services\CategoryService;
use EICC\StaticForge\Features\CategoryIndex\Services\PaginationService;
use EICC\StaticForge\Features\CategoryIndex\Models\Category;
use EICC\StaticForge\Features\CategoryIndex\Models\CategoryFile;
use EICC\StaticForge\Core\Application;
use EICC\Utils\Log;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class CategoryPageServiceTest extends UnitTestCase
{
    private CategoryPageService $service;
    private Log&MockObject $logger;
    private CategoryService&MockObject $categoryService;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/staticforge_gen_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->logger = $this->createMock(Log::class);
        $this->categoryService = $this->createMock(CategoryService::class);
        $this->service = new CategoryPageService(
            $this->logger,
            $this->categoryService,
            new PaginationService(),
            10
        );

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

    public function testDeferFile(): void
    {
        $this->service->deferFile(
            '/path/to/tech.md',
            ['title' => 'Tech'],
            $this->container
        );

        // Use reflection to verify state
        $reflection = new \ReflectionClass($this->service);
        $prop = $reflection->getProperty('deferredFiles');
        $prop->setAccessible(true);
        $deferred = $prop->getValue($this->service);

        $this->assertCount(1, $deferred);
        $this->assertEquals('/path/to/tech.md', $deferred[0]['file_path']);
    }

    public function testProcessDeferredFiles(): void
    {
        // Setup deferred file
        $this->service->deferFile(
            '/path/to/tech.md',
            ['title' => 'Tech'],
            $this->container
        );

        // Mock CategoryService to return a category with files
        $category = new Category('tech', ['title' => 'Tech']);
        $file = new CategoryFile('Post 1', '/url', '2023-01-01');
        $category->addFile($file);

        $this->categoryService->method('getCategory')
            ->with('tech')
            ->willReturn($category);

        // Mock Application
        $mockApp = $this->createMock(Application::class);
        $mockApp->expects($this->once())
            ->method('renderSingleFile')
            ->with(
                $this->equalTo('/path/to/tech.md'),
                $this->callback(function ($context) {
                    // Check that we are passing enriched metadata in 'file_metadata'
                    return isset($context['file_metadata']['category_files'])
                        && count($context['file_metadata']['category_files']) === 1
                        && isset($context['file_metadata']['category_files_count'])
                        && $context['file_metadata']['category_files_count'] === 1;
                })
            );

        $this->container->add(Application::class, $mockApp);

        $this->service->processDeferredFiles($this->container);
    }

    public function testSortingByTitleAsc(): void
    {
        // Setup deferred file
        $this->service->deferFile(
            '/path/to/tech.md',
            ['title' => 'Tech', 'sort_by' => 'title', 'sort_direction' => 'asc'],
            $this->container
        );

        // Mock CategoryService to return a category with files
        $category = new Category('tech', ['title' => 'Tech']);
        $file1 = new CategoryFile('B Post', '/url1', '2023-01-01');
        $file2 = new CategoryFile('A Post', '/url2', '2023-01-02');
        $category->addFile($file1);
        $category->addFile($file2);

        $this->categoryService->method('getCategory')
            ->with('tech')
            ->willReturn($category);

        // Mock Application
        $mockApp = $this->createMock(Application::class);
        $mockApp->expects($this->once())
            ->method('renderSingleFile')
            ->with(
                $this->equalTo('/path/to/tech.md'),
                $this->callback(function ($context) {
                    $files = $context['file_metadata']['category_files'];
                    return $files[0]['title'] === 'A Post' && $files[1]['title'] === 'B Post';
                })
            );

        $this->container->add(Application::class, $mockApp);

        $this->service->processDeferredFiles($this->container);
    }

    public function testSortingByDateDesc(): void
    {
        // Setup deferred file
        $this->service->deferFile(
            '/path/to/news.md',
            ['title' => 'News', 'sort_by' => 'published_date', 'sort_direction' => 'desc'],
            $this->container
        );

        // Mock CategoryService to return a category with files
        $category = new Category('news', ['title' => 'News']);
        $file1 = new CategoryFile('Old Post', '/url1', '2023-01-01');
        $file2 = new CategoryFile('New Post', '/url2', '2023-01-02');
        $category->addFile($file1);
        $category->addFile($file2);

        $this->categoryService->method('getCategory')
            ->with('news')
            ->willReturn($category);

        // Mock Application
        $mockApp = $this->createMock(Application::class);
        $mockApp->expects($this->once())
            ->method('renderSingleFile')
            ->with(
                $this->equalTo('/path/to/news.md'),
                $this->callback(function ($context) {
                    $files = $context['file_metadata']['category_files'];
                    return $files[0]['title'] === 'New Post' && $files[1]['title'] === 'Old Post';
                })
            );

        $this->container->add(Application::class, $mockApp);

        $this->service->processDeferredFiles($this->container);
    }

    public function testSortingByDirectionRandom(): void
    {
        // Setup deferred file
        $this->service->deferFile(
            '/path/to/random.md',
            ['title' => 'Random', 'sort_direction' => 'random'],
            $this->container
        );

        // Mock CategoryService to return a category with files
        $category = new Category('random', ['title' => 'Random']);
        $file1 = new CategoryFile('Post 1', '/url1', '2023-01-01');
        $file2 = new CategoryFile('Post 2', '/url2', '2023-01-02');
        $category->addFile($file1);
        $category->addFile($file2);

        $this->categoryService->method('getCategory')
            ->with('random')
            ->willReturn($category);

        // Mock Application
        $mockApp = $this->createMock(Application::class);
        $mockApp->expects($this->once())
            ->method('renderSingleFile')
            ->with(
                $this->equalTo('/path/to/random.md'),
                $this->callback(function ($context) {
                    $files = $context['file_metadata']['category_files'];
                    // Just check that we have the files, order is random so we can't assert specific order easily
                    // without mocking shuffle, but we can verify it didn't crash and returned files.
                    return count($files) === 2;
                })
            );

        $this->container->add(Application::class, $mockApp);

        $this->service->processDeferredFiles($this->container);
    }

    public function testSortingIgnoredIfMenuExists(): void
    {
        // Setup deferred file with sort instruction
        $this->service->deferFile(
            '/path/to/menu_cat.md',
            ['title' => 'Menu Cat', 'sort_by' => 'title', 'sort_direction' => 'asc'],
            $this->container
        );

        // Mock CategoryService to return a category with files
        $category = new Category('menu_cat', ['title' => 'Menu Cat']);
        // Add files in non-alphabetical order
        $file1 = new CategoryFile('Z Post', '/url1', '2023-01-01', ['menu' => 1]);
        $file2 = new CategoryFile('A Post', '/url2', '2023-01-02');
        $category->addFile($file1);
        $category->addFile($file2);

        $this->categoryService->method('getCategory')
            ->with('menu_cat')
            ->willReturn($category);

        // Mock Application
        $mockApp = $this->createMock(Application::class);
        $mockApp->expects($this->once())
            ->method('renderSingleFile')
            ->with(
                $this->equalTo('/path/to/menu_cat.md'),
                $this->callback(function ($context) {
                    $files = $context['file_metadata']['category_files'];
                    // Should NOT be sorted by title (A Post, Z Post)
                    // Should remain in added order (Z Post, A Post) because one file has 'menu'
                    return $files[0]['title'] === 'Z Post' && $files[1]['title'] === 'A Post';
                })
            );

        $this->container->add(Application::class, $mockApp);

        $this->service->processDeferredFiles($this->container);
    }

    public function testSinglePageCategoryRendersExactlyOnce(): void
    {
        $this->service->deferFile(
            '/path/to/tech.md',
            ['title' => 'Tech'],
            $this->container
        );

        $category = new Category('tech', ['title' => 'Tech']);
        for ($i = 1; $i <= 5; $i++) {
            $category->addFile(new CategoryFile("Post {$i}", "/url{$i}", '2023-01-0' . $i));
        }

        $this->categoryService->method('getCategory')
            ->with('tech')
            ->willReturn($category);

        $expectedOutputPath = $this->tempDir . DIRECTORY_SEPARATOR . 'tech' . DIRECTORY_SEPARATOR . 'index.html';

        $mockApp = $this->createMock(Application::class);
        $mockApp->expects($this->once())
            ->method('renderSingleFile')
            ->with(
                $this->equalTo('/path/to/tech.md'),
                $this->callback(function ($context) use ($expectedOutputPath) {
                    $metadata = $context['file_metadata'];
                    return $context['output_path'] === $expectedOutputPath
                        && $metadata['current_page'] === 1
                        && $metadata['total_pages'] === 1
                        && $metadata['pagination_prev_url'] === null
                        && $metadata['pagination_next_url'] === null
                        && $metadata['category_files_count'] === 5
                        && $metadata['total_files'] === 5;
                })
            );

        $this->container->add(Application::class, $mockApp);

        $this->service->processDeferredFiles($this->container);
    }

    public function testMultiPageCategoryRendersOncePerPage(): void
    {
        $this->service->deferFile(
            '/path/to/tech.md',
            ['title' => 'Tech', 'sort_by' => 'title', 'sort_direction' => 'asc'],
            $this->container
        );

        $category = new Category('tech', ['title' => 'Tech']);
        // 25 files, titled so alphabetical sort == insertion order
        for ($i = 1; $i <= 25; $i++) {
            $title = sprintf('Post %02d', $i);
            $category->addFile(new CategoryFile($title, "/url{$i}", '2023-01-01'));
        }

        $this->categoryService->method('getCategory')
            ->with('tech')
            ->willReturn($category);

        $categoryDir = $this->tempDir . DIRECTORY_SEPARATOR . 'tech';
        $pageDir = $categoryDir . DIRECTORY_SEPARATOR . 'page' . DIRECTORY_SEPARATOR;
        $expectedPaths = [
            1 => $categoryDir . DIRECTORY_SEPARATOR . 'index.html',
            2 => $pageDir . '2' . DIRECTORY_SEPARATOR . 'index.html',
            3 => $pageDir . '3' . DIRECTORY_SEPARATOR . 'index.html',
        ];
        $expectedCounts = [1 => 10, 2 => 10, 3 => 5];
        $expectedPrev = [1 => null, 2 => '/tech/', 3 => '/tech/page/2/'];
        $expectedNext = [1 => '/tech/page/2/', 2 => '/tech/page/3/', 3 => null];

        $calls = [];

        $mockApp = $this->createMock(Application::class);
        $mockApp->expects($this->exactly(3))
            ->method('renderSingleFile')
            ->with(
                $this->equalTo('/path/to/tech.md'),
                $this->callback(function ($context) use (
                    &$calls,
                    $expectedPaths,
                    $expectedCounts,
                    $expectedPrev,
                    $expectedNext
                ) {
                    $metadata = $context['file_metadata'];
                    $page = $metadata['current_page'];
                    $calls[] = $page;

                    $files = $metadata['category_files'];
                    $firstTitleOnPage = $files[0]['title'] ?? null;
                    $expectedFirstTitle = sprintf('Post %02d', (($page - 1) * 10) + 1);

                    return $context['output_path'] === $expectedPaths[$page]
                        && $metadata['total_pages'] === 3
                        && $metadata['category_files_count'] === $expectedCounts[$page]
                        && $metadata['total_files'] === 25
                        && $metadata['pagination_prev_url'] === $expectedPrev[$page]
                        && $metadata['pagination_next_url'] === $expectedNext[$page]
                        && $firstTitleOnPage === $expectedFirstTitle;
                })
            );

        $this->container->add(Application::class, $mockApp);

        $this->service->processDeferredFiles($this->container);

        $this->assertSame([1, 2, 3], $calls);
    }
}
