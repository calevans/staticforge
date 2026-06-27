<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\FeatureTools;

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

        $feature = new Feature();
        $feature->setContainer($this->container);

        $application = new Application();
        $parameters = ['application' => $application];

        $result = $feature->registerCommands($this->container, $parameters);

        $this->assertSame($parameters, $result);
        $this->assertTrue($application->has('feature:create'));
        $this->assertTrue($application->has('feature:setup'));
        $this->assertTrue($application->has('feature:list'));
    }
}
