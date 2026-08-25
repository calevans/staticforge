<?php

namespace EICC\StaticForge\Tests\Unit\Features\TableOfContents;

use EICC\StaticForge\Features\TableOfContents\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureFactory;

class FeatureTest extends UnitTestCase
{
    private Feature $feature;
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventManager = new EventManager($this->container);
        $feature = (new FeatureFactory($this->container))->make(Feature::class);
        $this->assertInstanceOf(Feature::class, $feature);
        $this->feature = $feature;
        $this->feature->register($this->eventManager);
    }

    public function testRegisterRegistersEvent(): void
    {
        $listeners = $this->eventManager->getListeners('MARKDOWN_CONVERTED');
        $this->assertNotEmpty($listeners);
        $this->assertCount(1, $listeners);
        $this->assertEquals([$this->feature, 'handleMarkdownConverted'], $listeners[0]['callback']);
    }
}
