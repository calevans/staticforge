<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\SiteBuilder;

use EICC\StaticForge\Core\EventManager;
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

        $this->eventManager = new EventManager($this->container);
        $this->feature = new Feature();
        $this->feature->setContainer($this->container);
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
        $parameters = ['application' => $application];

        $result = $this->feature->registerCommands($this->container, $parameters);

        $this->assertSame($parameters, $result);
        $this->assertTrue($application->has('site:render'));
        $this->assertInstanceOf(RenderSiteCommand::class, $application->find('site:render'));
    }

    public function testHandleConsoleInitEventDispatchesToRegisterCommands(): void
    {
        $application = new Application();

        $result = $this->eventManager->fire('CONSOLE_INIT', ['application' => $application]);

        $this->assertTrue($application->has('site:render'));
        $this->assertArrayHasKey('application', $result);
    }

    public function testGetNameReturnsSiteBuilder(): void
    {
        $this->assertEquals('SiteBuilder', $this->feature->getName());
    }
}
