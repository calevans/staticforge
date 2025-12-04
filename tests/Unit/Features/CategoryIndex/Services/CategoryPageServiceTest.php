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
}
