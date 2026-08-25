<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services\Upload;

use EICC\StaticForge\Services\Upload\SftpClient;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use PHPUnit\Framework\MockObject\MockObject;
use phpseclib3\Net\SFTP;
use ReflectionMethod;

class SftpClientTest extends UnitTestCase
{
    private SftpClient $client;
    /** @var Log&MockObject */
    private Log $mockLogger;
    private string $knownHostsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockLogger = $this->createMock(Log::class);
        $this->client = new SftpClient($this->mockLogger);
        $this->knownHostsPath = sys_get_temp_dir() . '/staticforge_sftp_client_test_known_hosts_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->knownHostsPath)) {
            unlink($this->knownHostsPath);
        }
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function invokeVerifyHostKey(SFTP $sftp, array $config): bool
    {
        $method = new ReflectionMethod($this->client, 'verifyHostKey');
        return $method->invoke($this->client, $sftp, $config);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseConfig(): array
    {
        return [
            'host' => 'example.com',
            'port' => 22,
            'host_key' => null,
            'known_hosts_path' => $this->knownHostsPath,
        ];
    }

    public function testVerifyHostKeyFailsWhenHandshakeFails(): void
    {
        $sftp = $this->createMock(SFTP::class);
        $sftp->method('getServerPublicHostKey')->willReturn(false);
        $sftp->expects($this->never())->method('disconnect');

        $this->mockLogger->expects($this->once())
            ->method('log')
            ->with('ERROR', $this->stringContains('handshake failed'));

        $this->assertFalse($this->invokeVerifyHostKey($sftp, $this->baseConfig()));
    }

    public function testVerifyHostKeyTrustsAndRecordsOnFirstConnection(): void
    {
        $sftp = $this->createMock(SFTP::class);
        $sftp->method('getServerPublicHostKey')->willReturn('ssh-ed25519 AAAA');
        $sftp->expects($this->never())->method('disconnect');

        $this->mockLogger->expects($this->once())
            ->method('log')
            ->with('INFO', $this->stringContains('First connection'));

        $this->assertTrue($this->invokeVerifyHostKey($sftp, $this->baseConfig()));
        $this->assertFileExists($this->knownHostsPath);
        $storedContents = (string) file_get_contents($this->knownHostsPath);
        $this->assertStringContainsString('example.com:22 ssh-ed25519 AAAA', $storedContents);
    }

    public function testVerifyHostKeySucceedsSilentlyWhenStoredKeyMatches(): void
    {
        file_put_contents($this->knownHostsPath, "example.com:22 ssh-ed25519 AAAA\n");

        $sftp = $this->createMock(SFTP::class);
        $sftp->method('getServerPublicHostKey')->willReturn('ssh-ed25519 AAAA');
        $sftp->expects($this->never())->method('disconnect');

        $this->mockLogger->expects($this->never())->method('log');

        $this->assertTrue($this->invokeVerifyHostKey($sftp, $this->baseConfig()));
    }

    public function testVerifyHostKeyFailsClosedWhenStoredKeyMismatches(): void
    {
        file_put_contents($this->knownHostsPath, "example.com:22 ssh-ed25519 OLDKEY\n");

        $sftp = $this->createMock(SFTP::class);
        $sftp->method('getServerPublicHostKey')->willReturn('ssh-ed25519 NEWKEY');
        $sftp->expects($this->once())->method('disconnect');

        $this->mockLogger->expects($this->once())
            ->method('log')
            ->with('ERROR', $this->stringContains('possible MITM'));

        $this->assertFalse($this->invokeVerifyHostKey($sftp, $this->baseConfig()));

        // The stale key must not have been silently overwritten by the failed attempt.
        $this->assertStringContainsString('OLDKEY', (string) file_get_contents($this->knownHostsPath));
    }

    public function testVerifyHostKeyAcceptsMatchingConfiguredOverride(): void
    {
        $sftp = $this->createMock(SFTP::class);
        $sftp->method('getServerPublicHostKey')->willReturn('ssh-ed25519 AAAA');
        $sftp->expects($this->never())->method('disconnect');

        $config = array_merge($this->baseConfig(), ['host_key' => 'ssh-ed25519 AAAA']);

        $this->assertTrue($this->invokeVerifyHostKey($sftp, $config));
        // The override path must not touch the known_hosts store at all.
        $this->assertFileDoesNotExist($this->knownHostsPath);
    }

    public function testVerifyHostKeyRejectsMismatchedConfiguredOverride(): void
    {
        $sftp = $this->createMock(SFTP::class);
        $sftp->method('getServerPublicHostKey')->willReturn('ssh-ed25519 ACTUAL');
        $sftp->expects($this->once())->method('disconnect');

        $this->mockLogger->expects($this->once())
            ->method('log')
            ->with('ERROR', $this->stringContains('SFTP_HOST_KEY'));

        $config = array_merge($this->baseConfig(), ['host_key' => 'ssh-ed25519 EXPECTED']);

        $this->assertFalse($this->invokeVerifyHostKey($sftp, $config));
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
