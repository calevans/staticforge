<?php

namespace Tests\Unit\Features;

use PHPUnit\Framework\TestCase;
use EICC\StaticForge\Features\Categories\Feature as CategoriesFeature;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;

class CategoriesFeatureTest extends TestCase
{
  private CategoriesFeature $feature;
  private Container $container;
  private EventManager $eventManager;

  protected function setUp(): void
  {
    $this->container = new Container();
    $this->eventManager = new EventManager($this->container);

    // Set up mock logger
    $logger = $this->createMock(Log::class);
    $this->container->setVariable('logger', $logger);

    $this->feature = new CategoriesFeature();
    $this->feature->register($this->eventManager, $this->container);
  }

  public function testRegisterFeature(): void
  {
    $listeners = $this->eventManager->getListeners('POST_RENDER');
    $this->assertNotEmpty($listeners);
  }

  public function testHandlePostRenderWithCategory(): void
  {
    $parameters = [
      'output_path' => '/var/www/public/test.html',
      'metadata' => [
        'category' => 'Blog Posts'
      ]
    ];

    $result = $this->feature->handlePostRender($this->container, $parameters);

    $this->assertStringContainsString('blog-posts', $result['output_path']);
    $this->assertStringEndsWith('test.html', $result['output_path']);
  }

  public function testHandlePostRenderWithoutCategory(): void
  {
    $parameters = [
      'output_path' => '/var/www/public/test.html',
      'metadata' => []
    ];

    $result = $this->feature->handlePostRender($this->container, $parameters);

    $this->assertEquals('/var/www/public/test.html', $result['output_path']);
  }

  public function testHandlePostRenderWithoutMetadata(): void
  {
    $parameters = [
      'output_path' => '/var/www/public/test.html'
    ];

    $result = $this->feature->handlePostRender($this->container, $parameters);

    $this->assertEquals('/var/www/public/test.html', $result['output_path']);
  }

  public function testCategorySanitization(): void
  {
    $parameters = [
      'output_path' => '/var/www/public/test.html',
      'metadata' => [
        'category' => 'Blog & News!!'
      ]
    ];

    $result = $this->feature->handlePostRender($this->container, $parameters);

    $this->assertStringContainsString('blog-news', $result['output_path']);
  }

  public function testCategoryWithSpaces(): void
  {
    $parameters = [
      'output_path' => '/var/www/public/test.html',
      'metadata' => [
        'category' => 'Product Reviews'
      ]
    ];

    $result = $this->feature->handlePostRender($this->container, $parameters);

    $this->assertStringContainsString('product-reviews', $result['output_path']);
  }
}
