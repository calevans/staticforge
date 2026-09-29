<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration\SiteFeed;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * SEC2-9: feed_links values are autoescaped by the shipped base templates and sit in <head>.
 */
class SiteFeedAutodiscoveryTest extends SiteFeedTestCase
{
    private const PAYLOAD = '"><script>alert(1)</script>';

    /**
     * @return array<string, array{string}>
     */
    public static function themes(): array
    {
        return ['sample' => ['sample'], 'staticforce' => ['staticforce']];
    }

    private function head(string $html): string
    {
        if (preg_match('~<head\b.*?</head>~is', $html, $m) !== 1) {
            $this->fail('page needs a <head>');
        }

        return $m[0];
    }

    #[DataProvider('themes')]
    public function testHostileTitleAndHrefAreEscapedAndLinksSitInsideHead(string $theme): void
    {
        $this->category('blog');
        $this->page('post.md', ['title' => 'Post', 'category' => 'Blog', 'date' => '2024-01-01']);

        $this->render(
            ['enabled' => true],
            ['site' => ['name' => 'Evil' . self::PAYLOAD]],
            baseUrl: 'https://feed.example.com/x' . self::PAYLOAD . '/',
            template: $theme,
            templateDir: dirname(__DIR__, 3) . '/templates'
        );

        $html = $this->read('blog/post.html');
        $head = $this->head($html);

        $this->assertStringNotContainsString(self::PAYLOAD, $html, 'raw payload must not reach the page');
        $this->assertStringNotContainsString('<script>alert(1)', $html);

        $this->assertSame(3, preg_match_all('~<link rel="alternate" type="([^"]+)" title="([^"]*)" href="([^"]*)">~', $head, $links, PREG_SET_ORDER));
        $this->assertSame(
            ['application/rss+xml', 'application/atom+xml', 'application/feed+json'],
            array_column($links, 1)
        );
        foreach ($links as $link) {
            $this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $link[2]);
            $this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $link[3]);
        }
    }

    #[DataProvider('themes')]
    public function testNoAlternateLinksAreRenderedWhenFeedsAreOff(string $theme): void
    {
        $this->category('blog');
        $this->page('post.md', ['title' => 'Post', 'category' => 'Blog', 'date' => '2024-01-01']);

        $this->render(null, [], template: $theme, templateDir: dirname(__DIR__, 3) . '/templates');

        $this->assertStringNotContainsString('rel="alternate"', $this->head($this->read('blog/post.html')));
    }
}
