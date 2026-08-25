<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\SiteBuilder;

use EICC\StaticForge\Core\Events\ConsoleInitEvent;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureFactory;
use EICC\StaticForge\Features\SiteBuilder\Commands\RenderSiteCommand;
use EICC\StaticForge\Features\SiteBuilder\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;

/**
 * @covers \EICC\StaticForge\Features\SiteBuilder\Feature
 */
class FeatureTest extends UnitTestCase
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

    public function testRegisterAddsConsoleInitListener(): void
    {
        $listeners = $this->eventManager->getListeners('CONSOLE_INIT');

        $this->assertNotEmpty($listeners);
    }

    public function testRegisterCommandsAddsRenderSiteCommandToApplication(): void
    {
        $application = new Application();
        $event = new ConsoleInitEvent('CONSOLE_INIT', $application);

        $this->feature->registerCommands($event);

        $this->assertTrue($application->has('site:render'));
        $this->assertInstanceOf(RenderSiteCommand::class, $application->find('site:render'));
    }

    public function testHandleConsoleInitEventDispatchesToRegisterCommands(): void
    {
        $application = new Application();
        $event = new ConsoleInitEvent('CONSOLE_INIT', $application);

        $result = $this->eventManager->fire('CONSOLE_INIT', $event);

        $this->assertTrue($application->has('site:render'));
        $this->assertSame($event, $result);
    }

    public function testGetNameReturnsSiteBuilder(): void
    {
        $this->assertEquals('SiteBuilder', $this->feature->getName());
    }
}
