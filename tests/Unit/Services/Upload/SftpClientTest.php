<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services\Upload;

use EICC\StaticForge\Services\Upload\SftpClient;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use PHPUnit\Framework\MockObject\MockObject;

class SftpClientTest extends UnitTestCase
{
    private SftpClient $client;
    /** @var Log&MockObject */
    private Log $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockLogger = $this->createMock(Log::class);
        $this->client = new SftpClient($this->mockLogger);
    }

    public function testConnectFailsWhenHostUnreachable(): void
    {
        // Port 1 is a privileged/unassigned port that should refuse connections quickly,
        // simulating a real network failure without depending on external infrastructure.
        $config = [
            'host' => '127.0.0.1',
            'port' => 1,
            'username' => 'user',
            'password' => 'secret',
            'key_path' => null,
            'key_passphrase' => null,
        ];

        $result = $this->client->connect($config);

        $this->assertFalse($result);
    }

    public function testConnectWithKeyAuthFailsWhenKeyFileMissing(): void
    {
        $config = [
            'host' => '127.0.0.1',
            'port' => 1,
            'username' => 'user',
            'password' => null,
            'key_path' => '/nonexistent/path/to/key',
            'key_passphrase' => null,
        ];

        $result = $this->client->connect($config);

        $this->assertFalse($result);
    }

    public function testUploadFileFailsWhenNotConnected(): void
    {
        // getSftp() throws RuntimeException internally, caught by the broad
        // catch block in uploadFile(), surfacing as a graceful false return.
        $result = $this->client->uploadFile('/local/file.txt', '/remote/file.txt');

        $this->assertFalse($result);
    }

    public function testFileExistsFailsWhenNotConnected(): void
    {
        $result = $this->client->fileExists('/remote/file.txt');

        $this->assertFalse($result);
    }

    public function testReadFileReturnsNullWhenNotConnected(): void
    {
        $result = $this->client->readFile('/remote/file.txt');

        $this->assertNull($result);
    }

    public function testDeleteFileFailsWhenNotConnected(): void
    {
        $result = $this->client->deleteFile('/remote/file.txt');

        $this->assertFalse($result);
    }

    public function testPutContentFailsWhenNotConnected(): void
    {
        $result = $this->client->putContent('/remote/file.txt', 'content');

        $this->assertFalse($result);
    }

    public function testEnsureRemoteDirectoryFailsWhenNotConnected(): void
    {
        $result = $this->client->ensureRemoteDirectory('/remote/dir');

        $this->assertFalse($result);
    }

    public function testDisconnectIsSafeWhenNeverConnected(): void
    {
        // Should not throw even though no connection was ever established, and the
        // client should remain in a consistent "not connected" state afterward.
        $this->client->disconnect();

        $this->assertFalse($this->client->fileExists('/remote/file.txt'));
    }

    public function testDisconnectIsSafeWhenCalledTwice(): void
    {
        $this->client->disconnect();
        $this->client->disconnect();

        $this->assertFalse($this->client->fileExists('/remote/file.txt'));
    }
}
