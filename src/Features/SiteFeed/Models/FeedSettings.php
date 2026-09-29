<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\SiteFeed\Models;

use EICC\StaticForge\Exceptions\InvalidSlugException;
use EICC\StaticForge\Services\MetadataFlags;
use EICC\StaticForge\Services\Slugger;

/**
 * The parsed `feed:` block of siteconfig.yaml. Invalid values fall back to
 * their defaults and are reported in $warnings rather than aborting a build.
 */
final class FeedSettings
{
    public const FORMATS = ['rss', 'atom', 'json'];
    public const DEFAULT_LIMIT = 20;

    /**
     * @param list<string> $formats
     * @param list<string> $excludeCategories Category slugs
     * @param list<string> $categoryFormats
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly int $limit,
        public readonly array $formats,
        public readonly array $excludeCategories,
        public readonly array $categoryFormats,
        public readonly array $warnings = [],
    ) {
    }

    public static function fromConfig(mixed $raw): self
    {
        if ($raw === null) {
            return new self(false, self::DEFAULT_LIMIT, self::FORMATS, [], ['rss']);
        }

        if (!is_array($raw)) {
            return new self(
                false,
                self::DEFAULT_LIMIT,
                self::FORMATS,
                [],
                ['rss'],
                ["'feed' in siteconfig.yaml must be a mapping (enabled, limit, formats, ...); feeds are off."]
            );
        }

        $warnings = [];
        $enabledRaw = $raw['enabled'] ?? null;
        $enabled = MetadataFlags::isTrue($enabledRaw);
        if ($enabledRaw !== null && !$enabled && !MetadataFlags::isFalse($enabledRaw)) {
            $warnings[] = 'feed.enabled must be true or false; feeds are off.';
        }

        $limit = self::DEFAULT_LIMIT;
        if (array_key_exists('limit', $raw)) {
            if (is_int($raw['limit']) && $raw['limit'] > 0) {
                $limit = $raw['limit'];
            } else {
                $warnings[] = "feed.limit must be a positive integer; using " . self::DEFAULT_LIMIT . '.';
            }
        }

        $formats = self::formatList($raw, 'formats', self::FORMATS, $warnings);
        $categoryFormats = self::formatList($raw, 'category_formats', ['rss'], $warnings);

        $excluded = [];
        if (array_key_exists('exclude_categories', $raw)) {
            if (!is_array($raw['exclude_categories'])) {
                $warnings[] = 'feed.exclude_categories must be a list of category slugs; ignored.';
            } else {
                foreach ($raw['exclude_categories'] as $name) {
                    try {
                        $excluded[] = Slugger::category(is_scalar($name) ? (string) $name : '');
                    } catch (InvalidSlugException) {
                        $warnings[] = 'feed.exclude_categories entry ignored, no usable characters: '
                            . json_encode($name);
                    }
                }
            }
        }

        return new self($enabled, $limit, $formats, array_values(array_unique($excluded)), $categoryFormats, $warnings);
    }

    /**
     * @param array<mixed> $raw
     * @param list<string> $default
     * @param list<string> $warnings
     * @return list<string>
     */
    private static function formatList(array $raw, string $key, array $default, array &$warnings): array
    {
        if (!array_key_exists($key, $raw)) {
            return $default;
        }

        if (!is_array($raw[$key])) {
            $warnings[] = "feed.{$key} must be a list (e.g. [rss, atom, json]); using the default.";
            return $default;
        }

        $formats = [];
        foreach ($raw[$key] as $format) {
            $name = is_string($format) ? strtolower(trim($format)) : '';
            if (in_array($name, self::FORMATS, true)) {
                $formats[] = $name;
            } else {
                $warnings[] = "feed.{$key} contains an unknown format and it was ignored: " . json_encode($format);
            }
        }

        return array_values(array_unique($formats));
    }
}
