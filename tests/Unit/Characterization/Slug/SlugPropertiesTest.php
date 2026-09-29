<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Characterization\Slug;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Properties of the CURRENT implementations over the shared corpus. Each is limited to what holds
 * in 3.3.7; the ones that Slugger (design 11.2) will deliberately change are marked
 * "WILL CHANGE WITH SLUGGER" and must be revisited, not silently deleted, during the migration.
 */
class SlugPropertiesTest extends TestCase
{
    private string $originalLocale = 'C';

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLocale = SlugImplementations::currentLocale();
        // Only ContentCreator output depends on this; harmless if unavailable.
        SlugImplementations::pinLocale();
    }

    protected function tearDown(): void
    {
        SlugImplementations::restoreLocale($this->originalLocale);
        parent::tearDown();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function inputProvider(): array
    {
        $out = [];
        foreach (SlugCorpus::cases() as $name => $case) {
            $out[$name] = [$case['input']];
        }

        return $out;
    }

    #[DataProvider('inputProvider')]
    public function testNoImplementationEmitsPathSeparatorsDotsOrNulBytes(string $input): void
    {
        foreach (SlugImplementations::ALL as $implementation) {
            $slug = SlugImplementations::slug($implementation, $input);

            $this->assertStringNotContainsString('.', $slug, $implementation);
            $this->assertStringNotContainsString('/', $slug, $implementation);
            $this->assertStringNotContainsString('\\', $slug, $implementation);
            $this->assertStringNotContainsString("\0", $slug, $implementation);
        }
    }

    #[DataProvider('inputProvider')]
    public function testCategoryImplementationsOnlyEmitLowercaseAlphanumericsAndSingleHyphens(string $input): void
    {
        foreach (SlugImplementations::CATEGORY_IMPLEMENTATIONS as $implementation) {
            $slug = SlugImplementations::slug($implementation, $input);

            // Empty is permitted TODAY.
            // WILL CHANGE WITH SLUGGER: Slugger::category() throws InvalidSlugException on an empty
            // result (design 11.2), so the "empty is allowed" half of this assertion goes away.
            $this->assertMatchesRegularExpression('/^([a-z0-9]+(-[a-z0-9]+)*)?$/', $slug, $implementation);
        }
    }

    #[DataProvider('inputProvider')]
    public function testCategoriesTemplateAndRobotsImplementationsAlwaysAgree(string $input): void
    {
        $canonical = SlugImplementations::slug(SlugImplementations::CATEGORIES, $input);

        $this->assertSame($canonical, SlugImplementations::slug(SlugImplementations::TEMPLATE, $input));
        $this->assertSame($canonical, SlugImplementations::slug(SlugImplementations::ROBOTS, $input));
    }

    #[DataProvider('inputProvider')]
    public function testRssImplementationEqualsCanonicalExceptItSubstitutesCategoryForEmpty(string $input): void
    {
        $canonical = SlugImplementations::slug(SlugImplementations::CATEGORIES, $input);
        $rss = SlugImplementations::slug(SlugImplementations::RSS, $input);

        // Holds after Slugger too: rssCategory() keeps the 'category' fallback (design 11.2).
        $this->assertSame($canonical === '' ? 'category' : $canonical, $rss);
        $this->assertNotSame('', $rss);
    }

    public function testCanonicalImplementationReturnsEmptyStringForUnusableCategoryNames(): void
    {
        $unusable = ['empty', 'only_spaces', 'all_symbols', 'symbol_mix', 'only_hyphens', 'single_dot', 'double_dot',
            'nul_only', 'emoji_only', 'cjk', 'rtl_arabic', 'rtl_hebrew'];

        foreach (SlugCorpus::cases() as $name => $case) {
            $slug = SlugImplementations::slug(SlugImplementations::CATEGORIES, $case['input']);
            if (in_array($name, $unusable, true)) {
                // WILL CHANGE WITH SLUGGER: category() throws InvalidSlugException instead of
                // returning '' (SEC-10 / P2 security fix, not a URL change: no valid URL existed).
                $this->assertSame('', $slug, $name);
            } else {
                $this->assertNotSame('', $slug, $name);
            }
        }
    }

    public function testContentCreatorNeverReturnsEmptyAndFallsBackToUntitled(): void
    {
        if (SlugImplementations::pinLocale() === null) {
            $this->markTestSkipped('No C.UTF-8 locale available.');
        }

        foreach (SlugCorpus::cases() as $name => $case) {
            $slug = SlugImplementations::slug(SlugImplementations::CONTENT_CREATOR, $case['input']);
            $this->assertNotSame('', $slug, $name);
        }
        $this->assertSame('untitled', SlugImplementations::slug(SlugImplementations::CONTENT_CREATOR, '!!!'));
    }

    public function testCategoryImplementationsAreIdempotent(): void
    {
        foreach (SlugCorpus::cases() as $name => $case) {
            $once = SlugImplementations::slug(SlugImplementations::CATEGORIES, $case['input']);

            $this->assertSame($once, SlugImplementations::slug(SlugImplementations::CATEGORIES, $once), $name);
        }
    }
}
