<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\Make\HtaccessCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class HtaccessCommandTest extends UnitTestCase
{
    private string $testDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = (string)getcwd();
        $this->testDir = sys_get_temp_dir() . '/staticforge_htaccess_test_' . uniqid();
        mkdir($this->testDir);
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
        $application->add(new HtaccessCommand($this->container));
        $command = $application->find('make:htaccess');

        return new CommandTester($command);
    }

    public function testPrintsContentToScreenByDefault(): void
    {
        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('staticforge-manifest.json', $display);
        $this->assertStringContainsString('Strict-Transport-Security', $display);
        $this->assertFileDoesNotExist($this->testDir . '/htaccess.txt');
    }

    public function testWritesToDefaultFileWhenWriteFlagUsed(): void
    {
        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute(['--write' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->testDir . '/htaccess.txt');
        $content = file_get_contents($this->testDir . '/htaccess.txt');
        $this->assertNotFalse($content);
        $this->assertStringContainsString('Require all denied', $content);
    }

    public function testWritesToCustomOutputFile(): void
    {
        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute(['--write' => true, '--output' => 'custom.htaccess']);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->testDir . '/custom.htaccess');
    }

    public function testWarnsWhenOverwritingExistingHtaccessFile(): void
    {
        file_put_contents($this->testDir . '/.htaccess', 'old content');

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute(['--write' => true, '--output' => '.htaccess']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Overwriting existing .htaccess file', $tester->getDisplay());
    }

    public function testFailsWhenOutputPathIsNotWritable(): void
    {
        // file_put_contents() emits a PHP warning for the unwritable path; the command
        // itself handles this gracefully via the `=== false` check, so we suppress the
        // expected warning here to keep the test output focused on the behavior under test.
        $tester = $this->makeCommandTester();
        $exitCode = @$tester->execute([
            '--write' => true,
            '--output' => '/nonexistent-directory/htaccess.txt',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Failed to write', $tester->getDisplay());
    }
}
