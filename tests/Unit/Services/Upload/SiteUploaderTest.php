<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services\Upload;

use EICC\StaticForge\Services\Upload\SiteUploader;
use EICC\StaticForge\Services\Upload\SftpClient;
use EICC\StaticForge\Services\Upload\UploadCheckService;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Output\BufferedOutput;

class SiteUploaderTest extends UnitTestCase
{
    private SiteUploader $uploader;
    /** @var SftpClient&MockObject */
    private SftpClient $mockClient;
    /** @var Log&MockObject */
    private Log $mockLogger;
    /** @var UploadCheckService&MockObject */
    private UploadCheckService $mockCheckService;
    /** @var EventManager&MockObject */
    private EventManager $mockEventManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = $this->createMock(SftpClient::class);
        $this->mockLogger = $this->createMock(Log::class);
        $this->mockCheckService = $this->createMock(UploadCheckService::class);
        $this->mockEventManager = $this->createMock(EventManager::class);

        $this->uploader = new SiteUploader(
            $this->mockClient,
            $this->mockLogger,
            $this->mockCheckService,
            $this->mockEventManager
        );
    }

    public function testGetFilesToUpload(): void
    {
        // Create a temporary directory structure
        $tmpDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($tmpDir);
        touch($tmpDir . '/file1.txt');
        mkdir($tmpDir . '/subdir');
        touch($tmpDir . '/subdir/file2.txt');

        try {
            $files = $this->uploader->getFilesToUpload($tmpDir);

            $this->assertCount(2, $files);
            $this->assertContains($tmpDir . '/file1.txt', $files);
            $this->assertContains($tmpDir . '/subdir/file2.txt', $files);
        } finally {
            // Cleanup
            @unlink($tmpDir . '/subdir/file2.txt');
            @rmdir($tmpDir . '/subdir');
            @unlink($tmpDir . '/file1.txt');
            @rmdir($tmpDir);
        }
    }

    public function testUploadDryRun(): void
    {
        $tmpDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($tmpDir);
        touch($tmpDir . '/file1.txt');

        try {
            $output = new BufferedOutput();

            // SftpClient should read manifest
            $this->mockClient->method('readFile')->willReturn(null); // No manifest

            // Should NOT upload
            $this->mockClient->expects($this->never())->method('uploadFile');

            // Hashes
            $this->mockCheckService->method('calculateHash')->willReturn('hash123');

            // Event
            $this->mockEventManager->method('fire')->will($this->returnArgument(1));

            $errorCount = $this->uploader->upload($tmpDir, '/remote', true, $output);

            $this->assertEquals(0, $errorCount);
            $display = $output->fetch();
            $this->assertStringContainsString('[DRY RUN]', $display);
            $this->assertStringContainsString('file1.txt', $display);
        } finally {
            unlink($tmpDir . '/file1.txt');
            rmdir($tmpDir);
        }
    }

    public function testUploadSuccess(): void
    {
        $tmpDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($tmpDir);
        touch($tmpDir . '/file1.txt');

        try {
            $output = new BufferedOutput();

            $this->mockClient->method('readFile')->willReturn(null); // No manifest
            $this->mockCheckService->method('calculateHash')->willReturn('hash123');

            // Pass-through event
            $this->mockEventManager->method('fire')->will($this->returnArgument(1));

            $this->mockClient->expects($this->once())
                ->method('uploadFile')
                ->with($tmpDir . '/file1.txt', '/remote/file1.txt')
                ->willReturn(true);

            // Manifest update
            $this->mockClient->expects($this->atLeastOnce())
                ->method('putContent'); // For manifest and .htaccess

            $errorCount = $this->uploader->upload($tmpDir, '/remote', false, $output);

            $this->assertEquals(0, $errorCount);
            $display = $output->fetch();
            $this->assertStringContainsString('Upload complete: 1 files uploaded, 0 errors', $display);
        } finally {
            unlink($tmpDir . '/file1.txt');
            rmdir($tmpDir);
        }
    }

    public function testUploadFailure(): void
    {
        $tmpDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($tmpDir);
        touch($tmpDir . '/file1.txt');

        try {
            $output = new BufferedOutput();

            $this->mockClient->method('readFile')->willReturn(null);
            $this->mockCheckService->method('calculateHash')->willReturn('hash123');
            $this->mockEventManager->method('fire')->will($this->returnArgument(1));

            $this->mockClient->expects($this->once())
                ->method('uploadFile')
                ->willReturn(false);

            $errorCount = $this->uploader->upload($tmpDir, '/remote', false, $output);

            $this->assertEquals(1, $errorCount);
            $display = $output->fetch();
            $this->assertStringContainsString('Failed to upload: file1.txt', $display);
        } finally {
            unlink($tmpDir . '/file1.txt');
            rmdir($tmpDir);
        }
    }

    public function testUploadMigratesLegacyListFormatManifest(): void
    {
        $tmpDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($tmpDir);
        touch($tmpDir . '/file1.txt');

        try {
            $output = new BufferedOutput();
            $output->setVerbosity(BufferedOutput::VERBOSITY_VERBOSE);

            // Legacy manifest format: a flat JSON list of paths instead of a map
            $this->mockClient->method('readFile')->willReturn(json_encode(['file1.txt', 'old-file.txt']));
            $this->mockCheckService->method('calculateHash')->willReturn('hash123');
            $this->mockEventManager->method('fire')->will($this->returnArgument(1));

            // Since legacy manifest maps to null hashes, the file should be re-uploaded
            $this->mockClient->expects($this->once())
                ->method('uploadFile')
                ->willReturn(true);

            $errorCount = $this->uploader->upload($tmpDir, '/remote', false, $output);

            $this->assertEquals(0, $errorCount);
            $display = $output->fetch();
            $this->assertStringContainsString('Upgrading manifest from legacy list format', $display);
        } finally {
            unlink($tmpDir . '/file1.txt');
            rmdir($tmpDir);
        }
    }

    public function testUploadHandlesInvalidManifestJson(): void
    {
        $tmpDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($tmpDir);
        touch($tmpDir . '/file1.txt');

        try {
            $output = new BufferedOutput();

            // Malformed JSON content for the manifest
            $this->mockClient->method('readFile')->willReturn('{not valid json');
            $this->mockCheckService->method('calculateHash')->willReturn('hash123');
            $this->mockEventManager->method('fire')->will($this->returnArgument(1));

            $this->mockClient->expects($this->once())
                ->method('uploadFile')
                ->willReturn(true);

            $errorCount = $this->uploader->upload($tmpDir, '/remote', false, $output);

            $this->assertEquals(0, $errorCount);
            $display = $output->fetch();
            $this->assertStringContainsString('Invalid manifest format', $display);
        } finally {
            unlink($tmpDir . '/file1.txt');
            rmdir($tmpDir);
        }
    }

    public function testUploadCleansUpStaleRemoteFiles(): void
    {
        $tmpDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($tmpDir);
        touch($tmpDir . '/file1.txt');

        try {
            $output = new BufferedOutput();
            $output->setVerbosity(BufferedOutput::VERBOSITY_VERBOSE);

            // Old manifest has a file that no longer exists locally
            $this->mockClient->method('readFile')->willReturn(json_encode([
                'file1.txt' => 'hash123',
                'stale-file.txt' => 'oldhash',
            ]));
            $this->mockCheckService->method('calculateHash')->willReturn('hash123');
            $this->mockEventManager->method('fire')->will($this->returnArgument(1));

            // file1.txt is unchanged, so it should not be re-uploaded
            $this->mockClient->expects($this->never())->method('uploadFile');

            // The stale file should be deleted from remote
            $this->mockClient->expects($this->once())
                ->method('deleteFile')
                ->with('/remote/stale-file.txt')
                ->willReturn(true);

            $errorCount = $this->uploader->upload($tmpDir, '/remote', false, $output);

            $this->assertEquals(0, $errorCount);
            $display = $output->fetch();
            $this->assertStringContainsString('Cleaning up 1 stale files', $display);
            $this->assertStringContainsString('Deleted: stale-file.txt', $display);
        } finally {
            unlink($tmpDir . '/file1.txt');
            rmdir($tmpDir);
        }
    }

    public function testUploadReportsFailedStaleFileDeletion(): void
    {
        $tmpDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($tmpDir);
        touch($tmpDir . '/file1.txt');

        try {
            $output = new BufferedOutput();

            $this->mockClient->method('readFile')->willReturn(json_encode([
                'file1.txt' => 'hash123',
                'stale-file.txt' => 'oldhash',
            ]));
            $this->mockCheckService->method('calculateHash')->willReturn('hash123');
            $this->mockEventManager->method('fire')->will($this->returnArgument(1));

            $this->mockClient->method('deleteFile')->willReturn(false);

            $errorCount = $this->uploader->upload($tmpDir, '/remote', false, $output);

            // Cleanup failures don't count toward the returned error count today,
            // but should still be surfaced to the operator via output.
            $this->assertEquals(0, $errorCount);
            $display = $output->fetch();
            $this->assertStringContainsString('Failed to delete: stale-file.txt', $display);
        } finally {
            unlink($tmpDir . '/file1.txt');
            rmdir($tmpDir);
        }
    }

    public function testUploadDryRunDoesNotDeleteStaleFiles(): void
    {
        $tmpDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($tmpDir);
        touch($tmpDir . '/file1.txt');

        try {
            $output = new BufferedOutput();

            $this->mockClient->method('readFile')->willReturn(json_encode([
                'file1.txt' => 'hash123',
                'stale-file.txt' => 'oldhash',
            ]));
            $this->mockCheckService->method('calculateHash')->willReturn('hash123');
            $this->mockEventManager->method('fire')->will($this->returnArgument(1));

            $this->mockClient->expects($this->never())->method('deleteFile');

            $errorCount = $this->uploader->upload($tmpDir, '/remote', true, $output);

            $this->assertEquals(0, $errorCount);
            $display = $output->fetch();
            $this->assertStringContainsString('[DRY RUN] Would delete: stale-file.txt', $display);
        } finally {
            unlink($tmpDir . '/file1.txt');
            rmdir($tmpDir);
        }
    }

    public function testUploadRespectsPluginHandledFile(): void
    {
        $tmpDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($tmpDir);
        touch($tmpDir . '/file1.txt');

        try {
            $output = new BufferedOutput();

            $this->mockClient->method('readFile')->willReturn(null);
            $this->mockCheckService->method('calculateHash')->willReturn('hash123');

            // Plugin (e.g. S3 feature) claims it handled the upload itself
            $this->mockEventManager->method('fire')->willReturnCallback(function (string $event, array $data) {
                $data['handled'] = true;
                return $data;
            });

            // Since the plugin handled it, our own client should never be asked to upload
            $this->mockClient->expects($this->never())->method('uploadFile');

            $errorCount = $this->uploader->upload($tmpDir, '/remote', false, $output);

            $this->assertEquals(0, $errorCount);
        } finally {
            unlink($tmpDir . '/file1.txt');
            rmdir($tmpDir);
        }
    }

    public function testUploadRespectsPluginSkipUpload(): void
    {
        $tmpDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($tmpDir);
        touch($tmpDir . '/file1.txt');

        try {
            $output = new BufferedOutput();
            $output->setVerbosity(BufferedOutput::VERBOSITY_VERBOSE);

            $this->mockClient->method('readFile')->willReturn(null);
            $this->mockCheckService->method('calculateHash')->willReturn('hash123');

            $this->mockEventManager->method('fire')->willReturnCallback(function (string $event, array $data) {
                $data['skip_upload'] = true;
                return $data;
            });

            $this->mockClient->expects($this->never())->method('uploadFile');

            $errorCount = $this->uploader->upload($tmpDir, '/remote', false, $output);

            $this->assertEquals(0, $errorCount);
            $display = $output->fetch();
            $this->assertStringContainsString('Skipped by plugin: file1.txt', $display);
        } finally {
            unlink($tmpDir . '/file1.txt');
            rmdir($tmpDir);
        }
    }

    public function testUploadWithNoFilesReportsNothingToUpload(): void
    {
        $tmpDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($tmpDir);

        try {
            $output = new BufferedOutput();

            $this->mockClient->expects($this->never())->method('readFile');

            $errorCount = $this->uploader->upload($tmpDir, '/remote', false, $output);

            $this->assertEquals(0, $errorCount);
            $display = $output->fetch();
            $this->assertStringContainsString('No files to upload', $display);
        } finally {
            rmdir($tmpDir);
        }
    }

    public function testUploadSkipsManifestUpdateWhenErrorsOccurred(): void
    {
        $tmpDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($tmpDir);
        touch($tmpDir . '/file1.txt');

        try {
            $output = new BufferedOutput();

            $this->mockClient->method('readFile')->willReturn(null);
            $this->mockCheckService->method('calculateHash')->willReturn('hash123');
            $this->mockEventManager->method('fire')->will($this->returnArgument(1));

            $this->mockClient->method('uploadFile')->willReturn(false);

            // Manifest must not be written when upload errors occurred,
            // so the failed file is retried on the next run.
            $this->mockClient->expects($this->never())->method('putContent');

            $errorCount = $this->uploader->upload($tmpDir, '/remote', false, $output);

            $this->assertEquals(1, $errorCount);
        } finally {
            unlink($tmpDir . '/file1.txt');
            rmdir($tmpDir);
        }
    }

    public function testUploadCreatesHtaccessToSecureManifestWhenNoneExists(): void
    {
        $tmpDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($tmpDir);
        touch($tmpDir . '/file1.txt');

        try {
            $output = new BufferedOutput();
            $output->setVerbosity(BufferedOutput::VERBOSITY_VERBOSE);

            $this->mockClient->method('readFile')->willReturn(null);
            $this->mockClient->method('fileExists')->willReturn(false);
            $this->mockCheckService->method('calculateHash')->willReturn('hash123');
            $this->mockEventManager->method('fire')->will($this->returnArgument(1));
            $this->mockClient->method('uploadFile')->willReturn(true);

            $htaccessWritten = false;
            $this->mockClient->method('putContent')->willReturnCallback(
                function (string $path, string $content) use (&$htaccessWritten) {
                    if (str_ends_with($path, '.htaccess')) {
                        $htaccessWritten = str_contains($content, 'Require all denied');
                    }
                    return true;
                }
            );

            $errorCount = $this->uploader->upload($tmpDir, '/remote', false, $output);

            $this->assertEquals(0, $errorCount);
            $this->assertTrue($htaccessWritten, '.htaccess should have been written with the security block');
            $display = $output->fetch();
            $this->assertStringContainsString('Creating .htaccess to secure manifest', $display);
        } finally {
            unlink($tmpDir . '/file1.txt');
            rmdir($tmpDir);
        }
    }

    public function testUploadAppendsToExistingHtaccessWhenNotAlreadySecured(): void
    {
        $tmpDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($tmpDir);
        touch($tmpDir . '/file1.txt');

        try {
            $output = new BufferedOutput();
            $output->setVerbosity(BufferedOutput::VERBOSITY_VERBOSE);

            $this->mockClient->method('readFile')->willReturnCallback(function (string $path) {
                if (str_ends_with($path, '.htaccess')) {
                    return "# existing rules\n";
                }
                return null; // No manifest yet
            });
            $this->mockClient->method('fileExists')->willReturn(true);
            $this->mockCheckService->method('calculateHash')->willReturn('hash123');
            $this->mockEventManager->method('fire')->will($this->returnArgument(1));
            $this->mockClient->method('uploadFile')->willReturn(true);

            $htaccessWritten = false;
            $this->mockClient->method('putContent')->willReturnCallback(
                function (string $path, string $content) use (&$htaccessWritten) {
                    if (str_ends_with($path, '.htaccess')) {
                        $htaccessWritten = str_contains($content, '# existing rules')
                            && str_contains($content, 'Require all denied');
                    }
                    return true;
                }
            );

            $errorCount = $this->uploader->upload($tmpDir, '/remote', false, $output);

            $this->assertEquals(0, $errorCount);
            $this->assertTrue($htaccessWritten, '.htaccess should preserve existing rules and add the security block');
            $display = $output->fetch();
            $this->assertStringContainsString('Securing manifest in existing .htaccess', $display);
        } finally {
            unlink($tmpDir . '/file1.txt');
            rmdir($tmpDir);
        }
    }

    public function testUploadSkipsHtaccessUpdateWhenAlreadySecured(): void
    {
        $tmpDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($tmpDir);
        touch($tmpDir . '/file1.txt');

        try {
            $output = new BufferedOutput();

            $this->mockClient->method('readFile')->willReturnCallback(function (string $path) {
                if (str_ends_with($path, '.htaccess')) {
                    return "<Files \"staticforge-manifest.json\">\n    Require all denied\n</Files>\n";
                }
                return null;
            });
            $this->mockClient->method('fileExists')->willReturn(true);
            $this->mockCheckService->method('calculateHash')->willReturn('hash123');
            $this->mockEventManager->method('fire')->will($this->returnArgument(1));
            $this->mockClient->method('uploadFile')->willReturn(true);

            // putContent should only be called once, for the manifest itself,
            // not for the already-secured .htaccess
            $this->mockClient->expects($this->once())
                ->method('putContent')
                ->with($this->stringContains('staticforge-manifest.json'))
                ->willReturn(true);

            $errorCount = $this->uploader->upload($tmpDir, '/remote', false, $output);

            $this->assertEquals(0, $errorCount);
        } finally {
            unlink($tmpDir . '/file1.txt');
            rmdir($tmpDir);
        }
    }

    public function testUploadWarnsWhenHtaccessExistsButCannotBeRead(): void
    {
        $tmpDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($tmpDir);
        touch($tmpDir . '/file1.txt');

        try {
            $output = new BufferedOutput();

            $this->mockClient->method('readFile')->willReturnCallback(function (string $path) {
                if (str_ends_with($path, '.htaccess')) {
                    return null; // Exists but unreadable
                }
                return null;
            });
            $this->mockClient->method('fileExists')->willReturn(true);
            $this->mockCheckService->method('calculateHash')->willReturn('hash123');
            $this->mockEventManager->method('fire')->will($this->returnArgument(1));
            $this->mockClient->method('uploadFile')->willReturn(true);

            $errorCount = $this->uploader->upload($tmpDir, '/remote', false, $output);

            $this->assertEquals(0, $errorCount);
            $display = $output->fetch();
            $this->assertStringContainsString(
                '.htaccess exists but cannot be read. Skipping security update.',
                $display
            );
        } finally {
            unlink($tmpDir . '/file1.txt');
            rmdir($tmpDir);
        }
    }
}
