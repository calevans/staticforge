<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features;

use EICC\StaticForge\Features\RssFeed\Feature;
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureFactory;
use EICC\Utils\Container;
use EICC\Utils\Log;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use org\bovigo\vfs\vfsStream;

class RssFeedFeatureTest extends UnitTestCase
{
    private Feature $feature;
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventManager = new EventManager();
        $feature = (new FeatureFactory($this->container))->make(Feature::class);
        $this->assertInstanceOf(Feature::class, $feature);
        $this->feature = $feature;
        $this->feature->register($this->eventManager);
    }

    public function testFeatureRegistration(): void
    {
        $this->assertInstanceOf(Feature::class, $this->feature);
        $this->assertEquals('RssFeed', $this->feature->getName());

        // Check event listeners are registered
        $listeners = $this->eventManager->getListeners('POST_RENDER');
        $this->assertNotEmpty($listeners);

        $listeners = $this->eventManager->getListeners('POST_LOOP');
        $this->assertNotEmpty($listeners);
    }

    public function testDelegatesToService(): void
    {
        // Basic functional test to verify delegation
        $root = vfsStream::setup('test');
        $this->setContainerVariable('OUTPUT_DIR', $root->url());
        $this->setContainerVariable('SITE_NAME', 'Test Site');
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com/');

        // Test POST_RENDER delegation (handlePostRender)
        $event = new RenderEvent(
            name: 'POST_RENDER',
            filePath: $root->url() . '/content/test.md',
            fileUrl: '',
            metadata: [
                'title' => 'Test Article',
                'category' => 'Technology',
                'description' => 'A test article'
            ],
            renderedContent: '<p>Content</p>',
            outputPath: $root->url() . '/technology/test.html',
        );

        // This should not throw an exception
        $this->feature->handlePostRender($event);

        // Test POST_LOOP delegation (handlePostLoop) - should not throw
        $this->feature->handlePostLoop(new Event('POST_LOOP'));
    }
}
