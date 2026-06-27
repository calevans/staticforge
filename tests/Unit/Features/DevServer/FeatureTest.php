<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer;

use EICC\StaticForge\Features\DevServer\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;

class FeatureTest extends UnitTestCase
{
    public function testRegisterCommandsAddsDevServerCommand(): void
    {
        $feature = new Feature();
        $feature->setContainer($this->container);

        $application = new Application();
        $parameters = ['application' => $application];

        $result = $feature->registerCommands($this->container, $parameters);

        $this->assertSame($parameters, $result);
        $this->assertTrue($application->has('site:devserver'));
    }
}
