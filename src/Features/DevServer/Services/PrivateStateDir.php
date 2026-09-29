<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\DevServer\Services;

/**
 * A 0700 directory with an unguessable name that holds the generated router
 * and the watch state file. Files are written via temp file + rename.
 */
final class PrivateStateDir
{
    public const PREFIX = 'staticforge-dev-';

    private function __construct(private readonly string $path)
    {
    }

    public static function create(?string $baseDir = null): self
    {
        $base = rtrim($baseDir ?? sys_get_temp_dir(), '/');
        $path = $base . '/' . self::PREFIX . bin2hex(random_bytes(8));

        if (!@mkdir($path, 0700)) {
            throw new \RuntimeException("Could not create private directory: {$path}");
        }
        // mkdir() is subject to the umask; make the mode exact.
        @chmod($path, 0700);
        clearstatcache(true, $path);

        $stat = @lstat($path);
        $isDir = $stat !== false && ($stat['mode'] & 0170000) === 0040000;
        $modeOk = $stat !== false && ($stat['mode'] & 0777) === 0700;
        $ownerOk = $stat !== false && (!function_exists('posix_geteuid') || $stat['uid'] === posix_geteuid());
        if (!$isDir || !$modeOk || !$ownerOk) {
            @rmdir($path);
            throw new \RuntimeException("Private directory failed ownership/mode verification: {$path}");
        }

        return new self($path);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function file(string $name): string
    {
        return $this->path . '/' . $name;
    }

    public function write(string $name, string $contents): void
    {
        $target = $this->file($name);
        $temp = $this->path . '/.' . bin2hex(random_bytes(6)) . '.tmp';

        try {
            if (file_put_contents($temp, $contents) === false) {
                throw new \RuntimeException("Could not write {$target}");
            }
            chmod($temp, 0600);
            if (!rename($temp, $target)) {
                throw new \RuntimeException("Could not write {$target}");
            }
        } catch (\Throwable $e) {
            @unlink($temp);
            throw $e;
        }
    }

    public function remove(): void
    {
        if (!str_starts_with(basename($this->path), self::PREFIX) || !is_dir($this->path) || is_link($this->path)) {
            return;
        }

        foreach (scandir($this->path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                @unlink($this->path . '/' . $entry);
            }
        }
        @rmdir($this->path);
    }
}
