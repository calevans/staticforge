<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services\Upload;

/**
 * Trust-on-first-use storage for SFTP host keys. Deliberately its own simple
 * "host:port algo base64key" line format rather than real OpenSSH known_hosts
 * syntax (which hashes hostnames by default) — this file is app-internal
 * state, not meant to interoperate with a system ssh client.
 *
 * No phpseclib dependency: this is plain file I/O and string parsing, kept
 * separate from SftpClient specifically so it's unit-testable without a real
 * SSH server.
 */
class KnownHostsStore
{
    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Returns the stored key for $host:$port, or null if none is recorded yet.
     */
    public function getStoredKey(string $host, int $port): ?string
    {
        if (!file_exists($this->filePath)) {
            return null;
        }

        $contents = file_get_contents($this->filePath);
        if ($contents === false) {
            return null;
        }

        $needle = $this->entryPrefix($host, $port);

        foreach (explode("\n", $contents) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, $needle . ' ')) {
                return substr($line, strlen($needle) + 1);
            }
        }

        return null;
    }

    /**
     * Records $key as the trusted key for $host:$port, replacing any
     * previously stored entry for the same host:port.
     */
    public function remember(string $host, int $port, string $key): void
    {
        $needle = $this->entryPrefix($host, $port);
        $lines = [];

        if (file_exists($this->filePath)) {
            $contents = file_get_contents($this->filePath);
            if ($contents !== false) {
                foreach (explode("\n", $contents) as $line) {
                    $trimmed = trim($line);
                    if ($trimmed === '' || str_starts_with($trimmed, $needle . ' ')) {
                        continue;
                    }
                    $lines[] = $line;
                }
            }
        }

        $lines[] = "{$needle} {$key}";

        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        file_put_contents($this->filePath, implode("\n", $lines) . "\n");
        chmod($this->filePath, 0600);
    }

    private function entryPrefix(string $host, int $port): string
    {
        return "{$host}:{$port}";
    }
}
