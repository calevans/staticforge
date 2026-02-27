<?php

namespace EICC\StaticForge\Tests\Unit\Features;

use EICC\StaticForge\Features\CategoryIndex\Feature;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Application;
use EICC\Utils\Container;
use EICC\Utils\Log;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class CategoryIndexFeatureTest extends UnitTestCase
{
    private Feature $feature;

    private EventManager $eventManager;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

      // Create temp directory for tests
        $this->tempDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

      // Setup container
        $this->setContainerVariable('OUTPUT_DIR', $this->tempDir);
        $this->setContainerVariable('SITE_NAME', 'Test Site');
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com/');
        $this->setContainerVariable('CATEGORY_PAGINATION', 5);
        $this->setContainerVariable('TEMPLATE_DIR', 'templates');
        $this->setContainerVariable('TEMPLATE', 'sample');
        $this->setContainerVariable('features', []); // Initialize features array

        $this->eventManager = new EventManager($this->container);

        // Mock Application with renderSingleFile method
        $mockApp = $this->createMock(Application::class);
        $mockApp->method('renderSingleFile')->willReturnCallback(function ($filePath, $context) {
            // Simulate rendering by creating the output file
            $outputPath = $context['output_path'] ?? $filePath;

            // Use file_metadata if available (new behavior), fallback to metadata (old behavior)
            $metadata = $context['file_metadata'] ?? $context['metadata'] ?? [];

            $content = "<!-- Generated category index -->\n";
            $content .= "<h1>" . ($metadata['title'] ?? 'Category Index') . "</h1>\n";

            // Include files in output
            if (isset($metadata['category_files'])) {
                foreach ($metadata['category_files'] as $file) {
                    $content .= "<div>" . ($file['title'] ?? 'Untitled') . "</div>\n";
                }
            }

            // Add pagination if needed
            if (isset($metadata['total_files']) && $metadata['total_files'] > 5) {
                $content .= '<div data-per-page="5"></div>';
                $content .= '<script>function showPage() {} function updatePagination() {}</script>';
            }

            // Create directory if needed
            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($outputPath, $content);

            return $context;
        });        $this->container->add(\EICC\StaticForge\Core\Application::class, $mockApp);

        $this->feature = new Feature();
        $this->feature->setContainer($this->container);
        $this->feature->register($this->eventManager);
    }

    protected function tearDown(): void
    {
      // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->recursiveRemoveDirectory($this->tempDir);
        }
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testFeatureRegistration(): void
    {
        $this->assertInstanceOf(Feature::class, $this->feature);
    }

    public function testCollectsCategoryFiles(): void
    {
        $parameters = [
        'metadata' => [
        'title' => 'Test Page',
        'category' => 'Technology'
        ],
        'output_path' => $this->tempDir . '/technology/test.html'
        ];

        $result = $this->feature->collectCategoryFiles($this->container, $parameters);

      // Should return parameters unchanged
        $this->assertEquals($parameters, $result);
    }

    public function testIgnoresFilesWithoutCategory(): void
    {
        $parameters = [
        'metadata' => [
        'title' => 'Test Page'
        ],
        'output_path' => $this->tempDir . '/test.html'
        ];

        $result = $this->feature->collectCategoryFiles($this->container, $parameters);

        $this->assertEquals($parameters, $result);
    }

    public function testGeneratesCategoryIndex(): void
    {
      // First, set up a deferred category file (simulating POST_GLOB scan)
        $this->setDeferredCategoryFiles([
        [
        'file_path' => $this->tempDir . '/tech.md',
        'metadata' => ['title' => 'Tech', 'menu' => '1'],
        'output_path' => $this->tempDir . '/tech/index.html'
        ]
        ]);

      // Collect some files (simulating POST_RENDER)
        $files = [
        ['title' => 'File 1', 'category' => 'Tech', 'path' => '/tech/file1.html'],
        ['title' => 'File 2', 'category' => 'Tech', 'path' => '/tech/file2.html'],
        ['title' => 'File 3', 'category' => 'Tech', 'path' => '/tech/file3.html']
        ];

        foreach ($files as $file) {
            $this->feature->collectCategoryFiles($this->container, [
            'metadata' => [
            'title' => $file['title'],
            'category' => $file['category']
            ],
            'output_path' => $this->tempDir . $file['path'],
            'file_path' => $this->tempDir . $file['path'],
            'rendered_content' => '<p>Test content</p>'
            ]);
        }

      // Generate indexes (simulating POST_LOOP)
        $this->feature->processDeferredCategoryFiles($this->container, []);

      // Check that index file was created
        $indexPath = $this->tempDir . '/tech/index.html';
        $this->assertFileExists($indexPath);

      // Verify content
        $content = file_get_contents($indexPath);
        $this->assertStringContainsString('File 1', $content);
        $this->assertStringContainsString('File 2', $content);
        $this->assertStringContainsString('File 3', $content);
    }

    public function testHandlesMultipleCategories(): void
    {
      // Set up deferred category files
        $this->setDeferredCategoryFiles([
        [
        'file_path' => $this->tempDir . '/technology.md',
        'metadata' => ['title' => 'Technology'],
        'output_path' => $this->tempDir . '/technology/index.html'
        ],
        [
        'file_path' => $this->tempDir . '/blog.md',
        'metadata' => ['title' => 'Blog'],
        'output_path' => $this->tempDir . '/blog/index.html'
        ]
        ]);

        $files = [
        ['title' => 'Tech 1', 'category' => 'Technology', 'path' => '/technology/tech1.html'],
        ['title' => 'Blog 1', 'category' => 'Blog', 'path' => '/blog/blog1.html']
        ];

        foreach ($files as $file) {
            $this->feature->collectCategoryFiles($this->container, [
            'metadata' => [
            'title' => $file['title'],
            'category' => $file['category']
            ],
            'output_path' => $this->tempDir . $file['path'],
            'file_path' => $this->tempDir . $file['path'],
            'rendered_content' => '<p>Test content</p>'
            ]);
        }

        $this->feature->processDeferredCategoryFiles($this->container, []);

      // Both category indexes should exist
        $this->assertFileExists($this->tempDir . '/technology/index.html');
        $this->assertFileExists($this->tempDir . '/blog/index.html');

      // Each should contain only its files
        $techContent = file_get_contents($this->tempDir . '/technology/index.html');
        $this->assertStringContainsString('Tech 1', $techContent);
        $this->assertStringNotContainsString('Blog 1', $techContent);

        $blogContent = file_get_contents($this->tempDir . '/blog/index.html');
        $this->assertStringContainsString('Blog 1', $blogContent);
        $this->assertStringNotContainsString('Tech 1', $blogContent);
    }

    public function testIncludesPaginationData(): void
    {
      // Set up deferred category file
        $this->setDeferredCategoryFiles([
        [
        'file_path' => $this->tempDir . '/test.md',
        'metadata' => ['title' => 'Test'],
        'output_path' => $this->tempDir . '/test/index.html'
        ]
        ]);

      // Add 15 files to trigger pagination (perPage is 5)
        for ($i = 1; $i <= 15; $i++) {
            $this->feature->collectCategoryFiles($this->container, [
            'metadata' => [
            'title' => "File {$i}",
            'category' => 'Test'
            ],
            'output_path' => $this->tempDir . "/test/file{$i}.html",
            'file_path' => $this->tempDir . "/test/file{$i}.html",
            'rendered_content' => '<p>Content</p>'
            ]);
        }

        $this->feature->processDeferredCategoryFiles($this->container, []);

        $indexPath = $this->tempDir . '/test/index.html';
        $this->assertFileExists($indexPath);

        $content = file_get_contents($indexPath);

      // Should include pagination configuration
        $this->assertStringContainsString('data-per-page="5"', $content);

      // Should include pagination JavaScript
        $this->assertStringContainsString('showPage', $content);
        $this->assertStringContainsString('updatePagination', $content);
    }

    public function testSkipsGenerationWhenNoCategories(): void
    {
      // Don't collect any files
        $result = $this->feature->processDeferredCategoryFiles($this->container, []);

      // Should return parameters unchanged
        $this->assertEquals([], $result);

      // Should not create any index files
        $files = glob($this->tempDir . '/*/index.html');
        $this->assertEmpty($files);
    }

    private function setDeferredCategoryFiles(array $files): void
    {
        $reflection = new \ReflectionClass($this->feature);
        $serviceProp = $reflection->getProperty('pageService');
        $serviceProp->setAccessible(true);
        $service = $serviceProp->getValue($this->feature);

        $serviceReflection = new \ReflectionClass($service);
        $deferredProp = $serviceReflection->getProperty('deferredFiles');
        $deferredProp->setAccessible(true);
        $deferredProp->setValue($service, $files);
    }
}
