<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\FeatureTools\Commands;

use EICC\StaticForge\Features\FeatureTools\Commands\FeatureCreateCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class FeatureCreateCommandTest extends UnitTestCase
{
    private string $tempCwd;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = (string) getcwd();
        $this->tempCwd = sys_get_temp_dir() . '/staticforge_feature_create_test_' . uniqid();
        mkdir($this->tempCwd . '/src/Features', 0755, true);
        chdir($this->tempCwd);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeDirectory($this->tempCwd);
        parent::tearDown();
    }

    public function testExecuteFailsWithInvalidFeatureName(): void
    {
        $application = new Application();
        $application->add(new FeatureCreateCommand());

        $command = $application->find('feature:create');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['name' => 'lowercase-invalid']);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Invalid feature name', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testExecuteFailsWhenFeatureAlreadyExists(): void
    {
        mkdir($this->tempCwd . '/src/Features/Existing', 0755, true);

        $application = new Application();
        $application->add(new FeatureCreateCommand());

        $command = $application->find('feature:create');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['name' => 'Existing']);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('already exists', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testExecuteSucceedsAndScaffoldsFeature(): void
    {
        $application = new Application();
        $application->add(new FeatureCreateCommand());

        $command = $application->find('feature:create');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['name' => 'Widgets']);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString("Feature 'Widgets' created successfully", $output);
        $this->assertEquals(0, $commandTester->getStatusCode());

        $this->assertFileExists($this->tempCwd . '/src/Features/Widgets/Feature.php');
        $this->assertFileExists($this->tempCwd . '/src/Features/Widgets/Services/WidgetsService.php');
    }
}
