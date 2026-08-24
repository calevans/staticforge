<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services\Upload;

use EICC\StaticForge\Services\Upload\KnownHostsStore;
use PHPUnit\Framework\TestCase;

class KnownHostsStoreTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        $this->filePath = sys_get_temp_dir() . '/staticforge_known_hosts_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
    }

    public function testGetStoredKeyReturnsNullWhenFileDoesNotExist(): void
    {
        $store = new KnownHostsStore($this->filePath);

        $this->assertNull($store->getStoredKey('example.com', 22));
    }

    public function testRememberThenGetStoredKeyRoundTrips(): void
    {
        $store = new KnownHostsStore($this->filePath);

        $store->remember('example.com', 22, 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAA');

        $this->assertSame('ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAA', $store->getStoredKey('example.com', 22));
    }

    public function testGetStoredKeyReturnsNullForDifferentHost(): void
    {
        $store = new KnownHostsStore($this->filePath);
        $store->remember('example.com', 22, 'ssh-ed25519 AAAA');

        $this->assertNull($store->getStoredKey('other.example.com', 22));
    }

    public function testGetStoredKeyReturnsNullForDifferentPort(): void
    {
        $store = new KnownHostsStore($this->filePath);
        $store->remember('example.com', 22, 'ssh-ed25519 AAAA');

        $this->assertNull($store->getStoredKey('example.com', 2222));
    }

    public function testRememberOverwritesPreviousKeyForSameHostAndPort(): void
    {
        $store = new KnownHostsStore($this->filePath);
        $store->remember('example.com', 22, 'ssh-ed25519 OLDKEY');
        $store->remember('example.com', 22, 'ssh-ed25519 NEWKEY');

        $this->assertSame('ssh-ed25519 NEWKEY', $store->getStoredKey('example.com', 22));

        // Only one entry should remain, not two - a subsequent lookup after a
        // key rotation must not have any way to match the stale key.
        $contents = (string) file_get_contents($this->filePath);
        $this->assertSame(1, substr_count($contents, 'example.com:22 '));
    }

    public function testRememberPreservesEntriesForOtherHosts(): void
    {
        $store = new KnownHostsStore($this->filePath);
        $store->remember('example.com', 22, 'ssh-ed25519 AAAA');
        $store->remember('other.example.com', 22, 'ssh-ed25519 BBBB');

        $this->assertSame('ssh-ed25519 AAAA', $store->getStoredKey('example.com', 22));
        $this->assertSame('ssh-ed25519 BBBB', $store->getStoredKey('other.example.com', 22));
    }

    public function testRememberCreatesParentDirectoryIfMissing(): void
    {
        $nestedPath = sys_get_temp_dir() . '/staticforge_known_hosts_dir_' . uniqid() . '/known_hosts';
        $store = new KnownHostsStore($nestedPath);

        $store->remember('example.com', 22, 'ssh-ed25519 AAAA');

        $this->assertFileExists($nestedPath);
        $this->assertSame('ssh-ed25519 AAAA', $store->getStoredKey('example.com', 22));

        unlink($nestedPath);
        rmdir(dirname($nestedPath));
    }

    public function testRememberSetsRestrictivePermissions(): void
    {
        $store = new KnownHostsStore($this->filePath);
        $store->remember('example.com', 22, 'ssh-ed25519 AAAA');

        $permissions = fileperms($this->filePath) & 0777;
        $this->assertSame(0600, $permissions);
    }
}
