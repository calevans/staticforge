<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\SiteFeed\Services;

/**
 * Feed readers resolve against the feed, not the site, so root-relative
 * src/href/srcset values must carry the site origin.
 *
 * Duplicated from RssFeedService::absolutizeUrls on purpose (RssFeed is
 * frozen for byte-identical category feeds); a candidate for one shared helper.
 */
final class HtmlUrlAbsolutizer
{
    public static function absolutize(string $html, string $siteBaseUrl): string
    {
        $base = rtrim($siteBaseUrl, '/') . '/';
        // Escaped so a $ or backslash in the configured URL isn't read as a backreference
        $replacement = str_replace(['\\', '$'], ['\\\\', '\\$'], $base);

        $html = preg_replace('/\b(src|href)=(["\'])\/(?!\/)/i', '$1=$2' . $replacement, $html) ?? $html;

        return preg_replace_callback(
            '/\bsrcset=(["\'])(.*?)\1/is',
            static fn (array $m): string => 'srcset=' . $m[1] . preg_replace(
                '/(^|,)(\s*)\/(?!\/)/',
                '$1$2' . $replacement,
                $m[2]
            ) . $m[1],
            $html
        ) ?? $html;
    }
}
