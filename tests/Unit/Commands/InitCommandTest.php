<?php

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\InitCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class InitCommandTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/staticforge_init_test_' . uniqid();
        mkdir($this->testDir);
        chdir($this->testDir);
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

    public function testExecuteCreatesDirectoryStructure(): void
    {
        // Create dummy example files needed for init
        file_put_contents($this->testDir . '/.env.example', 'SITE_NAME=Test');
        file_put_contents($this->testDir . '/siteconfig.yaml.example', 'site: name: Test');

        $application = new Application();
        $application->add(new InitCommand());
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
        $application->add(new InitCommand());
        $command = $application->find('site:init');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $this->assertFileExists($this->testDir . '/.env');
        $content = file_get_contents($this->testDir . '/.env');
        $this->assertStringContainsString('SITE_NAME=TestEnv', $content);
    }

    public function testExecuteCreatesSampleContent(): void
    {
        // Create dummy example files
        file_put_contents($this->testDir . '/.env.example', 'SITE_NAME=Test');
        file_put_contents($this->testDir . '/siteconfig.yaml.example', 'site: name: Test');

        $application = new Application();
        $application->add(new InitCommand());
        $command = $application->find('site:init');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $this->assertFileExists($this->testDir . '/content/index.md');
        $content = file_get_contents($this->testDir . '/content/index.md');
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
        $application->add(new InitCommand());
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
        $application->add(new InitCommand());
        $command = $application->find('site:init');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['--force' => true]);

        $this->assertFileExists($this->testDir . '/.env');
        $content = file_get_contents($this->testDir . '/.env');
        $this->assertStringContainsString('SITE_NAME=NewContent', $content);
    }

}

