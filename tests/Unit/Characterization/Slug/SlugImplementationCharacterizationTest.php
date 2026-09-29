<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Characterization\Slug;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Freezes, per implementation, the 3.3.7 output for the shared SlugCorpus (design 11.1 / TEST-9).
 * The expected values are literals in SlugCorpus, not golden files. One test method per
 * implementation; ContentCreatorCommand::slugify runs with LC_CTYPE pinned (see SlugImplementations).
 *
 * @phpstan-import-type SlugCase from SlugCorpus
 */
class SlugImplementationCharacterizationTest extends TestCase
{
    private string $originalLocale = 'C';

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLocale = SlugImplementations::currentLocale();
    }

    protected function tearDown(): void
    {
        SlugImplementations::restoreLocale($this->originalLocale);
        parent::tearDown();
    }

    /**
     * @return array<string, array{string, string}>
     */
    private static function pairs(string $implementation): array
    {
        $out = [];
        foreach (SlugCorpus::cases() as $name => $case) {
            $out[$name] = [$case['input'], $case[$implementation]];
        }

        return $out;
    }

    /** @return array<string, array{string, string}> */
    public static function categoriesProvider(): array
    {
        return self::pairs(SlugImplementations::CATEGORIES);
    }

    /** @return array<string, array{string, string}> */
    public static function templateProvider(): array
    {
        return self::pairs(SlugImplementations::TEMPLATE);
    }

    /** @return array<string, array{string, string}> */
    public static function robotsProvider(): array
    {
        return self::pairs(SlugImplementations::ROBOTS);
    }

    /** @return array<string, array{string, string}> */
    public static function rssProvider(): array
    {
        return self::pairs(SlugImplementations::RSS);
    }

    /** @return array<string, array{string, string}> */
    public static function fileDiscoveryProvider(): array
    {
        return self::pairs(SlugImplementations::FILE_DISCOVERY);
    }

    /** @return array<string, array{string, string}> */
    public static function contentCreatorProvider(): array
    {
        return self::pairs(SlugImplementations::CONTENT_CREATOR);
    }

    #[DataProvider('categoriesProvider')]
    public function testCategoriesServiceSanitizeCategoryNameMatchesFrozenOutput(string $input, string $expected): void
    {
        $this->assertSame($expected, SlugImplementations::slug(SlugImplementations::CATEGORIES, $input));
    }

    #[DataProvider('templateProvider')]
    public function testTemplateRendererSlugifyCategoryMatchesFrozenOutput(string $input, string $expected): void
    {
        $this->assertSame($expected, SlugImplementations::slug(SlugImplementations::TEMPLATE, $input));
    }

    #[DataProvider('robotsProvider')]
    public function testRobotsTxtServiceSanitizeCategoryNameMatchesFrozenOutput(string $input, string $expected): void
    {
        $this->assertSame($expected, SlugImplementations::slug(SlugImplementations::ROBOTS, $input));
    }

    #[DataProvider('rssProvider')]
    public function testRssFeedServiceSanitizeCategoryNameMatchesFrozenOutput(string $input, string $expected): void
    {
        $this->assertSame($expected, SlugImplementations::slug(SlugImplementations::RSS, $input));
    }

    #[DataProvider('fileDiscoveryProvider')]
    public function testFileDiscoverySlugifyMatchesFrozenOutput(string $input, string $expected): void
    {
        $this->assertSame($expected, SlugImplementations::slug(SlugImplementations::FILE_DISCOVERY, $input));
    }

    #[DataProvider('contentCreatorProvider')]
    public function testContentCreatorSlugifyMatchesFrozenOutputUnderPinnedLocale(string $input, string $expected): void
    {
        if (SlugImplementations::pinLocale() === null) {
            $this->markTestSkipped(
                'Neither C.UTF-8 nor C.utf8 is installed; ContentCreatorCommand::slugify (iconv //TRANSLIT) '
                . 'is locale dependent, so the frozen values cannot be verified on this machine.'
            );
        }

        $this->assertSame($expected, SlugImplementations::slug(SlugImplementations::CONTENT_CREATOR, $input));
    }

    public function testContentCreatorTransliterationDependsOnLocale(): void
    {
        if (SlugImplementations::pinLocale() === null) {
            $this->markTestSkipped('No UTF-8 locale available to compare against the C locale.');
        }
        $accented = "Caf\u{00E9} Cr\u{00E8}me";

        $pinned = SlugImplementations::slug(SlugImplementations::CONTENT_CREATOR, $accented);
        setlocale(LC_CTYPE, 'C');
        $cLocale = SlugImplementations::slug(SlugImplementations::CONTENT_CREATOR, $accented);

        $this->assertSame('cafe-creme', $pinned);
        $this->assertNotSame($pinned, $cLocale, 'documents why the locale must be pinned in tests');
    }
}
