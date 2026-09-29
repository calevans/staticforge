<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Services;

/**
 * Tear-down helper for tests that plant symlinks (loops, links to outside paths):
 * links are unlinked, never descended into.
 */
trait SymlinkSafeCleanup
{
    protected function removeDirectory(string $dir): void
    {
        if (is_link($dir)) {
            unlink($dir);
            return;
        }
        if (!is_dir($dir)) {
            return;
        }

        foreach (array_diff((array) scandir($dir), ['.', '..']) as $entry) {
            $path = $dir . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
