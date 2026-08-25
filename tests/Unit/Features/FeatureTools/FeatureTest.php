<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\FeatureTools;

use EICC\StaticForge\Core\Events\ConsoleInitEvent;
use EICC\StaticForge\Core\FeatureFactory;
use EICC\StaticForge\Core\FeatureManager;
use EICC\StaticForge\Features\FeatureTools\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;

class FeatureTest extends UnitTestCase
{
    public function testRegisterCommandsAddsAllThreeCommands(): void
    {
        $featureManager = $this->createMock(FeatureManager::class);
        $this->addToContainer(FeatureManager::class, $featureManager);

        $feature = (new FeatureFactory($this->container))->make(Feature::class);
        $this->assertInstanceOf(Feature::class, $feature);

        $application = new Application();
        $event = new ConsoleInitEvent('CONSOLE_INIT', $application);

        $feature->registerCommands($event);

        $this->assertTrue($application->has('feature:create'));
        $this->assertTrue($application->has('feature:setup'));
        $this->assertTrue($application->has('feature:list'));
    }
}
