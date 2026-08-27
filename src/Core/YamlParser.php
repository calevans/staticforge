<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core;

use Symfony\Component\Yaml\Yaml;

/**
 * The single parse boundary for every YAML document StaticForge reads —
 * content frontmatter and siteconfig alike.
 *
 * Symfony's YAML parser resolves an unquoted date scalar such as
 * `date: 2026-01-15` to a Unix timestamp int, so consumers downstream that
 * reasonably expect a string (strtotime(), a `: string` return type, a Twig
 * `{{ page.date }}`) receive an int instead and fail three layers away from
 * the file that caused it.
 *
 * Parsing with PARSE_DATETIME makes the parser hand back a DateTimeImmutable
 * for exactly the scalars YAML considers dates, which is the discriminator we
 * need: `weight: 3` stays an int, `date: 2026-01-15` does not. Those objects
 * are then formatted back to a canonical string here, so no caller ever sees
 * a date-shaped value as anything other than the string its author wrote.
 */
final class YamlParser
{
    /**
     * Parse a YAML string with date scalars normalized to canonical strings.
     */
    public static function parse(string $yaml): mixed
    {
        return self::normalize(Yaml::parse($yaml, Yaml::PARSE_DATETIME));
    }

    /**
     * Parse a YAML file with date scalars normalized to canonical strings.
     */
    public static function parseFile(string $path): mixed
    {
        return self::normalize(Yaml::parseFile($path, Yaml::PARSE_DATETIME));
    }

    /**
     * Recursively replace every DateTimeInterface in a parsed document with a
     * canonical string. Dates carrying no time of day render as `Y-m-d` so
     * they survive a round trip looking exactly as the author typed them;
     * anything with a real time keeps its offset in ATOM form.
     */
    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s') === '00:00:00'
                ? $value->format('Y-m-d')
                : $value->format(\DateTimeInterface::ATOM);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::normalize($item);
            }
        }

        return $value;
    }
}
