<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Characterization\Html;

use EICC\StaticForge\Features\TableOfContents\Services\TableOfContentsService;
use EICC\Utils\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Freezes the 3.3.7 output of TableOfContentsService::generateToc for the shared HTML corpus,
 * ahead of the 3.5 HTML parser change.
 */
class TableOfContentsCharacterizationTest extends TestCase
{
    use GoldenAssertions;

    private const GROUP = 'toc';

    /**
     * @return array<string, array{string}>
     */
    public static function corpusProvider(): array
    {
        return HtmlCorpus::asProvider();
    }

    #[DataProvider('corpusProvider')]
    public function testGenerateTocOutputMatchesFrozenGolden(string $case): void
    {
        $service = new TableOfContentsService($this->createStub(Log::class));

        $this->assertMatchesGolden(self::GROUP, $case, $service->generateToc(HtmlCorpus::cases()[$case]));
    }

    public function testGoldenSetMatchesCorpus(): void
    {
        $this->assertNoOrphanedGoldens(self::GROUP, array_keys(HtmlCorpus::cases()));
    }
}
