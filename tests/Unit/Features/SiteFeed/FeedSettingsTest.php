<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\SiteFeed;

use EICC\StaticForge\Features\SiteFeed\Models\FeedSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FeedSettingsTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function absentOrNotAMapping(): array
    {
        return [
            'null' => [null],
            'string' => ['yes'],
            'true bool' => [true],
            'list' => [['enabled']],
        ];
    }

    #[DataProvider('absentOrNotAMapping')]
    public function testMissingOrNonMappingConfigIsDisabledWithDefaults(mixed $raw): void
    {
        $settings = FeedSettings::fromConfig($raw);

        $this->assertFalse($settings->enabled);
        $this->assertSame(20, $settings->limit);
        $this->assertSame(['rss', 'atom', 'json'], $settings->formats);
        $this->assertSame(['rss'], $settings->categoryFormats);
        $this->assertSame([], $settings->excludeCategories);
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function enabledValues(): array
    {
        return [
            'enabled true' => [true, true],
            '"true"' => ['true', true],
            '"yes"' => ['yes', true],
            'enabled 1' => [1, true],
            'enabled false' => [false, false],
            '"false"' => ['false', false],
            '"no"' => ['no', false],
            'enabled null' => [null, false],
            'enabled array' => [[], false],
            'enabled garbage' => ['maybe', false],
        ];
    }

    #[DataProvider('enabledValues')]
    public function testEnabledUsesTheSameBooleanParsingAsPageFlags(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, FeedSettings::fromConfig(['enabled' => $value])->enabled);
    }

    /**
     * @return array<string, array{mixed, int, bool}>
     */
    public static function limits(): array
    {
        return [
            'positive int' => [5, 5, false],
            'one' => [1, 1, false],
            'zero' => [0, 20, true],
            'negative' => [-3, 20, true],
            'string' => ['abc', 20, true],
            'numeric string' => ['10', 20, true],
            'float' => [3.0, 20, true],
            'null limit' => [null, 20, true],
            'list' => [[1], 20, true],
        ];
    }

    #[DataProvider('limits')]
    public function testLimitMustBeAPositiveIntegerElseDefaultWithWarning(mixed $limit, int $expected, bool $warns): void
    {
        $settings = FeedSettings::fromConfig(['enabled' => true, 'limit' => $limit]);

        $this->assertSame($expected, $settings->limit);
        $this->assertSame($warns, $settings->warnings !== []);
    }

    public function testFormatsAreLowercasedTrimmedDeduplicatedAndUnknownOnesWarn(): void
    {
        $settings = FeedSettings::fromConfig(['formats' => [' RSS ', 'atom', 'atom', 'rdf', 5, null]]);

        $this->assertSame(['rss', 'atom'], $settings->formats);
        $this->assertCount(3, $settings->warnings);
    }

    public function testEmptyFormatListIsHonouredAsNoFormats(): void
    {
        $settings = FeedSettings::fromConfig(['formats' => []]);

        $this->assertSame([], $settings->formats);
        $this->assertSame([], $settings->warnings);
    }

    public function testFormatsGivenAsAStringFallBackToDefaultWithWarning(): void
    {
        $settings = FeedSettings::fromConfig(['formats' => 'rss', 'category_formats' => 'atom']);

        $this->assertSame(['rss', 'atom', 'json'], $settings->formats);
        $this->assertSame(['rss'], $settings->categoryFormats);
        $this->assertCount(2, $settings->warnings);
    }

    public function testExcludeCategoriesAreSluggedDeduplicatedAndUnusableEntriesWarn(): void
    {
        $settings = FeedSettings::fromConfig([
            'exclude_categories' => ['News & Views', 'news-views', 'PODCAST', '!!!', '', '日本語', 12],
        ]);

        $this->assertSame(['news-views', 'podcast', '12'], $settings->excludeCategories);
        $this->assertCount(3, $settings->warnings);
    }

    public function testExcludeCategoriesThatIsNotAListWarnsAndExcludesNothing(): void
    {
        $settings = FeedSettings::fromConfig(['exclude_categories' => 'podcast']);

        $this->assertSame([], $settings->excludeCategories);
        $this->assertCount(1, $settings->warnings);
    }
}
