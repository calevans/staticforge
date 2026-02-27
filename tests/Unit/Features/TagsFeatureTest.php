<?php

namespace EICC\StaticForge\Tests\Unit\Features;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Features\Tags\Feature;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;
use org\bovigo\vfs\vfsStream;

class TagsFeatureTest extends UnitTestCase
{
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventManager = new EventManager($this->container);
    }

    public function testFeatureRegistration(): void
    {
        $feature = new Feature();
        $feature->setContainer($this->container);
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

        $feature = new Feature();
        $feature->setContainer($this->container);
        $feature->register($this->eventManager);

        // Test POST_GLOB delegation
        $parameters = ['features' => []];
        $result = $feature->handlePostGlob($this->container, $parameters);

        $this->assertArrayHasKey('Tags', $result['features']);
        $this->assertContains('php', $result['features']['Tags']['all_tags']);

        // Test PRE_RENDER delegation
        $parameters = [
            'file_path' => $file->url(),
            'metadata' => ['tags' => ['php']]
        ];

        $result = $feature->handlePreRender($this->container, $parameters);
        $this->assertArrayHasKey('tag_data', $result);
        $this->assertContains('php', $result['tag_data']['tags']);
    }
}
