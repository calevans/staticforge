<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\InitCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class InitCommandTest extends UnitTestCase
{
    private string $testDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $cwd = getcwd();
        $this->assertNotFalse($cwd, 'Could not determine current working directory');
        $this->originalCwd = $cwd;
        $this->testDir = sys_get_temp_dir() . '/staticforge_init_test_' . uniqid();
        mkdir($this->testDir);
        chdir($this->testDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeDirectory($this->testDir);
    }

    // removeDirectory is now provided by UnitTestCase

    public function testExecuteCreatesDirectoryStructure(): void
    {
        // Create dummy example files needed for init
        file_put_contents($this->testDir . '/.env.example', 'SITE_NAME=Test');
        file_put_contents($this->testDir . '/siteconfig.yaml.example', 'site: name: Test');

        $application = new Application();
        $application->addCommand(new InitCommand());
        $command = $application->find('site:init');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $this->assertDirectoryExists($this->testDir . '/content');
        $this->assertDirectoryExists($this->testDir . '/templates');
        $this->assertDirectoryExists($this->testDir . '/public');
        $this->assertDirectoryExists($this->testDir . '/config');
        $this->assertDirectoryExists($this->testDir . '/logs');
    }

    public function testExecuteCreatesEnvFile(): void
    {
        // Create dummy example file
        file_put_contents($this->testDir . '/.env.example', 'SITE_NAME=TestEnv');

        $application = new Application();
        $application->addCommand(new InitCommand());
        $command = $application->find('site:init');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $this->assertFileExists($this->testDir . '/.env');
        $content = file_get_contents($this->testDir . '/.env');
        $this->assertNotFalse($content);
        $this->assertStringContainsString('SITE_NAME=TestEnv', $content);
    }

    public function testExecuteCreatesSampleContent(): void
    {
        // Create dummy example files
        file_put_contents($this->testDir . '/.env.example', 'SITE_NAME=Test');
        file_put_contents($this->testDir . '/siteconfig.yaml.example', 'site: name: Test');

        $application = new Application();
        $application->addCommand(new InitCommand());
        $command = $application->find('site:init');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $this->assertFileExists($this->testDir . '/content/index.md');
        $content = file_get_contents($this->testDir . '/content/index.md');
        $this->assertNotFalse($content);
        $this->assertStringContainsString('Welcome to StaticForge', $content);
    }

    public function testExecuteDoesNotOverwriteExistingFilesWithoutForce(): void
    {
        // Create dummy example files
        file_put_contents($this->testDir . '/.env.example', 'SITE_NAME=New');
        file_put_contents($this->testDir . '/siteconfig.yaml.example', 'site: name: New');

        // Create existing file
        file_put_contents($this->testDir . '/.env', 'EXISTING_CONTENT');

        $application = new Application();
        $application->addCommand(new InitCommand());
        $command = $application->find('site:init');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $this->assertEquals('EXISTING_CONTENT', file_get_contents($this->testDir . '/.env'));
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('.env file already exists', $output);
    }

    public function testExecuteOverwritesExistingFilesWithForce(): void
    {
        // Create dummy example files
        file_put_contents($this->testDir . '/.env.example', 'SITE_NAME=NewContent');
        file_put_contents($this->testDir . '/siteconfig.yaml.example', 'site: name: NewContent');

        // Create existing file
        file_put_contents($this->testDir . '/.env', 'OLD_CONTENT');

        $application = new Application();
        $application->addCommand(new InitCommand());
        $command = $application->find('site:init');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['--force' => true]);

        $this->assertFileExists($this->testDir . '/.env');
        $content = file_get_contents($this->testDir . '/.env');
        $this->assertNotFalse($content);
        $this->assertStringContainsString('SITE_NAME=NewContent', $content);
    }

    private function runInit(bool $force = false): CommandTester
    {
        file_put_contents($this->testDir . '/.env.example', 'SITE_NAME=Test');
        file_put_contents($this->testDir . '/siteconfig.yaml.example', 'site: name: Test');

        $application = new Application();
        $application->addCommand(new InitCommand());
        $tester = new CommandTester($application->find('site:init'));
        $tester->execute($force ? ['--force' => true] : []);

        return $tester;
    }

    public function testNewSiteIsSeededWithShipped404Content(): void
    {
        $this->runInit();

        $expected = <<<'MD'
---
title: 'Page not found'
description: 'The page you were looking for does not exist.'
template: 404
noindex: true
sitemap: false
search_index: false
no_llms: true
---

Sorry, we could not find that page.
MD;
        $this->assertSame($expected, file_get_contents($this->testDir . '/content/404.md'));
    }

    public function testExisting404ContentIsNotOverwrittenWithoutForce(): void
    {
        mkdir($this->testDir . '/content');
        file_put_contents($this->testDir . '/content/404.md', 'MY CUSTOM 404');

        $this->runInit();

        $this->assertSame('MY CUSTOM 404', file_get_contents($this->testDir . '/content/404.md'));
    }

    public function testExisting404ContentIsNotOverwrittenWhenIndexExistsWithoutForce(): void
    {
        mkdir($this->testDir . '/content');
        file_put_contents($this->testDir . '/content/index.md', 'MY INDEX');
        file_put_contents($this->testDir . '/content/404.md', 'MY CUSTOM 404');

        $this->runInit();

        $this->assertSame('MY INDEX', file_get_contents($this->testDir . '/content/index.md'));
        $this->assertSame('MY CUSTOM 404', file_get_contents($this->testDir . '/content/404.md'));
    }

    public function testExistingSiteWithIndexIsNotSeededWith404WithoutForce(): void
    {
        mkdir($this->testDir . '/content');
        file_put_contents($this->testDir . '/content/index.md', 'MY INDEX');

        $this->runInit();

        $this->assertFileDoesNotExist($this->testDir . '/content/404.md');
    }

    public function testForceOverwrites404Content(): void
    {
        mkdir($this->testDir . '/content');
        file_put_contents($this->testDir . '/content/404.md', 'MY CUSTOM 404');

        $this->runInit(true);

        $content = file_get_contents($this->testDir . '/content/404.md');
        $this->assertNotFalse($content);
        $this->assertStringContainsString('template: 404', $content);
        $this->assertStringNotContainsString('MY CUSTOM 404', $content);
    }
}
