<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services;

use EICC\StaticForge\Core\Events\RenderEvent;

/**
 * Lets pre-render features (shortcodes, forms) inject trusted HTML into
 * source content without it passing through the Markdown converter, which
 * escapes raw HTML when markdown.trust_html is false. The HTML is parked on
 * the event and swapped back in by the renderer after conversion.
 */
final class HtmlPlaceholders
{
    private const EXTRA_KEY = 'html_placeholders';
    private const NONCE_KEY = 'html_placeholder_nonce';

    /**
     * Park $html on the event and return the token to put in its place.
     * Tokens are alphanumeric so Markdown leaves them untouched.
     */
    public static function reserve(RenderEvent $event, string $html): string
    {
        $event->extra[self::EXTRA_KEY] ??= [];
        $event->extra[self::NONCE_KEY] ??= bin2hex(random_bytes(6));

        $token = 'SFPH' . $event->extra[self::NONCE_KEY] . 'N' . count($event->extra[self::EXTRA_KEY]) . 'E';
        $event->extra[self::EXTRA_KEY][$token] = $html;

        return $token;
    }

    /**
     * Apply $transform to every parked HTML fragment (e.g. to expand forms
     * nested inside a shortcode's output).
     *
     * @param callable(string): string $transform
     */
    public static function transform(RenderEvent $event, callable $transform): void
    {
        $placeholders = $event->extra[self::EXTRA_KEY] ?? [];
        if (!is_array($placeholders)) {
            return;
        }

        foreach ($placeholders as $token => $html) {
            $event->extra[self::EXTRA_KEY][$token] = $transform($html);
        }
    }

    public static function restore(RenderEvent $event, string $content): string
    {
        $placeholders = $event->extra[self::EXTRA_KEY] ?? [];
        if (!is_array($placeholders) || $placeholders === []) {
            return $content;
        }

        // A token the author placed inside a tag (a link href/title) or a code
        // span must come back as text: spliced in raw, the HTML's quotes would
        // break out of the attribute, and code should show source, not markup.
        $content = preg_replace_callback(
            '#<code\b[^>]*>.*?</code>|<[^>]*>#s',
            static fn (array $m): string => strtr($m[0], array_map(
                static fn (string $html): string => htmlspecialchars($html, ENT_QUOTES, 'UTF-8'),
                $placeholders
            )),
            $content
        ) ?? $content;

        foreach ($placeholders as $token => $html) {
            // A token alone on its line comes back wrapped in <p>, which would
            // put block elements inside a paragraph.
            $content = str_replace(['<p>' . $token . '</p>', $token], [$html, $html], $content);
        }

        return $content;
    }
}
