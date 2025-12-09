<?php

declare(strict_types=1);

namespace Tests\Unit\Features\S3MediaOffload\Services;

use Aws\Result;
use Aws\S3\S3Client;
use EICC\StaticForge\Features\S3MediaOffload\Services\S3Service;
use EICC\Utils\Log;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class S3ServiceTest extends TestCase
{
    private $logger;
    private $s3ClientMock;
    private $service;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(Log::class);

        $config = [
            'bucket' => 'test-bucket',
            'region' => 'us-west-2',
        ];

        $this->service = new S3Service($this->logger, $config);

        // Mock S3Client
        $this->s3ClientMock = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPaginator'])
            ->addMethods(['putObject', 'getObject'])
            ->getMock();

        // Inject mock
        $reflection = new ReflectionClass($this->service);
        $clientProperty = $reflection->getProperty('s3Client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->service, $this->s3ClientMock);
    }

    public function testConstructorThrowsExceptionIfBucketMissing(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('S3 Bucket is not configured.');

        new S3Service($this->logger, []);
    }

    public function testUploadFileSuccess(): void
    {
        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_s3');
        file_put_contents($tempFile, 'test content');

        $this->s3ClientMock->expects($this->once())
            ->method('putObject')
            ->with([
                'Bucket' => 'test-bucket',
                'Key'    => 'test-key',
                'SourceFile' => $tempFile,
                'ACL'    => 'public-read',
            ])
            ->willReturn(new Result());

        $this->logger->expects($this->once())
            ->method('log')
            ->with('INFO', $this->stringContains('Uploaded'));

        $result = $this->service->uploadFile($tempFile, 'test-key');
        $this->assertTrue($result);

        unlink($tempFile);
    }

    public function testUploadFileFailure(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_s3');

        $this->s3ClientMock->expects($this->once())
            ->method('putObject')
            ->willThrowException(new \Exception('S3 Error'));

        $this->logger->expects($this->once())
            ->method('log')
            ->with('ERROR', $this->stringContains('Failed to upload'));

        $result = $this->service->uploadFile($tempFile, 'test-key');
        $this->assertFalse($result);

        unlink($tempFile);
    }

    public function testUploadFileMissingFile(): void
    {
        $this->logger->expects($this->once())
            ->method('log')
            ->with('ERROR', $this->stringContains('File not found'));

        $result = $this->service->uploadFile('/non/existent/file', 'test-key');
        $this->assertFalse($result);
    }

    public function testDownloadFileSuccess(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_s3_dl');
        // We want to test that it writes to this file, so let's delete it first
        unlink($tempFile);

        $this->s3ClientMock->expects($this->once())
            ->method('getObject')
            ->with([
                'Bucket' => 'test-bucket',
                'Key'    => 'test-key',
            ])
            ->willReturn(new Result(['Body' => 'downloaded content']));

        $this->logger->expects($this->once())
            ->method('log')
            ->with('INFO', $this->stringContains('Downloaded'));

        $result = $this->service->downloadFile('test-key', $tempFile);
        $this->assertTrue($result);
        $this->assertFileExists($tempFile);
        $this->assertEquals('downloaded content', file_get_contents($tempFile));

        unlink($tempFile);
    }

    public function testDownloadFileFailure(): void
    {
        $this->s3ClientMock->expects($this->once())
            ->method('getObject')
            ->willThrowException(new \Exception('S3 Error'));

        $this->logger->expects($this->once())
            ->method('log')
            ->with('ERROR', $this->stringContains('Failed to download'));

        $result = $this->service->downloadFile('test-key', '/tmp/fail');
        $this->assertFalse($result);
    }
}
