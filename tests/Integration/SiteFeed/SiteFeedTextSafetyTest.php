<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration\SiteFeed;

use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\SiteFeed\Events\SiteFeedItemBuildingEvent;

/**
 * SEC-4 / SEC2-9: whatever a page contains, every feed must still parse.
 */
class SiteFeedTextSafetyTest extends SiteFeedTestCase
{
    private const FEEDS = ['feed.xml', 'feed.atom', 'feed.json'];

    private function assertAllFeedsParse(): void
    {
        $this->xml('feed.xml');
        $this->xml('feed.atom');
        $this->json();
    }

    private function assertNoXmlForbiddenControlChars(string $file): void
    {
        $this->assertSame(
            0,
            preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $this->read($file)),
            "{$file} must not contain XML 1.0 forbidden control characters"
        );
    }

    public function testCdataTerminatorAndControlCharsInTitleAndBodyStillYieldValidFeeds(): void
    {
        $this->page(
            'nasty.md',
            ['title' => "Nasty \u{0001}\u{0008}\u{000B}\u{000C}\u{001F} ]]> title", 'feed' => true, 'date' => '2024-01-01'],
            "Body \x01\x08\x0B\x0C\x1F with ]]> terminator\tand tab.\n"
        );

        $this->render(['enabled' => true]);

        $this->assertAllFeedsParse();
        $this->assertNoXmlForbiddenControlChars('feed.xml');
        $this->assertNoXmlForbiddenControlChars('feed.atom');
        $this->assertStringContainsString('Nasty', $this->atomTitles()[0]);
        $this->assertStringContainsString(']]>', $this->atomTitles()[0], 'the terminator is text, escaped by XMLWriter');
        $this->assertStringNotContainsString('<![CDATA[', $this->read('feed.xml'));
        $this->assertStringContainsString(']]>', $this->jsonTitles()[0]);
    }

    public function testNulByteInTitleStillYieldsValidFeeds(): void
    {
        $this->page('nul.md', ['title' => "Nul\u{0000}Title", 'feed' => true, 'date' => '2024-01-01']);

        $this->render(['enabled' => true]);

        $this->assertAllFeedsParse();
        $this->assertSame('NulTitle', $this->atomTitles()[0]);
    }

    public function testEmojiInJsonFeedIsWrittenUnescapedAndRoundTrips(): void
    {
        $this->page('emoji.md', ['title' => 'Party 🎉 日本語', 'feed' => true, 'date' => '2024-01-01'], "Body 🎉\n");

        $this->render(['enabled' => true]);

        $raw = $this->read('feed.json');
        $this->assertStringContainsString('Party 🎉 日本語', $raw);
        $this->assertStringNotContainsString('\ud83c', $raw);
        $this->assertSame('Party 🎉 日本語', $this->jsonTitles()[0]);
        $this->assertStringContainsString('🎉', $this->json()['items'][0]['content_html']);
    }

    public function testInvalidUtf8InEveryItemFieldStillYieldsValidFeeds(): void
    {
        // The YAML parser rejects invalid UTF-8 in frontmatter before a feed sees it, so a
        // listener stands in for any source (shortcode, external feature) that injects some.
        $this->page('p.md', ['title' => 'Placeholder', 'feed' => true, 'date' => '2024-01-01']);

        $this->render(['enabled' => true], beforeRun: static function (EventManager $events): void {
            $events->registerListener('SITE_FEED_ITEM_BUILDING', [new class {
                public function corrupt(SiteFeedItemBuildingEvent $e): void
                {
                    $e->item->title = "T \xFF \xC3\x28 title";
                    $e->item->summary = "S \xFF summary";
                    $e->item->contentHtml = "<p>C \xFF \xE2\x82 body</p>";
                    $e->item->author = "A \xFF";
                    $e->item->tags = ["t \xFF"];
                }
            }, 'corrupt']);
        });

        $this->assertAllFeedsParse();
        foreach (self::FEEDS as $file) {
            $this->assertTrue(mb_check_encoding($this->read($file), 'UTF-8'), "{$file} must be valid UTF-8");
        }
        $this->assertStringContainsString(' title', $this->jsonTitles()[0]);
        $this->assertStringContainsString(' title', $this->atomTitles()[0]);
    }

    public function testSubPathSiteBaseUrlAbsolutizesAssetsAndItemIds(): void
    {
        $this->category('blog');
        $body = "<p><a href=\"/about.html\">About</a> <a href='/single.html'>S</a> "
            . "<img src=\"/assets/a.png\" srcset=\"/assets/a-1.png 1x, /assets/a-2.png 2x\" alt=\"a\"> "
            . "<a href=\"//cdn.example.net/x\">cdn</a> <a href=\"https://other.example.org/y\">abs</a> "
            . "<a href=\"relative.html\">rel</a></p>\n";
        $this->page('post.md', ['title' => 'Sub Path', 'category' => 'Blog', 'date' => '2024-01-01'], $body);

        $this->render(['enabled' => true, 'category_formats' => ['atom', 'json']], baseUrl: 'https://example.com/sub/');

        $json = $this->json();
        $item = $json['items'][0];
        $html = $item['content_html'];
        $this->assertSame('https://example.com/sub/blog/post.html', $item['id']);
        $this->assertSame('https://example.com/sub/blog/post.html', $item['url']);
        $this->assertSame('https://example.com/sub/feed.json', $json['feed_url']);
        $this->assertSame('https://example.com/sub/', $json['home_page_url']);
        $this->assertStringContainsString('href="https://example.com/sub/about.html"', $html);
        $this->assertStringContainsString("href='https://example.com/sub/single.html'", $html);
        $this->assertStringContainsString('src="https://example.com/sub/assets/a.png"', $html);
        $this->assertStringContainsString(
            'srcset="https://example.com/sub/assets/a-1.png 1x, https://example.com/sub/assets/a-2.png 2x"',
            $html
        );
        $this->assertStringContainsString('href="//cdn.example.net/x"', $html);
        $this->assertStringContainsString('href="https://other.example.org/y"', $html);
        $this->assertStringContainsString('href="relative.html"', $html);

        $this->assertStringContainsString(
            '<guid isPermaLink="true">https://example.com/sub/blog/post.html</guid>',
            $this->read('feed.xml')
        );
        $this->assertStringContainsString('<id>https://example.com/sub/blog/post.html</id>', $this->read('feed.atom'));
        $this->assertStringContainsString('<id>https://example.com/sub/blog/feed.atom</id>', $this->read('blog/feed.atom'));
        $this->assertSame('https://example.com/sub/blog/feed.json', $this->json('blog/feed.json')['feed_url']);
    }
}
