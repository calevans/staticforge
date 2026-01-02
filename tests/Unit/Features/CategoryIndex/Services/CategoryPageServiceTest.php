<?php

namespace EICC\StaticForge\Tests\Unit\Features\CategoryIndex\Services;

use EICC\StaticForge\Features\CategoryIndex\Services\CategoryPageService;
use EICC\StaticForge\Features\CategoryIndex\Services\CategoryService;
use EICC\StaticForge\Features\CategoryIndex\Models\Category;
use EICC\StaticForge\Features\CategoryIndex\Models\CategoryFile;
use EICC\StaticForge\Core\Application;
use EICC\Utils\Log;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class CategoryPageServiceTest extends UnitTestCase
{
    private CategoryPageService $service;
    private Log $logger;
    private CategoryService $categoryService;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/staticforge_gen_test_' . uniqid();
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
}
