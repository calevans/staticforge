<?php

namespace EICC\StaticForge\Tests\Unit\Features;

use EICC\StaticForge\Features\CacheBuster\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\EventManager;

class CacheBusterFeatureTest extends UnitTestCase
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
        $listeners = $this->eventManager->getListeners('CREATE');
        $this->assertNotEmpty($listeners);
        $this->assertCount(1, $listeners);
        $this->assertEquals([$this->feature, 'handleCreate'], $listeners[0]['callback']);
    }

    public function testHandleCreateSetsBuildId(): void
    {
        $parameters = [];
        $result = $this->feature->handleCreate($this->container, $parameters);

        $this->assertEquals($parameters, $result);

        $buildId = $this->container->getVariable('build_id');
        $this->assertNotNull($buildId);
        $this->assertIsString($buildId);
        $this->assertNotEmpty($buildId);

        // Should be a timestamp
        $this->assertTrue(is_numeric($buildId));
        $this->assertGreaterThan(0, (int)$buildId);

        $cacheBuster = $this->container->getVariable('cache_buster');
        $this->assertNotNull($cacheBuster);
        $this->assertIsString($cacheBuster);
        $this->assertEquals("sfcb={$buildId}", $cacheBuster);
    }
}
