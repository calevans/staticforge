<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services;

use EICC\StaticForge\Exceptions\InvalidSlugException;

final class Slugger
{
    /**
     * Slug used for category output directories. Output matches ^[a-z0-9]+(-[a-z0-9]+)*$.
     *
     * @throws InvalidSlugException when nothing usable remains
     */
    public static function category(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));

        if ($slug === null) {
            throw new InvalidSlugException('Category name could not be slugged');
        }

        $slug = trim($slug, '-');

        if ($slug === '') {
            throw new InvalidSlugException('Category name contains no usable characters');
        }

        return $slug;
    }

    /**
     * Same as category(), but falls back to 'category' so the RSS URL for a
     * symbol-only name (/category/rss.xml) is unchanged.
     */
    public static function rssCategory(string $name): string
    {
        try {
            return self::category($name);
        } catch (InvalidSlugException) {
            return 'category';
        }
    }

    /**
     * Slug for generated content filenames.
     *
     * Transliteration via iconv //TRANSLIT depends on the process locale. The
     * locale is deliberately NOT pinned here (setlocale is process-global);
     * callers needing determinism, such as tests, must set it themselves.
     */
    public static function filename(string $text): string
    {
        $slug = strtolower($text);
        $slug = preg_replace('~[^\pL\d]+~u', '-', $slug) ?? $slug;
        $transliterated = iconv('utf-8', 'us-ascii//TRANSLIT', $slug);
        if ($transliterated !== false) {
            $slug = $transliterated;
        }
        $slug = preg_replace('~[^-\w]+~', '', $slug) ?? $slug;
        $slug = trim($slug, '-');
        $slug = preg_replace('~-+~', '-', $slug) ?? $slug;

        if (empty($slug)) {
            return 'untitled';
        }

        return $slug;
    }
}
