<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\Audit\ConfigCommand;
use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\FeatureManager;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ConfigCommandTest extends UnitTestCase
{
    private string $testDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = (string)getcwd();
        $this->testDir = sys_get_temp_dir() . '/staticforge_config_test_' . uniqid();
        mkdir($this->testDir);
        mkdir($this->testDir . '/content');
        mkdir($this->testDir . '/templates');
        touch($this->testDir . '/.env');
        chdir($this->testDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    private function makeCommandTester(): CommandTester
    {
        $application = new Application();
        $application->addCommand(new ConfigCommand($this->container));
        $command = $application->find('audit:config');

        return new CommandTester($command);
    }

    /**
     * Injects a feature directly into the FeatureManager's internal registry,
     * bypassing filesystem discovery (loadFeatures() scans real directories).
     */
    private function injectFeature(FeatureInterface $feature): void
    {
        $featureManager = $this->container->get(FeatureManager::class);
        $this->assertInstanceOf(FeatureManager::class, $featureManager);

        $reflection = new \ReflectionClass($featureManager);
        $property = $reflection->getProperty('features');
        $property->setValue($featureManager, [$feature->getName() => $feature]);
    }

    public function testPassesWhenConfigurationIsComplete(): void
    {
        $this->setContainerVariable('TEMPLATE', 'sample');
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);

        $featureManager = $this->container->get(FeatureManager::class);
        $this->assertInstanceOf(FeatureManager::class, $featureManager);

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Audit passed', $tester->getDisplay());
    }

    public function testFailsWhenTemplateIsMissing(): void
    {
        $this->setContainerVariable('TEMPLATE', '');
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString("Missing 'site.template'", $display);
    }

    public function testFailsWhenSiteNameIsMissing(): void
    {
        $this->setContainerVariable('TEMPLATE', 'sample');
        $this->setContainerVariable('site_config', []);

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString("Missing 'site.name'", $display);
    }

    public function testFailsWhenContentDirectoryMissing(): void
    {
        $this->setContainerVariable('TEMPLATE', 'sample');
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);

        rmdir($this->testDir . '/content');

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Content Directory not found', $display);
    }

    public function testFailsWhenEnvFileMissing(): void
    {
        $this->setContainerVariable('TEMPLATE', 'sample');
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);

        unlink($this->testDir . '/.env');

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('.env file not found', $display);
    }

    public function testHasConfigKeySupportsDotNotation(): void
    {
        $command = new ConfigCommand($this->container);
        $reflection = new \ReflectionMethod($command, 'hasConfigKey');

        $config = ['forms' => ['contact' => ['provider_url' => 'https://example.com']]];

        $this->assertTrue($reflection->invoke($command, $config, 'forms.contact.provider_url'));
        $this->assertFalse($reflection->invoke($command, $config, 'forms.contact.missing_key'));
        $this->assertFalse($reflection->invoke($command, $config, 'forms.nonexistent.key'));
    }

    public function testShowsExampleConfigurationWhenFeatureSuppliesHelp(): void
    {
        $this->setContainerVariable('TEMPLATE', 'sample');
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);

        $this->injectFeature(new ConfigHelpFeature());

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Example Configuration:', $display);
        $this->assertStringContainsString('example_setting: value', $display);
    }

    public function testOmitsExampleConfigurationWhenFeatureReturnsNull(): void
    {
        $this->setContainerVariable('TEMPLATE', 'sample');
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);

        $this->injectFeature(new NoConfigHelpFeature());

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringNotContainsString('Example Configuration:', $display);
    }
}

class ConfigHelpFeature extends BaseFeature implements FeatureInterface, ConfigurableFeatureInterface
{
    protected string $name = 'ConfigHelpFeature';

    public function getRequiredConfig(): array
    {
        return ['some_required_key'];
    }

    public function getRequiredEnv(): array
    {
        return [];
    }

    public function getConfigHelp(string $key): ?string
    {
        return "example_setting: value";
    }
}

class NoConfigHelpFeature extends BaseFeature implements FeatureInterface, ConfigurableFeatureInterface
{
    protected string $name = 'NoConfigHelpFeature';

    public function getRequiredConfig(): array
    {
        return ['some_other_required_key'];
    }

    public function getRequiredEnv(): array
    {
        return [];
    }
}
