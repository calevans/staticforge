<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services\Upload;

interface SftpClientInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function connect(array $config): bool;

    public function disconnect(): void;

    public function ensureRemoteDirectory(string $path): bool;

    public function uploadFile(string $localPath, string $remotePath): bool;

    public function fileExists(string $remotePath): bool;

    public function readFile(string $remotePath): ?string;

    public function deleteFile(string $remotePath): bool;

    public function putContent(string $remotePath, string $content): bool;
}
