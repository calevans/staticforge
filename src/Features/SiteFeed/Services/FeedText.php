<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\SiteFeed\Services;

/**
 * One bad byte in a page must not make XMLWriter or json_encode drop the
 * whole feed.
 */
final class FeedText
{
    /**
     * Valid UTF-8 only; suitable for JSON.
     */
    public static function scrub(string $text): string
    {
        return mb_scrub($text, 'UTF-8');
    }

    /**
     * Valid UTF-8 without characters XML 1.0 forbids; encoding is left to XMLWriter.
     */
    public static function xml(string $text): string
    {
        return preg_replace(
            '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            self::scrub($text)
        ) ?? '';
    }
}
