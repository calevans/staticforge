<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer;

use EICC\StaticForge\Core\Events\ConsoleInitEvent;
use EICC\StaticForge\Features\DevServer\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;

class FeatureTest extends UnitTestCase
{
    public function testRegisterCommandsAddsDevServerCommand(): void
    {
        $feature = new Feature();

        $application = new Application();
        $event = new ConsoleInitEvent('CONSOLE_INIT', $application);

        $feature->registerCommands($event);

        $this->assertTrue($application->has('site:devserver'));
    }
}
