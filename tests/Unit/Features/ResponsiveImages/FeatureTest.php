<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\ResponsiveImages;

use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\ResponsiveImages\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class FeatureTest extends UnitTestCase
{
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventManager = new EventManager($this->container);
    }

    public function testDisabledByDefaultDoesNotRegisterListener(): void
    {
        $this->setContainerVariable('site_config', []);

        $feature = new Feature();
        $feature->setContainer($this->container);
        $feature->register($this->eventManager);

        $listeners = $this->eventManager->getListeners('POST_RENDER');
        $this->assertEmpty($listeners);
    }

    public function testExplicitlyEnabledRegistersListener(): void
    {
        $this->setContainerVariable('site_config', ['responsive_images' => ['enabled' => true]]);

        $feature = new Feature();
        $feature->setContainer($this->container);
        $feature->register($this->eventManager);

        $listeners = $this->eventManager->getListeners('POST_RENDER');
        $this->assertCount(1, $listeners);
        $this->assertEquals([$feature, 'handlePostRender'], $listeners[0]['callback']);
        $this->assertSame(150, $listeners[0]['priority']);
    }

    public function testHandlePostRenderNoOpsWhenServiceNotInitialized(): void
    {
        $this->setContainerVariable('site_config', []);

        $feature = new Feature();
        $feature->setContainer($this->container);
        $feature->register($this->eventManager);

        $parameters = ['rendered_content' => '<p><img src="/x.jpg"></p>'];
        $result = $feature->handlePostRender($this->container, $parameters);

        $this->assertSame($parameters, $result);
    }

    public function testGetRequiredConfigAndEnvAreEmpty(): void
    {
        $feature = new Feature();

        $this->assertSame([], $feature->getRequiredConfig());
        $this->assertSame([], $feature->getRequiredEnv());
    }
}
