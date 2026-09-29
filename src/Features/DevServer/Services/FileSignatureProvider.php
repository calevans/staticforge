<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\DevServer\Services;

/**
 * Path + mtime + size for every regular file under the watched roots.
 * Symlinks are never followed (no loops, no escaping the project).
 */
final class FileSignatureProvider
{
    public const MIN_INTERVAL_MS = 500;

    private int $lastScanMs = 0;
    /** @var list<string> */
    private array $roots = [];
    /** @var list<string> */
    private array $excluded = [];

    /**
     * @param list<string> $roots Files or directories to watch; missing ones are ignored
     * @param list<string> $excluded Directories (or files) never reported
     */
    public function __construct(
        array $roots,
        array $excluded,
        private readonly string $excludedName = '.staticforge-build'
    ) {
        foreach ($roots as $root) {
            $real = realpath($root);
            if ($real !== false) {
                $this->roots[] = $real;
            }
        }
        foreach ($excluded as $path) {
            $this->excluded[] = rtrim(realpath($path) ?: $path, '/');
        }
    }

    /**
     * @return array<string, string>
     */
    public function signature(): array
    {
        $started = hrtime(true);
        $result = [];
        foreach ($this->roots as $root) {
            $this->visit($root, $result);
        }
        $this->lastScanMs = (int) ((hrtime(true) - $started) / 1_000_000);

        return $result;
    }

    /**
     * Milliseconds to wait before the next scan: never below MIN_INTERVAL_MS and
     * at least five scan durations, so a big tree on a slow mount is not hammered.
     */
    public function intervalMs(): int
    {
        return max(self::MIN_INTERVAL_MS, 5 * $this->lastScanMs);
    }

    private function isIgnoredName(string $name): bool
    {
        return $name === '4913'
            || $name === '.DS_Store'
            || str_ends_with($name, '~')
            || str_ends_with($name, '.swp')
            || str_ends_with($name, '.swo')
            || str_ends_with($name, '.tmp')
            || str_starts_with($name, '.#');
    }

    /**
     * @param array<string, string> $result
     */
    private function visit(string $path, array &$result): void
    {
        if (in_array($path, $this->excluded, true) || basename($path) === $this->excludedName) {
            return;
        }

        $stat = @lstat($path);
        if ($stat === false) {
            return;
        }

        $type = $stat['mode'] & 0170000;
        if ($type === 0100000) {
            if ($this->isIgnoredName(basename($path))) {
                return;
            }
            $result[$path] = $stat['mtime'] . ':' . $stat['size'];
            return;
        }
        if ($type !== 0040000) {
            return;
        }

        $entries = @scandir($path);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->visit($path . '/' . $entry, $result);
        }
    }
}
