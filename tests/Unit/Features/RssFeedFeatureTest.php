<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features;

use EICC\StaticForge\Features\RssFeed\Feature;
use EICC\StaticForge\Core\EventManager;
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
        $this->eventManager = new EventManager($this->container);
        $this->feature = new Feature();
        $this->feature->register($this->eventManager, $this->container);
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
        $parameters = [
            'metadata' => [
                'title' => 'Test Article',
                'category' => 'Technology',
                'description' => 'A test article'
            ],
            'output_path' => $root->url() . '/technology/test.html',
            'file_path' => $root->url() . '/content/test.md',
            'rendered_content' => '<p>Content</p>'
        ];

        // This should not throw an exception
        $this->feature->handlePostRender($this->container, $parameters);

        // Test POST_LOOP delegation (handlePostLoop)
        // This should not throw an exception
        $this->feature->handlePostLoop($this->container, []);
        
        $this->assertTrue(true); // If we got here, no exceptions were thrown
    }
}
