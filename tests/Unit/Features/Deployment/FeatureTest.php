<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Deployment;

use EICC\StaticForge\Features\Deployment\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;

class FeatureTest extends UnitTestCase
{
    private Feature $feature;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feature = new Feature();
        $this->feature->setContainer($this->container);
    }

    public function testGetRequiredConfigReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->feature->getRequiredConfig());
    }

    public function testGetRequiredEnvReturnsUploadUrl(): void
    {
        $this->assertSame(['UPLOAD_URL'], $this->feature->getRequiredEnv());
    }

    public function testRegisterCommandsAddsUploadSiteCommand(): void
    {
        $application = new Application();
        $parameters = ['application' => $application];

        $result = $this->feature->registerCommands($this->container, $parameters);

        $this->assertSame($parameters, $result);
        $this->assertTrue($application->has('site:upload'));
    }
}
