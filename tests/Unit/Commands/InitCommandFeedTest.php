<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\InitCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 * site:init turns feeds on for NEW sites only; existing sites must opt in themselves.
 */
class InitCommandFeedTest extends UnitTestCase
{
    private string $testDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = (string) getcwd();
        $this->testDir = sys_get_temp_dir() . '/staticforge_init_feed_test_' . uniqid();
        mkdir($this->testDir);
        chdir($this->testDir);
        file_put_contents($this->testDir . '/.env.example', 'SITE_NAME=Test');
        copy($this->originalCwd . '/siteconfig.yaml.example', $this->testDir . '/siteconfig.yaml.example');
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    private function init(bool $force = false): void
    {
        $application = new Application();
        $application->addCommand(new InitCommand());
        (new CommandTester($application->find('site:init')))->execute($force ? ['--force' => true] : []);
    }

    /**
     * @return array<string, mixed>
     */
    private function parsedConfig(): array
    {
        $parsed = Yaml::parseFile($this->testDir . '/siteconfig.yaml');
        $this->assertIsArray($parsed);

        return $parsed;
    }

    public function testNewSiteGetsTheEnabledFeedBlockAndItParsesAsYaml(): void
    {
        $this->init();

        $feed = $this->parsedConfig()['feed'] ?? null;
        $this->assertSame([
            'enabled' => true,
            'limit' => 20,
            'formats' => ['rss', 'atom', 'json'],
            'exclude_categories' => [],
            'category_formats' => ['rss'],
        ], $feed);
    }

    public function testExistingSiteConfigIsLeftAloneWithoutForce(): void
    {
        file_put_contents($this->testDir . '/siteconfig.yaml', "site:\n  name: Mine\n");

        $this->init();

        $this->assertSame("site:\n  name: Mine\n", file_get_contents($this->testDir . '/siteconfig.yaml'));
        $this->assertArrayNotHasKey('feed', $this->parsedConfig());
    }

    public function testForceRecreatesTheConfigWithFeedsEnabledExactlyOnce(): void
    {
        file_put_contents($this->testDir . '/siteconfig.yaml', "site:\n  name: Mine\n");

        $this->init(true);

        $content = (string) file_get_contents($this->testDir . '/siteconfig.yaml');
        $this->assertSame(1, preg_match_all('/^feed:/m', $content), 'a duplicate top-level key would be a YAML error');
        $this->assertTrue($this->parsedConfig()['feed']['enabled']);
    }

    public function testTheShippedExampleFileAloneDoesNotEnableFeeds(): void
    {
        $parsed = Yaml::parseFile($this->testDir . '/siteconfig.yaml.example');
        $this->assertIsArray($parsed);

        $this->assertArrayNotHasKey('feed', $parsed, 'the example stays commented so copying it by hand keeps feeds off');
    }
}
