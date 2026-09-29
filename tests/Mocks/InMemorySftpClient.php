<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Mocks;

use EICC\StaticForge\Services\Upload\SftpClientInterface;

/**
 * In-memory remote server. Every call is appended to $calls in order, e.g. "upload:/r/a.txt".
 */
final class InMemorySftpClient implements SftpClientInterface
{
    /** @var list<string> */
    public array $calls = [];

    /** @var array<string, string> Remote path => content */
    public array $files = [];

    public bool $connectResult = true;
    public bool $directoryResult = true;

    /** 1-based index of the uploadFile() call that fails; null = none fail */
    public ?int $failUploadNumber = null;
    public bool $failAllDeletes = false;
    public bool $failManifestWrite = false;

    /** @var list<string> Remote paths whose delete fails */
    public array $failDeletePaths = [];

    private int $uploadCount = 0;

    /**
     * @param array<string, string> $files
     */
    public function __construct(array $files = [])
    {
        $this->files = $files;
    }

    public function connect(array $config): bool
    {
        $this->calls[] = 'connect';
        return $this->connectResult;
    }

    public function disconnect(): void
    {
        $this->calls[] = 'disconnect';
    }

    public function ensureRemoteDirectory(string $path): bool
    {
        $this->calls[] = 'mkdir:' . $path;
        return $this->directoryResult;
    }

    public function uploadFile(string $localPath, string $remotePath): bool
    {
        $this->calls[] = 'upload:' . $remotePath;
        $this->uploadCount++;

        if ($this->failUploadNumber === $this->uploadCount) {
            return false;
        }

        $content = file_get_contents($localPath);
        if ($content === false) {
            return false;
        }

        $this->files[$remotePath] = $content;
        return true;
    }

    public function fileExists(string $remotePath): bool
    {
        $this->calls[] = 'exists:' . $remotePath;
        return array_key_exists($remotePath, $this->files);
    }

    public function readFile(string $remotePath): ?string
    {
        $this->calls[] = 'read:' . $remotePath;
        return $this->files[$remotePath] ?? null;
    }

    public function deleteFile(string $remotePath): bool
    {
        $this->calls[] = 'delete:' . $remotePath;

        if (
            $this->failAllDeletes
            || in_array($remotePath, $this->failDeletePaths, true)
            || !array_key_exists($remotePath, $this->files)
        ) {
            return false;
        }

        unset($this->files[$remotePath]);
        return true;
    }

    public function putContent(string $remotePath, string $content): bool
    {
        $this->calls[] = 'put:' . $remotePath;

        if ($this->failManifestWrite && str_ends_with($remotePath, 'staticforge-manifest.json')) {
            return false;
        }

        $this->files[$remotePath] = $content;
        return true;
    }

    /**
     * @return list<string> Call log entries starting with "$prefix:"
     */
    public function callsOf(string $prefix): array
    {
        return array_values(array_filter(
            $this->calls,
            static fn (string $call): bool => str_starts_with($call, $prefix . ':')
        ));
    }
}
