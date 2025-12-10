<?php

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Features\FeatureTools\Commands\FeatureSetupCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class FeatureSetupCommandTest extends UnitTestCase
{
    private string $tempVendorDir;
    private string $tempRootDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temp directories
        $this->tempRootDir = sys_get_temp_dir() . '/staticforge_setup_test_' . uniqid();
        $this->tempVendorDir = $this->tempRootDir . '/vendor';

        mkdir($this->tempVendorDir, 0777, true);

        // Change CWD to temp root so the command works relative to it
        chdir($this->tempRootDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempRootDir);
        parent::tearDown();
    }

    public function testExecuteWithNonExistentPackage(): void
    {
        $application = new Application();
        $application->add(new FeatureSetupCommand());

        $command = $application->find('feature:setup');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'package' => 'vendor/nonexistent'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Package directory not found', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testExecuteWithNoExampleFiles(): void
    {
        // Create dummy package dir
        $packageDir = $this->tempVendorDir . '/vendor/empty-package';
        mkdir($packageDir, 0777, true);

        $application = new Application();
        $application->add(new FeatureSetupCommand());

        $command = $application->find('feature:setup');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'package' => 'vendor/empty-package'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No example configuration files', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testExecuteWithExampleFiles(): void
    {
        // Create dummy package dir with examples
        $packageDir = $this->tempVendorDir . '/vendor/full-package';
        mkdir($packageDir, 0777, true);

        file_put_contents($packageDir . '/.env.example', 'TEST_KEY=value');
        file_put_contents($packageDir . '/siteconfig.yaml.example', 'config: value');

        $application = new Application();
        $application->add(new FeatureSetupCommand());

        $command = $application->find('feature:setup');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'package' => 'vendor/full-package'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Copied:', $output);
        $this->assertStringContainsString('.env.example.full-package', $output);
        $this->assertStringContainsString('siteconfig.yaml.example.full-package', $output);

        // Verify files were copied to root
        $this->assertFileExists($this->tempRootDir . '/.env.example.full-package');
        $this->assertFileExists($this->tempRootDir . '/siteconfig.yaml.example.full-package');

        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}
