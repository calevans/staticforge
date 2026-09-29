<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Characterization\Html;

use EICC\StaticForge\Core\OutputWriter;
use EICC\StaticForge\Features\Search\Services\SearchIndexService;
use EICC\StaticForge\Services\ContentMarkers;
use EICC\Utils\Container;
use EICC\Utils\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Freezes the 3.3.7 section parsing of SearchIndexService (the private parseHtmlSections, called
 * via reflection because this is characterization of an internal about to be replaced) for the
 * shared HTML corpus, both with and without the sf:content markers.
 *
 * Each golden is the sections array as pretty JSON (unescaped unicode/slashes, invalid UTF-8
 * substituted) so the golden is diffable and stable.
 */
class SearchSectionCharacterizationTest extends TestCase
{
    use GoldenAssertions;

    private const GROUP_UNMARKED = 'search_unmarked';
    private const GROUP_MARKED = 'search_marked';

    /**
     * @return array<string, array{string}>
     */
    public static function corpusProvider(): array
    {
        return HtmlCorpus::asProvider();
    }

    private function sectionsAsJson(string $html): string
    {
        $service = new SearchIndexService(
            $this->createStub(Log::class),
            $this->createStub(OutputWriter::class),
            new Container()
        );

        $method = new ReflectionMethod($service, 'parseHtmlSections');
        $sections = $method->invoke($service, $html, 'Default Title');

        $json = json_encode(
            $sections,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        $this->assertNotFalse($json);

        return $json;
    }

    #[DataProvider('corpusProvider')]
    public function testUnmarkedPageSectionsMatchFrozenGolden(string $case): void
    {
        $this->assertMatchesGolden(
            self::GROUP_UNMARKED,
            $case,
            $this->sectionsAsJson(HtmlCorpus::cases()[$case])
        );
    }

    #[DataProvider('corpusProvider')]
    public function testMarkedPageSectionsMatchFrozenGolden(string $case): void
    {
        $this->assertMatchesGolden(
            self::GROUP_MARKED,
            $case,
            $this->sectionsAsJson(ContentMarkers::wrap(HtmlCorpus::cases()[$case]))
        );
    }

    public function testGoldenSetsMatchCorpus(): void
    {
        $names = array_keys(HtmlCorpus::cases());
        $this->assertNoOrphanedGoldens(self::GROUP_UNMARKED, $names);
        $this->assertNoOrphanedGoldens(self::GROUP_MARKED, $names);
    }
}
