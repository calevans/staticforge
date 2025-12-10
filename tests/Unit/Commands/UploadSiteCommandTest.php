<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Features\Deployment\Commands\UploadSiteCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class UploadSiteCommandTest extends UnitTestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new UploadSiteCommand($this->container);

        $this->assertEquals('site:upload', $command->getName());
        $this->assertStringContainsString('Upload generated static site', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('input'));
        $this->assertTrue($command->getDefinition()->hasOption('test'));
        $this->assertTrue($command->getDefinition()->hasOption('url'));
    }
}
