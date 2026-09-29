<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services;

use EICC\StaticForge\Exceptions\InvalidSlugException;
use EICC\StaticForge\Features\Categories\Services\CategoriesService;
use EICC\StaticForge\Features\RssFeed\Services\RssFeedService;
use EICC\StaticForge\Services\Slugger;
use EICC\StaticForge\Tests\Unit\Characterization\Slug\SlugCorpus;
use EICC\StaticForge\Tests\Unit\Characterization\Slug\SlugImplementations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class SluggerTest extends TestCase
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
    private static function pairs(string $column): array
    {
        $out = [];
        foreach (SlugCorpus::cases() as $name => $case) {
            $out[$name] = [$case['input'], $case[$column]];
        }

        return $out;
    }

    /** @return array<string, array{string, string}> */
    public static function categoryProvider(): array
    {
        return self::pairs('categories');
    }

    /** @return array<string, array{string, string}> */
    public static function rssProvider(): array
    {
        return self::pairs('rss');
    }

    /** @return array<string, array{string, string}> */
    public static function filenameProvider(): array
    {
        return self::pairs('contentCreator');
    }

    /** @return array<string, array{string}> */
    public static function inputProvider(): array
    {
        $out = [];
        foreach (SlugCorpus::cases() as $name => $case) {
            $out[$name] = [$case['input']];
        }

        return $out;
    }

    #[DataProvider('categoryProvider')]
    public function testCategoryMatchesFrozenCategoriesOutputOrThrowsWhenUnusable(
        string $input,
        string $expected
    ): void {
        if ($expected === '') {
            $this->expectException(InvalidSlugException::class);
            Slugger::category($input);

            return;
        }

        $this->assertSame($expected, Slugger::category($input));
    }

    #[DataProvider('rssProvider')]
    public function testRssCategoryMatchesFrozenRssOutputAndNeverThrows(string $input, string $expected): void
    {
        $this->assertSame($expected, Slugger::rssCategory($input));
    }

    #[DataProvider('filenameProvider')]
    public function testFilenameMatchesFrozenContentCreatorOutputUnderPinnedLocale(
        string $input,
        string $expected
    ): void {
        if (SlugImplementations::pinLocale() === null) {
            $this->markTestSkipped('No C.UTF-8 locale available; iconv //TRANSLIT output is locale dependent.');
        }

        set_error_handler(static fn (): bool => true);
        try {
            $actual = Slugger::filename($input);
        } finally {
            restore_error_handler();
        }

        $this->assertSame($expected, $actual);
    }

    #[DataProvider('inputProvider')]
    public function testCategoryOutputIsSafeStrictSlugAndIdempotent(string $input): void
    {
        try {
            $slug = Slugger::category($input);
        } catch (InvalidSlugException $e) {
            $this->assertNotSame('', $e->getMessage());

            return;
        }

        $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug);
        $this->assertStringNotContainsString('.', $slug);
        $this->assertStringNotContainsString('..', $slug);
        $this->assertStringNotContainsString('/', $slug);
        $this->assertStringNotContainsString('\\', $slug);
        $this->assertStringNotContainsString("\0", $slug);
        $this->assertSame($slug, Slugger::category($slug));
    }

    /** @return array<string, array{string}> */
    public static function hostileProvider(): array
    {
        return [
            'invalid utf8 only' => ["\xff\xfe"],
            'invalid utf8 mixed' => ["ab\xc3\x28cd"],
            'latin1 e-acute' => ["caf\xe9"],
            'nul only' => ["\0"],
            'nul inside' => ["a\0b"],
            'nul path traversal' => ["..\0/.."],
        ];
    }

    #[DataProvider('hostileProvider')]
    public function testCategoryEitherThrowsOrReturnsSafeSlugForHostileBytes(string $input): void
    {
        try {
            $slug = Slugger::category($input);
        } catch (InvalidSlugException $e) {
            $this->assertNotSame('', $e->getMessage());

            return;
        }

        $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug);
    }

    #[DataProvider('hostileProvider')]
    public function testRssCategoryNeverReturnsUnsafeValueForHostileBytes(string $input): void
    {
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', Slugger::rssCategory($input));
    }

    public function testInvalidSlugExceptionIsAnInvalidArgumentException(): void
    {
        $this->assertInstanceOf(\InvalidArgumentException::class, new InvalidSlugException('x'));
    }

    public function testCategoriesServiceReturnsEmptyStringInsteadOfThrowingForUnusableName(): void
    {
        $service = (new ReflectionClass(CategoriesService::class))->newInstanceWithoutConstructor();

        $this->assertSame('', $service->sanitizeCategoryName('!!!'));
        $this->assertSame('', $service->sanitizeCategoryName('..'));
        $this->assertSame('', $service->sanitizeCategoryName(''));
        $this->assertSame('my-blog', $service->sanitizeCategoryName('My Blog!'));
    }

    public function testRssFeedServiceFallsBackToCategoryForSymbolOnlyName(): void
    {
        $service = (new ReflectionClass(RssFeedService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(RssFeedService::class, 'sanitizeCategoryName');

        $this->assertSame('category', $method->invoke($service, '!!!'));
        $this->assertSame('category', $method->invoke($service, ''));
        $this->assertSame('news-item', $method->invoke($service, 'News & Item'));
    }
}
