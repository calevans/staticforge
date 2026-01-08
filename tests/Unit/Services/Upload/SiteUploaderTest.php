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
}
