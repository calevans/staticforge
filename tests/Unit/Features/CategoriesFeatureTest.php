<?php

namespace Tests\Unit\Features;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Features\Categories\Feature as CategoriesFeature;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;

class CategoriesFeatureTest extends UnitTestCase
{
  private CategoriesFeature $feature;

  private EventManager $eventManager;

  protected function setUp(): void
  {
    parent::setUp();
    // Use bootstrapped container from parent::setUp()
    $this->eventManager = new EventManager($this->container);

    // Logger already registered by bootstrap

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
