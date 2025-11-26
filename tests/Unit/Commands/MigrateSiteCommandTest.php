<?php

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\MigrateSiteCommand;
use EICC\Utils\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class MigrateSiteCommandTest extends TestCase
{
    private string $testDir;
    private Container $container;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/staticforge_migrate_test_' . uniqid();
        mkdir($this->testDir);

        $this->container = new Container();
        $this->container->setVariable('SOURCE_DIR', $this->testDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testExecuteMigratesIniToYaml(): void
    {
        $iniContent = <<<INI
<!-- INI
title = "Test Page"
description = "Test Description"
tags = [tag1, tag2]
-->
<h1>Content</h1>
INI;
        file_put_contents($this->testDir . '/test.html', $iniContent);

        $application = new Application();
        $application->add(new MigrateSiteCommand($this->container));
        $command = $application->find('site:migrate');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['--directory' => [$this->testDir]]);

        $migratedContent = file_get_contents($this->testDir . '/test.html');

        $this->assertStringContainsString('<!--', $migratedContent);
        $this->assertStringContainsString('---', $migratedContent);
        // YAML dumper might use single or double quotes depending on content
        $this->assertMatchesRegularExpression('/title:\s*[\'"]Test Page[\'"]/', $migratedContent);
        $this->assertStringContainsString('tags:', $migratedContent);
        $this->assertStringContainsString('- tag1', $migratedContent);
        $this->assertStringContainsString('-->', $migratedContent);
    }

    public function testExecuteMigratesMarkdownIniToYaml(): void
    {
        $iniContent = <<<MD
---
title = "Markdown Page"
category = "docs"
---
# Content
MD;
        file_put_contents($this->testDir . '/test.md', $iniContent);

        $application = new Application();
        $application->add(new MigrateSiteCommand($this->container));
        $command = $application->find('site:migrate');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['--directory' => [$this->testDir]]);

        $migratedContent = file_get_contents($this->testDir . '/test.md');

        $this->assertMatchesRegularExpression('/title:\s*[\'"]Markdown Page[\'"]/', $migratedContent);
        $this->assertStringContainsString('category: docs', $migratedContent);
    }

    public function testExecuteDryRunDoesNotModifyFiles(): void
    {
        $iniContent = <<<MD
---
title = "Dry Run"
---
Content
MD;
        file_put_contents($this->testDir . '/test.md', $iniContent);

        $application = new Application();
        $application->add(new MigrateSiteCommand($this->container));
        $command = $application->find('site:migrate');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--directory' => [$this->testDir],
            '--dry-run' => true
        ]);

        $this->assertEquals($iniContent, file_get_contents($this->testDir . '/test.md'));
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('DRY RUN MODE', $output);
    }

    public function testExecuteCreatesBackupByDefault(): void
    {
        $iniContent = <<<MD
---
title = "Backup Test"
---
Content
MD;
        file_put_contents($this->testDir . '/test.md', $iniContent);

        $application = new Application();
        $application->add(new MigrateSiteCommand($this->container));
        $command = $application->find('site:migrate');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['--directory' => [$this->testDir]]);

        $this->assertFileExists($this->testDir . '/test.md.backup');
        $this->assertEquals($iniContent, file_get_contents($this->testDir . '/test.md.backup'));
    }

    public function testExecuteSkipsAlreadyYaml(): void
    {
        $yamlContent = <<<MD
---
title: "Already YAML"
---
Content
MD;
        file_put_contents($this->testDir . '/test.md', $yamlContent);

        $application = new Application();
        $application->add(new MigrateSiteCommand($this->container));
        $command = $application->find('site:migrate');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['--directory' => [$this->testDir]]);

        $this->assertEquals($yamlContent, file_get_contents($this->testDir . '/test.md'));
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Already YAML', $output);
    }
}
