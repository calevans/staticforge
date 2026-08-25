<?php

namespace EICC\StaticForge\Tests\Unit\Features;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\Categories\Feature as CategoriesFeature;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureFactory;
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
        $this->eventManager = new EventManager();

      // Logger already registered by bootstrap

        $feature = (new FeatureFactory($this->container))->make(CategoriesFeature::class);
        $this->assertInstanceOf(CategoriesFeature::class, $feature);
        $this->feature = $feature;
        $this->feature->register($this->eventManager);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function makeEvent(string $outputPath, array $metadata = []): RenderEvent
    {
        return new RenderEvent(
            name: 'POST_RENDER',
            filePath: '',
            fileUrl: '',
            metadata: $metadata,
            outputPath: $outputPath,
        );
    }

    public function testRegisterFeature(): void
    {
        $listeners = $this->eventManager->getListeners('POST_RENDER');
        $this->assertNotEmpty($listeners);
    }

    public function testHandlePostRenderWithCategory(): void
    {
        $event = $this->makeEvent('/var/www/public/test.html', ['category' => 'Blog Posts']);

        $this->feature->handlePostRender($event);

        $this->assertNotNull($event->outputPath);
        $this->assertStringContainsString('blog-posts', $event->outputPath);
        $this->assertStringEndsWith('test.html', $event->outputPath);
    }

    public function testHandlePostRenderWithoutCategory(): void
    {
        $event = $this->makeEvent('/var/www/public/test.html');

        $this->feature->handlePostRender($event);

        $this->assertEquals('/var/www/public/test.html', $event->outputPath);
    }

    public function testHandlePostRenderWithoutMetadata(): void
    {
        $event = $this->makeEvent('/var/www/public/test.html');

        $this->feature->handlePostRender($event);

        $this->assertEquals('/var/www/public/test.html', $event->outputPath);
    }

    public function testCategorySanitization(): void
    {
        $event = $this->makeEvent('/var/www/public/test.html', ['category' => 'Blog & News!!']);

        $this->feature->handlePostRender($event);

        $this->assertNotNull($event->outputPath);
        $this->assertStringContainsString('blog-news', $event->outputPath);
    }

    public function testCategoryWithSpaces(): void
    {
        $event = $this->makeEvent('/var/www/public/test.html', ['category' => 'Product Reviews']);

        $this->feature->handlePostRender($event);

        $this->assertNotNull($event->outputPath);
        $this->assertStringContainsString('product-reviews', $event->outputPath);
    }
}
