<?php

namespace EICC\StaticForge\Tests\Unit\Features\CategoryIndex;

use EICC\StaticForge\Features\CategoryIndex\CategoryPageGenerator;
use EICC\StaticForge\Features\CategoryIndex\CategoryManager;
use EICC\StaticForge\Core\Application;
use EICC\Utils\Log;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class CategoryPageGeneratorTest extends UnitTestCase
{
    private CategoryPageGenerator $generator;
    private Log $logger;
    private CategoryManager $manager;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/staticforge_gen_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->logger = $this->createMock(Log::class);
        $this->manager = $this->createMock(CategoryManager::class);
        $this->generator = new CategoryPageGenerator($this->logger, $this->manager);

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
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testDeferCategoryFile(): void
    {
        $this->generator->deferCategoryFile(
            '/path/to/tech.md',
            ['title' => 'Tech'],
            $this->container
        );

        // We can't easily inspect private property deferredCategoryFiles without reflection,
        // but we can verify it processes them later.
        // Or use reflection to verify state.
        $reflection = new \ReflectionClass($this->generator);
        $prop = $reflection->getProperty('deferredCategoryFiles');
        $prop->setAccessible(true);
        $deferred = $prop->getValue($this->generator);

        $this->assertCount(1, $deferred);
        $this->assertEquals('/path/to/tech.md', $deferred[0]['file_path']);
    }

    public function testProcessDeferredCategoryFiles(): void
    {
        // Setup deferred file
        $this->generator->deferCategoryFile(
            '/path/to/tech.md',
            ['title' => 'Tech'],
            $this->container
        );

        // Mock CategoryManager to return some files
        $this->manager->method('getCategoryFiles')
            ->willReturn(['files' => [['title' => 'Post 1']]]);

        // Mock Application
        $mockApp = $this->createMock(Application::class);
        $mockApp->expects($this->once())
            ->method('renderSingleFile')
            ->with(
                $this->equalTo('/path/to/tech.md'),
                $this->callback(function($context) {
                    return isset($context['metadata']['category_files']) 
                        && count($context['metadata']['category_files']) === 1;
                })
            );
        
        $this->container->add(Application::class, $mockApp);

        $this->generator->processDeferredCategoryFiles($this->container);
    }
}
