<?php

namespace EICC\StaticForge\Tests\Unit\Features;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\Tags\Feature;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureFactory;
use EICC\Utils\Container;
use EICC\Utils\Log;
use org\bovigo\vfs\vfsStream;

class TagsFeatureTest extends UnitTestCase
{
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventManager = new EventManager();
    }

    public function testFeatureRegistration(): void
    {
        $feature = (new FeatureFactory($this->container))->make(Feature::class);
        $this->assertInstanceOf(Feature::class, $feature);
        $feature->register($this->eventManager);

        $this->assertEquals('Tags', $feature->getName());

        // Check event listeners are registered
        $listeners = $this->eventManager->getListeners('POST_GLOB');
        $this->assertNotEmpty($listeners);

        $listeners = $this->eventManager->getListeners('PRE_RENDER');
        $this->assertNotEmpty($listeners);
    }

    public function testDelegatesToService(): void
    {
        // This test ensures that the feature class correctly delegates to the service
        // We'll do a basic functional test to verify the integration

        $root = vfsStream::setup('test');
        $file = vfsStream::newFile('test.md')->at($root)->setContent('');

        $this->setContainerVariable('discovered_files', [
            ['path' => $file->url(), 'url' => 'test.md', 'metadata' => ['tags' => ['php']]]
        ]);

        $feature = (new FeatureFactory($this->container))->make(Feature::class);
        $this->assertInstanceOf(Feature::class, $feature);
        $feature->register($this->eventManager);

        // Test POST_GLOB delegation - collects tags from discovered_files
        $feature->handlePostGlob(new Event('POST_GLOB'));

        // Test PRE_RENDER delegation - the collected tags should now surface
        // in tag_data, proving POST_GLOB's collection actually happened.
        $event = new RenderEvent(
            name: 'PRE_RENDER',
            filePath: $file->url(),
            fileUrl: '',
            metadata: ['tags' => ['php']],
        );

        $feature->handlePreRender($event);
        $this->assertArrayHasKey('tag_data', $event->extra);
        $this->assertContains('php', $event->extra['tag_data']['tags']);
        $this->assertContains('php', $event->extra['tag_data']['all_tags']);
    }
}
