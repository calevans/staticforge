<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Characterization\Slug;

use EICC\StaticForge\Commands\Make\ContentCreatorCommand;
use EICC\StaticForge\Core\FileDiscovery;
use EICC\StaticForge\Features\Categories\Services\CategoriesService;
use EICC\StaticForge\Features\RobotsTxt\Services\RobotsTxtService;
use EICC\StaticForge\Features\RssFeed\Services\RssFeedService;
use EICC\StaticForge\Services\TemplateRenderer;
use ReflectionClass;
use ReflectionMethod;

/**
 * Uniform access to the six 3.3.7 slug implementations (design 11.1).
 *
 * This is characterization of code that is about to be replaced by a shared Slugger, so the
 * non-public methods are called via reflection. The instances are created WITHOUT running their
 * constructors: all six methods are pure functions of their argument and touch no instance state,
 * so no container, logger or event manager is needed.
 */
final class SlugImplementations
{
    public const CATEGORIES = 'categories';
    public const TEMPLATE = 'template';
    public const ROBOTS = 'robots';
    public const RSS = 'rss';
    public const FILE_DISCOVERY = 'fileDiscovery';
    public const CONTENT_CREATOR = 'contentCreator';

    /** @var list<string> */
    public const ALL = [
        self::CATEGORIES,
        self::TEMPLATE,
        self::ROBOTS,
        self::RSS,
        self::FILE_DISCOVERY,
        self::CONTENT_CREATOR,
    ];

    /** @var list<string> the four implementations that name category directories/URLs */
    public const CATEGORY_IMPLEMENTATIONS = [self::CATEGORIES, self::TEMPLATE, self::ROBOTS, self::RSS];

    /**
     * Locale used for ContentCreatorCommand::slugify. iconv //TRANSLIT depends on LC_CTYPE: in the
     * "C" locale every non-ASCII character becomes "?", under a UTF-8 locale "é" becomes "e".
     * C.UTF-8 exists in the lando container (glibc, listed by `locale -a` as "C.utf8"), so it is
     * the pinned locale; "C.utf8" is the same locale under its other spelling.
     */
    private const LOCALE_CANDIDATES = ['C.UTF-8', 'C.utf8'];

    /**
     * Pins LC_CTYPE for the ContentCreator expectations. Returns the locale name, or null if no
     * candidate exists on this machine (callers then skip: the frozen values would be meaningless).
     */
    public static function pinLocale(): ?string
    {
        foreach (self::LOCALE_CANDIDATES as $candidate) {
            $applied = setlocale(LC_CTYPE, $candidate);
            if (is_string($applied)) {
                return $applied;
            }
        }

        return null;
    }

    public static function currentLocale(): string
    {
        $current = setlocale(LC_CTYPE, '0');

        return is_string($current) ? $current : 'C';
    }

    public static function restoreLocale(string $locale): void
    {
        setlocale(LC_CTYPE, $locale);
    }

    /**
     * @param-out int $notices number of PHP notices/warnings the call raised (iconv on invalid UTF-8)
     */
    public static function slug(string $implementation, string $input, ?int &$notices = null): string
    {
        [$class, $method] = match ($implementation) {
            self::CATEGORIES => [CategoriesService::class, 'sanitizeCategoryName'],
            self::TEMPLATE => [TemplateRenderer::class, 'slugifyCategory'],
            self::ROBOTS => [RobotsTxtService::class, 'sanitizeCategoryName'],
            self::RSS => [RssFeedService::class, 'sanitizeCategoryName'],
            self::FILE_DISCOVERY => [FileDiscovery::class, 'slugify'],
            self::CONTENT_CREATOR => [ContentCreatorCommand::class, 'slugify'],
            default => throw new \InvalidArgumentException("Unknown implementation {$implementation}"),
        };

        $object = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $reflection = new ReflectionMethod($class, $method);

        $count = 0;
        set_error_handler(static function () use (&$count): bool {
            $count++;

            return true;
        });
        try {
            $result = $reflection->invoke($object, $input);
        } finally {
            restore_error_handler();
        }
        $notices = $count;

        if (!is_string($result)) {
            throw new \UnexpectedValueException("{$class}::{$method} did not return a string");
        }

        return $result;
    }
}
