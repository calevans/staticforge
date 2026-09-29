<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration\SiteFeed;

use DOMElement;

/**
 * Freezes the bytes of /feed.xml, /feed.atom and /feed.json for a fixture site.
 *
 * Nothing is normalised: every date in the feeds derives from frontmatter (the RSS
 * lastBuildDate and Atom updated are the newest item's `updated`), and the timezone is pinned
 * to UTC. Regenerate deliberately, then review the diff:
 *     lando ssh -c "env UPDATE_GOLDEN=1 vendor/bin/phpunit tests/Integration/SiteFeed/SiteFeedGoldenTest.php"
 */
class SiteFeedGoldenTest extends SiteFeedTestCase
{
    private const GOLDEN_DIR = __DIR__ . '/../Golden/site-feed';

    protected function setUp(): void
    {
        parent::setUp();

        $this->category('blog');
        $this->page('podcast.md', ['title' => 'Podcast', 'type' => 'category', 'podcast' => true], '');
        $this->page('blog-1.md', ['title' => 'First Post', 'category' => 'Blog', 'date' => '2024-01-01'], "First body.\n");
        $this->page('blog-2.md', [
            'title' => 'Second Post',
            'category' => 'Blog',
            'date' => '2024-02-01',
            'author' => 'Ada Lovelace',
            'tags' => ['php', 'feeds'],
        ], "Second body with **bold**.\n\n![Logo](/assets/logo.png)\n\n[Home](/about.html)\n");
        $this->page(
            'blog-unicode.md',
            ['title' => 'Cafe ☕ 日本語 🎉 & <Friends>', 'category' => 'Blog', 'date' => '2024-04-01'],
            "Unicode: café ☕ 日本語 🎉 — “quotes” and more.\n"
        );
        $this->page('standalone.md', [
            'title' => 'Standalone',
            'feed' => true,
            'date' => '2024-05-01',
            'updated' => '2024-06-01',
            'description' => 'A page that opted in.',
        ], "Standalone body.\n");
        $this->page('ep-1.md', ['title' => 'Episode One', 'category' => 'Podcast', 'date' => '2024-03-03']);
        $this->page('undated.md', ['title' => 'Undated', 'category' => 'Blog']);
        $this->page('hidden.md', ['title' => 'Opted Out', 'category' => 'Blog', 'date' => '2024-03-01', 'feed' => false]);
    }

    private function build(): void
    {
        $this->render(['enabled' => true]);
    }

    public function testSiteRssFeedMatchesGolden(): void
    {
        $this->build();

        $this->assertGolden('feed.xml', $this->read('feed.xml'));
    }

    public function testSiteAtomFeedMatchesGolden(): void
    {
        $this->build();

        $this->assertGolden('feed.atom', $this->read('feed.atom'));
    }

    public function testSiteJsonFeedMatchesGolden(): void
    {
        $this->build();

        $this->assertGolden('feed.json', $this->read('feed.json'));
    }

    public function testRebuildProducesByteIdenticalFeeds(): void
    {
        $this->build();
        $first = [$this->read('feed.xml'), $this->read('feed.atom'), $this->read('feed.json')];
        $this->removeDirectory($this->outputDir);
        mkdir($this->outputDir, 0755, true);

        $this->build();

        $this->assertSame($first, [$this->read('feed.xml'), $this->read('feed.atom'), $this->read('feed.json')]);
    }

    public function testJsonFeedCarriesTheFieldsJsonFeed11Requires(): void
    {
        $this->build();
        $feed = $this->json();

        $this->assertSame('https://jsonfeed.org/version/1.1', $feed['version']);
        $this->assertNotSame('', $feed['title']);
        $this->assertSame('https://feed.example.com/feed.json', $feed['feed_url']);
        $this->assertNotEmpty($feed['items']);
        $ids = [];
        foreach ($feed['items'] as $item) {
            $this->assertIsString($item['id']);
            $this->assertNotSame('', $item['id']);
            $this->assertTrue(isset($item['content_html']) || isset($item['content_text']));
            $ids[] = $item['id'];
        }
        $this->assertSame($ids, array_values(array_unique($ids)), 'item ids must be unique');
    }

    public function testAtomFeedCarriesTheElementsRfc4287Requires(): void
    {
        $this->build();
        $dom = $this->xml('feed.atom');
        $ns = 'http://www.w3.org/2005/Atom';
        $root = $dom->documentElement;
        $this->assertInstanceOf(DOMElement::class, $root);
        $this->assertSame('feed', $root->localName);
        $this->assertSame($ns, $root->namespaceURI);

        foreach (['id', 'title', 'updated'] as $required) {
            $found = 0;
            foreach ($root->childNodes as $child) {
                $found += ($child instanceof DOMElement && $child->localName === $required && $child->textContent !== '') ? 1 : 0;
            }
            $this->assertSame(1, $found, "feed needs exactly one <{$required}>");
        }

        $entries = $dom->getElementsByTagNameNS($ns, 'entry');
        $this->assertGreaterThan(0, $entries->length);
        foreach ($entries as $entry) {
            $this->assertInstanceOf(DOMElement::class, $entry);
            foreach (['id', 'title', 'updated'] as $required) {
                $this->assertSame(1, $entry->getElementsByTagNameNS($ns, $required)->length, "entry needs <{$required}>");
                $this->assertNotSame('', $entry->getElementsByTagNameNS($ns, $required)->item(0)?->textContent);
            }
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d[+-]\d\d:\d\d$/',
                (string) $entry->getElementsByTagNameNS($ns, 'updated')->item(0)?->textContent
            );
        }
    }

    public function testRssFeedIsWellFormedRss2WithAChannel(): void
    {
        $this->build();
        $dom = $this->xml('feed.xml');

        $this->assertSame('rss', $dom->documentElement?->localName);
        $this->assertSame('2.0', $dom->documentElement->getAttribute('version'));
        $this->assertSame(1, $dom->getElementsByTagName('channel')->length);
        foreach (['title', 'link', 'description'] as $required) {
            $this->assertNotSame('', $dom->getElementsByTagName($required)->item(0)?->textContent);
        }
    }

    public function testFeedsIncludeExactlyTheEligiblePagesNewestFirst(): void
    {
        $this->build();

        $expected = ['Standalone', 'Cafe ☕ 日本語 🎉 & <Friends>', 'Second Post', 'First Post'];
        $this->assertSame($expected, $this->rssTitles());
        $this->assertSame($expected, $this->atomTitles());
        $this->assertSame($expected, $this->jsonTitles());
    }

    private function assertGolden(string $name, string $actual): void
    {
        $path = self::GOLDEN_DIR . '/' . $name;

        if (getenv('UPDATE_GOLDEN') === '1') {
            if (!is_dir(self::GOLDEN_DIR)) {
                mkdir(self::GOLDEN_DIR, 0755, true);
            }
            file_put_contents($path, $actual);
        }

        $this->assertFileExists($path, 'golden missing; regenerate with UPDATE_GOLDEN=1');
        $this->assertSame(file_get_contents($path), $actual, "feed differs from golden {$name}");
    }
}
