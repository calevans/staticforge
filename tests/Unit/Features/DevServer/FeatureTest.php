<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer;

use EICC\StaticForge\Core\Events\ConsoleInitEvent;
use EICC\StaticForge\Core\FeatureFactory;
use EICC\StaticForge\Features\DevServer\Commands\DevServerCommand;
use EICC\StaticForge\Features\DevServer\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;

class FeatureTest extends UnitTestCase
{
    public function testRegisterCommandsAddsDevServerCommand(): void
    {
        $feature = (new FeatureFactory($this->container))->make(Feature::class);
        $this->assertInstanceOf(Feature::class, $feature);

        $application = new Application();
        $event = new ConsoleInitEvent('CONSOLE_INIT', $application);

        $feature->registerCommands($event);

        $this->assertTrue($application->has('site:devserver'));
        $this->assertInstanceOf(DevServerCommand::class, $application->find('site:devserver'));
    }
}
