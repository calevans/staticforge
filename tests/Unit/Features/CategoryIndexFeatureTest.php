<?php

namespace EICC\StaticForge\Tests\Unit\Features;

use EICC\StaticForge\Features\CategoryIndex\Feature;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;
use PHPUnit\Framework\TestCase;

class CategoryIndexFeatureTest extends TestCase
{
  private Feature $feature;
  private Container $container;
  private EventManager $eventManager;
  private string $tempDir;

  protected function setUp(): void
  {
    $this->container = new Container();

    // Create temp directory for tests
    $this->tempDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
    mkdir($this->tempDir, 0755, true);

    // Setup container
    $this->container->setVariable('OUTPUT_DIR', $this->tempDir);
    $this->container->setVariable('SITE_NAME', 'Test Site');
    $this->container->setVariable('SITE_BASE_URL', 'https://example.com/');
    $this->container->setVariable('CATEGORY_PAGINATION', 5);
    $this->container->setVariable('TEMPLATE_DIR', 'templates');
    $this->container->setVariable('TEMPLATE', 'terminal');

    // Setup logger
    $logger = new Log('test', $this->tempDir . '/test.log', 'INFO');
    $this->container->setVariable('logger', $logger);

    $this->eventManager = new EventManager($this->container);

    $this->feature = new Feature();
    $this->feature->register($this->eventManager, $this->container);
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
    // Collect some files
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
        'output_path' => $this->tempDir . $file['path']
      ]);
    }

    // Generate indexes
    $this->feature->generateCategoryIndexes($this->container, []);

    // Check that index file was created
    $indexPath = $this->tempDir . '/tech/index.html';
    $this->assertFileExists($indexPath);

    // Verify content
    $content = file_get_contents($indexPath);
    $this->assertStringContainsString('File 1', $content);
    $this->assertStringContainsString('File 2', $content);
    $this->assertStringContainsString('File 3', $content);
    $this->assertStringContainsString('Tech', $content);
  }

  public function testHandlesMultipleCategories(): void
  {
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
        'output_path' => $this->tempDir . $file['path']
      ]);
    }

    $this->feature->generateCategoryIndexes($this->container, []);

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
    // Add 15 files to trigger pagination (perPage is 5)
    for ($i = 1; $i <= 15; $i++) {
      $this->feature->collectCategoryFiles($this->container, [
        'metadata' => [
          'title' => "File {$i}",
          'category' => 'Test'
        ],
        'output_path' => $this->tempDir . "/test/file{$i}.html"
      ]);
    }

    $this->feature->generateCategoryIndexes($this->container, []);

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
    $result = $this->feature->generateCategoryIndexes($this->container, []);

    // Should return parameters unchanged
    $this->assertEquals([], $result);

    // Should not create any index files
    $files = glob($this->tempDir . '/*/index.html');
    $this->assertEmpty($files);
  }
}
