<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Deployment\Commands;

use EICC\StaticForge\Features\Deployment\Commands\UploadSiteCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * UploadSiteCommand wires real SftpClient/SiteUploader instances internally rather than
 * accepting them via constructor injection, so failure paths that require a live SFTP
 * connection cannot be exercised here without a real server. These tests focus on the
 * input-validation guard clauses that run before any network I/O occurs.
 */
class UploadSiteCommandTest extends UnitTestCase
{
    private ?string $originalUploadUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalUploadUrl = $_ENV['UPLOAD_URL'] ?? null;
        unset($_ENV['UPLOAD_URL']);
    }

    protected function tearDown(): void
    {
        if ($this->originalUploadUrl !== null) {
            $_ENV['UPLOAD_URL'] = $this->originalUploadUrl;
        } else {
            unset($_ENV['UPLOAD_URL']);
        }
        parent::tearDown();
    }

    public function testExecuteFailsWhenNoUploadUrlProvided(): void
    {
        $application = new Application();
        $application->add(new UploadSiteCommand($this->container));

        $command = $application->find('site:upload');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Upload URL is required', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testExecuteUsesUploadUrlFromEnvWhenOptionNotProvided(): void
    {
        $_ENV['UPLOAD_URL'] = 'https://example.com/';

        // Let config loading succeed (valid, readable OUTPUT_DIR) so we reach the
        // URL-resolution/re-render branch. SFTP_HOST is left unset by the test environment,
        // so the command still fails fast afterward via SftpConfigLoader, before any
        // real network I/O occurs.
        $outputDir = sys_get_temp_dir() . '/staticforge_upload_test_output_' . uniqid();
        mkdir($outputDir, 0755, true);
        $this->setContainerVariable('OUTPUT_DIR', $outputDir);

        $application = new Application();
        $application->add(new UploadSiteCommand($this->container));

        $command = $application->find('site:upload');
        $commandTester = new CommandTester($command);

        try {
            $commandTester->execute([]);

            $output = $commandTester->getDisplay();
            $this->assertStringContainsString('URL override detected: https://example.com/', $output);
            $this->assertEquals(1, $commandTester->getStatusCode());
        } finally {
            $this->removeDirectory($outputDir);
        }
    }
}
