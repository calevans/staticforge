<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Search;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Verifies the `dedupe_pages` expression used by templates/staticforce/base.html.twig
 * to pass the dedupe option through to StaticForgeSearch.init(), without needing to
 * render the full themed page.
 */
class SearchDedupeConfigTest extends UnitTestCase
{
    private const EXPRESSION = "dedupePages: {{ (search.dedupe_pages ?? true) ? 'true' : 'false' }}";

    private function render(array $search): string
    {
        $twig = new Environment(new ArrayLoader(['snippet' => self::EXPRESSION]));
        return $twig->render('snippet', ['search' => $search]);
    }

    public function testDefaultsToTrueWhenUnset(): void
    {
        $this->assertSame('dedupePages: true', $this->render([]));
    }

    public function testHonorsExplicitTrue(): void
    {
        $this->assertSame('dedupePages: true', $this->render(['dedupe_pages' => true]));
    }

    public function testHonorsExplicitFalse(): void
    {
        $this->assertSame('dedupePages: false', $this->render(['dedupe_pages' => false]));
    }

    public function testBaseTemplateContainsDedupeExpressionForBothEngines(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 4) . '/templates/staticforce/base.html.twig'
        );
        $this->assertIsString($template);

        $this->assertSame(
            2,
            substr_count($template, self::EXPRESSION),
            'Expected the dedupe expression once per engine branch (fuse and minisearch).'
        );
    }
}
