<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\DevServer\Services;

/**
 * Prepares build error text for display: valid UTF-8, capped, and with the
 * project, home and temp paths masked (on path boundaries only).
 */
final class ErrorSanitizer
{
    public const MAX_LINES = 5;
    public const MAX_CHARS = 500;

    public static function sanitize(
        string $error,
        string $appRoot,
        ?string $home = null,
        ?string $tempDir = null
    ): string {
        $error = mb_scrub($error);
        $home ??= self::homeDir();
        $tempDir ??= sys_get_temp_dir();

        $masks = [];
        foreach ([[$appRoot, '.'], [$home, '~'], [$tempDir, '<tmp>']] as [$path, $replacement]) {
            $path = rtrim((string) $path, '/');
            if (strlen($path) > 1) {
                $masks[$path] = $replacement;
            }
        }
        uksort($masks, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($masks as $path => $replacement) {
            $error = (string) preg_replace(
                '#' . preg_quote($path, '#') . '(?![\w.\-])#u',
                str_replace(['\\', '$'], ['\\\\', '\\$'], $replacement),
                $error
            );
        }

        $lines = array_slice(preg_split('/\R/', trim($error)) ?: [], 0, self::MAX_LINES);

        return mb_substr(implode("\n", $lines), 0, self::MAX_CHARS);
    }

    private static function homeDir(): ?string
    {
        $home = getenv('HOME');

        return $home === false ? null : $home;
    }
}
