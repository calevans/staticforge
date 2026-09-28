<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services;

/**
 * Delimits a page's own content inside the fully templated HTML, so POST_RENDER
 * consumers (RSS, search) can pull the article out without the theme's nav,
 * sidebars and scripts. Markers live in the written file rather than in event
 * state because incremental-build cache hits never run RENDER - the file on
 * disk is the only thing those consumers get.
 */
final class ContentMarkers
{
    public const START = '<!--sf:content-->';
    public const END = '<!--/sf:content-->';

    public static function wrap(string $content): string
    {
        return self::START . $content . self::END;
    }

    /**
     * Returns the marked content, or null if $html has no complete marker pair.
     */
    public static function extract(string $html): ?string
    {
        $start = strpos($html, self::START);
        if ($start === false) {
            return null;
        }

        $start += strlen(self::START);
        $end = strpos($html, self::END, $start);
        if ($end === false) {
            return null;
        }

        return substr($html, $start, $end - $start);
    }
}
