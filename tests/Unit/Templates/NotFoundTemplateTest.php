<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Templates;

use EICC\StaticForge\Services\TemplateRenderer;
use EICC\StaticForge\Services\TemplateVariableBuilder;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class NotFoundTemplateTest extends UnitTestCase
{
    /**
     * @param array<string, mixed> $siteConfig
     */
    private function render(string $theme, string $baseUrl, array $siteConfig = []): string
    {
        $this->setContainerVariable('TEMPLATE_DIR', dirname(__DIR__, 3) . '/templates');
        $this->setContainerVariable('TEMPLATE', $theme);
        $this->setContainerVariable('SITE_NAME', 'Test Site');
        $this->setContainerVariable('SITE_BASE_URL', $baseUrl);
        $this->setContainerVariable('site_config', array_merge(['site' => ['name' => 'Test Site']], $siteConfig));

        $renderer = new TemplateRenderer(new TemplateVariableBuilder(), $this->container->get('logger'), null);

        return $renderer->render(
            [
                'metadata' => ['template' => '404', 'noindex' => true, 'description' => 'Gone'],
                'content' => '<p>Sorry.</p>',
                'title' => 'Page not found',
            ],
            $this->container
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function themeProvider(): array
    {
        return ['staticforce' => ['staticforce'], 'sample' => ['sample']];
    }

    /**
     * @return list<string> every href/src attribute value
     */
    private function urls(string $html): array
    {
        preg_match_all('/\b(?:href|src)="([^"]*)"/', $html, $m);

        return $m[1];
    }

    /**
     * @return list<string> hrefs of the links inside the "Helpful links" nav
     */
    private function navHrefs(string $html): array
    {
        $this->assertSame(1, preg_match('#<nav[^>]*aria-label="Helpful links".*?</nav>#s', $html, $nav));
        preg_match_all('/<a href="([^"]*)"/', $nav[0] ?? '', $m);

        return $m[1];
    }

    #[DataProvider('themeProvider')]
    public function testRendersExactlyOneH1(string $theme): void
    {
        $html = $this->render($theme, 'https://example.com/');

        $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html));
        $this->assertStringContainsString('<h1>Page not found</h1>', $html);
    }

    #[DataProvider('themeProvider')]
    public function testEmitsNoindexMetaInsideHead(string $theme): void
    {
        $html = $this->render($theme, 'https://example.com/');
        $meta = '<meta name="robots" content="noindex, follow">';

        $this->assertSame(1, substr_count($html, $meta));
        $this->assertLessThan((int) strpos($html, '</head>'), (int) strpos($html, $meta));
    }

    #[DataProvider('themeProvider')]
    public function testHasNoCanonicalOrMetaRefresh(string $theme): void
    {
        $html = $this->render($theme, 'https://example.com/');

        $this->assertDoesNotMatchRegularExpression('/rel="canonical"/i', $html);
        $this->assertDoesNotMatchRegularExpression('/http-equiv="refresh"/i', $html);
    }

    public function testStaticforceHasExactlyOneSearchInputAndNoDuplicateIds(): void
    {
        $html = $this->render('staticforce', 'https://example.com/');

        $this->assertSame(1, substr_count($html, 'id="search-input"'));
        $this->assertSame(1, substr_count($html, 'id="search-results"'));

        preg_match_all('/\sid="([^"]+)"/', $html, $m);
        $duplicates = array_keys(array_filter(array_count_values($m[1]), static fn (int $n): bool => $n > 1));
        $this->assertSame([], $duplicates);
    }

    public function testStaticforceHasNoscriptFallbackLinkingToSitemap(): void
    {
        $html = $this->render('staticforce', 'https://example.com/sub/');

        $this->assertSame(1, preg_match('#<noscript>(.*?)</noscript>#s', $html, $m));
        $this->assertStringContainsString('href="https://example.com/sub/sitemap.xml"', $m[1] ?? '');
    }

    public function testStaticforceSearchBoxStartsHiddenUntilScriptRuns(): void
    {
        $html = $this->render('staticforce', 'https://example.com/');

        $this->assertMatchesRegularExpression('/id="error-404-search"\s+hidden/', $html);
    }

    public function testSampleThemeHasNoSearchBlock(): void
    {
        $html = $this->render('sample', 'https://example.com/');

        $this->assertStringNotContainsString('search-input', $html);
        $this->assertStringNotContainsString('type="search"', $html);
    }

    #[DataProvider('themeProvider')]
    public function testAllTemplateBuiltUrlsUseSubPathBaseUrl(string $theme): void
    {
        $base = 'https://example.com/sub/';
        $html = $this->render($theme, $base, ['404_links' => [
            ['title' => 'Docs', 'url' => '/docs/'],
            ['title' => 'Blog', 'url' => 'blog/'],
        ]]);

        $hrefs = $this->navHrefs($html);
        $this->assertSame([$base . 'docs/', $base . 'blog/'], $hrefs);
        $this->assertContains($base, $this->urls($html));
        $this->assertContains($base . 'sitemap.xml', $this->urls($html));

        // The shared staticforce footer hard-codes root-relative links; those are outside the 404 template.
        $withoutFooter = (string) preg_replace('#<footer.*?</footer>#s', '', $html);
        foreach ($this->urls($withoutFooter) as $url) {
            $this->assertStringStartsWith($base, $url);
        }
    }

    #[DataProvider('themeProvider')]
    public function testAbsoluteLinkUrlsAreUsedAsIs(string $theme): void
    {
        $html = $this->render($theme, 'https://example.com/sub/', ['404_links' => [
            ['title' => 'Elsewhere', 'url' => 'https://other.example.org/x'],
        ]]);

        $this->assertSame(['https://other.example.org/x'], $this->navHrefs($html));
    }

    #[DataProvider('themeProvider')]
    public function testPopularSectionsPreferCustomLinksOverMenuTop(string $theme): void
    {
        $html = $this->render($theme, 'https://example.com/', [
            '404_links' => [['title' => 'Custom One', 'url' => '/one/']],
            'menu' => ['top' => ['Menu Item' => '/menu/']],
        ]);

        $this->assertStringContainsString('Custom One', $html);
        $this->assertStringNotContainsString('Menu Item', $html);
    }

    #[DataProvider('themeProvider')]
    public function testPopularSectionsFallBackToMenuTopWhenNoCustomLinks(string $theme): void
    {
        $html = $this->render($theme, 'https://example.com/', [
            'menu' => ['top' => ['Guide' => '/guide/', 'About' => '/about/']],
        ]);

        $this->assertSame(
            ['https://example.com/guide/', 'https://example.com/about/'],
            $this->navHrefs($html)
        );
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function homeOnlyProvider(): array
    {
        $cases = [];
        foreach (['staticforce', 'sample'] as $theme) {
            $cases["$theme no config"] = [$theme, []];
            $cases["$theme empty menu"] = [$theme, ['menu' => ['top' => []]]];
            $cases["$theme empty custom links"] = [$theme, ['404_links' => []]];
            $cases["$theme malformed custom links"] = [$theme, ['404_links' => [['nope' => 'x']]]];
        }

        return $cases;
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('homeOnlyProvider')]
    public function testFallsBackToHomeOnlyWhenNoUsableLinks(string $theme, array $config): void
    {
        $html = $this->render($theme, 'https://example.com/sub/', $config);

        $this->assertSame(['https://example.com/sub/'], $this->navHrefs($html));
        $this->assertStringContainsString('>Home</a>', $html);
    }

    #[DataProvider('themeProvider')]
    public function testHostileLinkTitlesAreAutoescaped(string $theme): void
    {
        $html = $this->render($theme, 'https://example.com/', ['404_links' => [
            ['title' => '<script>alert(1)</script><img src=x onerror=alert(2)>', 'url' => '/x/'],
        ]]);

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    #[DataProvider('themeProvider')]
    public function testHostileLinkUrlCannotBreakOutOfHrefAttribute(string $theme): void
    {
        $html = $this->render($theme, 'https://example.com/', ['404_links' => [
            ['title' => 'x', 'url' => '/a" onmouseover="alert(1)'],
        ]]);

        $this->assertStringNotContainsString('" onmouseover="', $html);
        $this->assertStringContainsString('&quot; onmouseover=&quot;', $html);
    }

    #[DataProvider('themeProvider')]
    public function testJavascriptSchemeLinkUrlIsNeutralisedToBaseRelativePath(string $theme): void
    {
        $base = 'https://example.com/';
        $html = $this->render($theme, $base, ['404_links' => [
            ['title' => 'Evil', 'url' => 'javascript:alert(1)'],
        ]]);

        // Pinned behaviour: it is not an http(s):// URL, so it is prefixed with site_base_url
        // and never rendered as a bare javascript: href.
        $this->assertSame([$base . 'javascript:alert(1)'], $this->navHrefs($html));
        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    #[DataProvider("themeProvider")]
    public function testPathThatMerelyStartsWithHttpIsStillTreatedAsRelative(string $theme): void
    {
        $base = "https://example.com/sub/";
        $html = $this->render($theme, $base, ["404_links" => [
            ["title" => "Httpd docs", "url" => "httpd/"],
        ]]);

        $this->assertSame([$base . "httpd/"], $this->navHrefs($html));
    }}
