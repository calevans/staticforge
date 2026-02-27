<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Sitemap;

use EICC\StaticForge\Features\Sitemap\Feature;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class FeatureTest extends UnitTestCase
{
    private Feature $feature;
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventManager = new EventManager($this->container);
        $this->feature = new Feature();
        $this->feature->setContainer($this->container);
        $this->feature->register($this->eventManager);
    }

    public function testRegisterRegistersEvent(): void
    {
        $listeners = $this->eventManager->getListeners('POST_RENDER');
        $this->assertNotEmpty($listeners);
        // The callback is now [$this->feature, 'handlePostRender']
        $this->assertEquals([$this->feature, 'handlePostRender'], $listeners[0]['callback']);

        $listeners = $this->eventManager->getListeners('POST_LOOP');
        $this->assertNotEmpty($listeners);
        // The callback is now [$this->feature, 'handlePostLoop']
        $this->assertEquals([$this->feature, 'handlePostLoop'], $listeners[0]['callback']);
    }

    public function testFeatureRegistration(): void
    {
        $this->assertInstanceOf(Feature::class, $this->feature);
        $this->assertEquals('Sitemap', $this->feature->getName());
    }
}
