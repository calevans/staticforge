<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\CacheBuster;

use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\CacheBuster\Feature;
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
        $listeners = $this->eventManager->getListeners('CREATE');
        $this->assertNotEmpty($listeners);
        $this->assertCount(1, $listeners);
        $this->assertEquals([$this->feature, 'handleCreate'], $listeners[0]['callback']);
    }

    public function testHandleCreateSetsBuildIdAndCacheBuster(): void
    {
        $result = $this->feature->handleCreate($this->container, []);

        $this->assertSame([], $result);
        $this->assertTrue($this->container->hasVariable('build_id'));
        $this->assertTrue($this->container->hasVariable('cache_buster'));

        $buildId = $this->container->getVariable('build_id');
        $cacheBuster = $this->container->getVariable('cache_buster');

        $this->assertTrue(is_numeric($buildId));
        $this->assertSame("sfcb={$buildId}", $cacheBuster);
    }

    public function testHandleCreateReturnsParametersUnchangedOtherwise(): void
    {
        $parameters = ['foo' => 'bar'];
        $result = $this->feature->handleCreate($this->container, $parameters);

        $this->assertSame(['foo' => 'bar'], $result);
    }
}
