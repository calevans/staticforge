<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Features\FeatureTools\Commands\FeatureCreateCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use ReflectionMethod;

class FeatureCreateCommandTest extends UnitTestCase
{
    public function testFeatureTemplateIncludesStrictTypesAndDocblocks(): void
    {
        $command = new FeatureCreateCommand();
        $method = new ReflectionMethod($command, 'getFeatureTemplate');
        $method->setAccessible(true);

        $content = $method->invoke($command, 'MyFeature');

        $this->assertStringContainsString('declare(strict_types=1);', $content);
        $this->assertStringContainsString('/**', $content);
        $this->assertStringContainsString('Feature entry point for MyFeature', $content);
    }

    public function testServiceTemplateIncludesStrictTypesAndDocblocks(): void
    {
        $command = new FeatureCreateCommand();
        $method = new ReflectionMethod($command, 'getServiceTemplate');
        $method->setAccessible(true);

        $content = $method->invoke($command, 'MyFeature');

        $this->assertStringContainsString('declare(strict_types=1);', $content);
        $this->assertStringContainsString('/**', $content);
        $this->assertStringContainsString('Service class for MyFeature feature logic', $content);
    }
}
