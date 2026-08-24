<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core;

use RuntimeException;

/**
 * Single policy for keeping a path inside a directory boundary. Replaces the
 * hand-rolled `strpos($path, $root) === 0` checks scattered across Feature
 * services, which are vulnerable to sibling-directory bypass (e.g. `/content`
 * matching `/content-evil`) because they never re-append the directory
 * separator to the root before comparing.
 *
 * Deliberately pure string normalization, not realpath()-based: callers use
 * this for paths that don't exist yet (write targets) and, in tests, for
 * synthetic paths that are never meant to touch the real filesystem.
 */
final class PathGuard
{
    /**
     * Normalize $path and assert it is inside (or equal to) $root. Throws if
     * it is not. vfs:// stream-wrapper paths (used by tests) pass through
     * unchecked, matching the established codebase convention.
     */
    public static function resolveInside(string $path, string $root): string
    {
        if (str_starts_with($path, 'vfs://')) {
            return $path;
        }

        $normalizedRoot = self::normalize($root);
        $normalizedPath = self::normalize($path);
        $isInside = $normalizedPath === $normalizedRoot
            || str_starts_with($normalizedPath, $normalizedRoot . DIRECTORY_SEPARATOR);

        if (!$isInside) {
            throw new RuntimeException("PathGuard: path escapes root: {$path}");
        }

        return $normalizedPath;
    }

    /**
     * Non-throwing variant: returns $path's location relative to $root, or
     * null if $path is outside $root. Used where callers already have a
     * sensible fallback (e.g. basename-only) rather than a hard failure.
     */
    public static function relativeTo(string $path, string $root): ?string
    {
        $normalizedRoot = self::normalize($root);
        $normalizedPath = self::normalize($path);

        if ($normalizedPath === $normalizedRoot) {
            return '';
        }

        $prefix = $normalizedRoot . DIRECTORY_SEPARATOR;
        if (!str_starts_with($normalizedPath, $prefix)) {
            return null;
        }

        return substr($normalizedPath, strlen($prefix));
    }

    /**
     * Collapse `.` and `..` segments without touching the filesystem.
     */
    private static function normalize(string $path): string
    {
        // Preserve a stream-wrapper scheme (e.g. vfs://) separately so the
        // "//" after it survives collapsing, instead of being treated as
        // just another run of separators.
        $scheme = '';
        if (preg_match('#^([a-zA-Z][a-zA-Z0-9+.-]*://)(.*)$#s', $path, $matches) === 1) {
            $scheme = $matches[1];
            $path = $matches[2];
        }

        $isAbsolute = $scheme !== '' || str_starts_with($path, DIRECTORY_SEPARATOR) || str_starts_with($path, '/');

        $segments = preg_split('#[\\\\/]+#', $path) ?: [];
        $stack = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($stack);
                continue;
            }
            $stack[] = $segment;
        }

        $normalized = implode(DIRECTORY_SEPARATOR, $stack);

        if ($scheme !== '') {
            return $scheme . $normalized;
        }

        return $isAbsolute ? DIRECTORY_SEPARATOR . $normalized : $normalized;
    }
}
