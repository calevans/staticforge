<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Deployment;

use EICC\StaticForge\Core\Events\ConsoleInitEvent;
use EICC\StaticForge\Core\FeatureFactory;
use EICC\StaticForge\Features\Deployment\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;

class FeatureTest extends UnitTestCase
{
    private Feature $feature;

    protected function setUp(): void
    {
        parent::setUp();
        $feature = (new FeatureFactory($this->container))->make(Feature::class);
        $this->assertInstanceOf(Feature::class, $feature);
        $this->feature = $feature;
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
        $event = new ConsoleInitEvent('CONSOLE_INIT', $application);

        $this->feature->registerCommands($event);

        $this->assertTrue($application->has('site:upload'));
    }
}
