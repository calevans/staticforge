<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration\SiteFeed;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * When feeds are written, which ones, and proof that a site that never opts in is untouched.
 */
class SiteFeedGatingTest extends SiteFeedTestCase
{
    private const NEW_FEEDS = ['feed.xml', 'feed.atom', 'feed.json'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->category('blog');
        $this->page('post.md', ['title' => 'Post', 'category' => 'Blog', 'date' => '2024-01-01']);
        $this->page('loose.md', ['title' => 'Loose', 'feed' => true, 'date' => '2024-01-02']);
    }

    private function resetOutput(): void
    {
        $this->removeDirectory($this->outputDir);
        mkdir($this->outputDir, 0755, true);
    }

    /**
     * Output files minus the ones that legitimately differ per build (build id, wall-clock RSS date).
     *
     * @return array<string, string>
     */
    private function comparableOutput(): array
    {
        $files = $this->outputManifest();
        unset($files['.staticforge-build']);
        foreach (array_keys($files) as $path) {
            if (str_ends_with($path, 'rss.xml')) {
                $normalised = preg_replace('#<lastBuildDate>[^<]*</lastBuildDate>#', '', $this->read($path));
                $files[$path] = sha1((string) $normalised);
            }
        }

        return $files;
    }

    /**
     * @return array<string, array{array<string, mixed>|null}>
     */
    public static function inertConfigs(): array
    {
        return [
            'key absent' => [null],
            'enabled false' => [['enabled' => false]],
            'enabled missing' => [['limit' => 5]],
            'enabled "false"' => [['enabled' => 'false']],
            'enabled "no"' => [['enabled' => 'no']],
        ];
    }

    /**
     * @param array<string, mixed>|null $feed
     */
    #[DataProvider('inertConfigs')]
    public function testFeatureIsInertWithNoNewFilesAndNoFeedLinksVariable(?array $feed): void
    {
        $this->render($feed);

        foreach (self::NEW_FEEDS as $file) {
            $this->assertFalse($this->has($file), $file);
        }
        $this->assertFalse($this->has('blog/feed.atom'));
        $this->assertFalse($this->has('blog/feed.json'));
        $this->assertTrue($this->has('blog/rss.xml'));
        $this->assertNull($this->advertisedLinks('blog/post.html'));
        $this->assertFalse($this->container->hasVariable('feed_links'));
    }

    public function testDisabledOutputIsIdenticalToOutputWithoutTheKey(): void
    {
        $this->render(null);
        $without = $this->comparableOutput();

        $this->resetOutput();
        $this->render(['enabled' => false, 'limit' => 3, 'formats' => ['atom'], 'category_formats' => ['json']]);
        $disabled = $this->comparableOutput();

        $this->assertSame($without, $disabled);
        $this->assertNotEmpty($without);
    }

    public function testEnablingFeedsOnlyAddsFilesAndLeavesEveryExistingOutputIdentical(): void
    {
        $this->render(null);
        $before = $this->comparableOutput();

        $this->resetOutput();
        $this->render(['enabled' => true, 'category_formats' => ['rss', 'atom', 'json']]);
        $after = $this->comparableOutput();

        $added = array_keys(array_diff_key($after, $before));
        sort($added);
        $this->assertSame(['blog/feed.atom', 'blog/feed.json', 'feed.atom', 'feed.json', 'feed.xml'], $added);
        $changed = array_keys(array_diff_assoc(array_intersect_key($after, $before), $before));
        // Pages gain autodiscovery links (the test template prints them); no other file may change.
        foreach ($changed as $path) {
            $this->assertMatchesRegularExpression('~\.html$|^search\.json$~', $path, "{$path} changed but is not a page");
        }
    }

    public function testEnabledSiteAdvertisesEveryFormatOnItsPages(): void
    {
        $this->render(['enabled' => true]);

        $this->assertSame([
            'application/rss+xml|https://feed.example.com/feed.xml',
            'application/atom+xml|https://feed.example.com/feed.atom',
            'application/feed+json|https://feed.example.com/feed.json',
        ], $this->advertisedLinks('blog/post.html'));
    }

    public function testEmptySiteBaseUrlLogsWarningAndWritesNoFeeds(): void
    {
        $this->render(['enabled' => true], baseUrl: '');

        foreach (self::NEW_FEEDS as $file) {
            $this->assertFalse($this->has($file), $file);
        }
        $this->assertStringContainsString('SITE_BASE_URL is not set', $this->newLog());
        $this->assertNull($this->advertisedLinks('blog/post.html'));
    }

    public function testFormatsSubsetWritesAndAdvertisesOnlyAtom(): void
    {
        $this->render(['enabled' => true, 'formats' => ['atom']]);

        $this->assertTrue($this->has('feed.atom'));
        $this->assertFalse($this->has('feed.xml'));
        $this->assertFalse($this->has('feed.json'));
        $this->assertSame(['application/atom+xml|https://feed.example.com/feed.atom'], $this->advertisedLinks('blog/post.html'));
    }

    public function testFormatNamesAreCaseAndWhitespaceInsensitiveAndDeduplicated(): void
    {
        $this->render(['enabled' => true, 'formats' => [' JSON ', 'json']]);

        $this->assertTrue($this->has('feed.json'));
        $this->assertFalse($this->has('feed.atom'));
        $this->assertCount(1, $this->advertisedLinks('blog/post.html') ?? []);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function noFormats(): array
    {
        return [
            'empty list' => [[]],
            'only unknown formats' => [['rdf', 'opml']],
        ];
    }

    #[DataProvider('noFormats')]
    public function testNoSiteFormatsMeansZeroSiteFeedFilesAndNoAdvertisedLinks(mixed $formats): void
    {
        $this->render(['enabled' => true, 'formats' => $formats]);

        foreach (self::NEW_FEEDS as $file) {
            $this->assertFalse($this->has($file), $file);
        }
        $this->assertSame([], $this->advertisedLinks('blog/post.html') ?? []);
    }

    public function testFormatsThatIsAStringFallsBackToAllFormatsWithWarning(): void
    {
        $this->render(['enabled' => true, 'formats' => 'rss']);

        foreach (self::NEW_FEEDS as $file) {
            $this->assertTrue($this->has($file), $file);
        }
        $this->assertStringContainsString('feed.formats must be a list', $this->newLog());
    }

    public function testCategoryFormatsWriteAtomAndJsonBesideTheUnchangedRssXml(): void
    {
        $this->render(null);
        $baseline = (string) preg_replace('#<lastBuildDate>[^<]*</lastBuildDate>#', '', $this->read('blog/rss.xml'));
        $this->resetOutput();

        $this->render(['enabled' => true, 'category_formats' => ['rss', 'atom', 'json']]);

        $this->assertSame(
            $baseline,
            preg_replace('#<lastBuildDate>[^<]*</lastBuildDate>#', '', $this->read('blog/rss.xml'))
        );
        $this->assertSame(['Post'], $this->atomTitles('blog/feed.atom'));
        $this->assertSame(['Post'], $this->jsonTitles('blog/feed.json'));
        $this->assertSame('https://feed.example.com/blog/feed.json', $this->json('blog/feed.json')['feed_url']);
    }

    public function testDefaultCategoryFormatsAddNothingBesideCategoryRss(): void
    {
        $this->render(['enabled' => true]);

        $this->assertFalse($this->has('blog/feed.atom'));
        $this->assertFalse($this->has('blog/feed.json'));
        $this->assertFalse($this->has('blog/feed.xml'));
    }

    public function testCategoryFormatsWithOnlyJsonWritesNoCategoryAtom(): void
    {
        $this->render(['enabled' => true, 'category_formats' => ['json']]);

        $this->assertTrue($this->has('blog/feed.json'));
        $this->assertFalse($this->has('blog/feed.atom'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function collidingFiles(): array
    {
        return [
            'site rss' => ['feed.xml', 'feed.xml'],
            'site atom' => ['feed.atom', 'feed.atom'],
            'site json' => ['feed.json', 'feed.json'],
        ];
    }

    #[DataProvider('collidingFiles')]
    public function testAuthorsSourceFileWinsAndOurFileIsNotWrittenWithWarning(string $name, string $expectedWarningFile): void
    {
        $this->raw($name, "author-owned\n");

        $this->render(['enabled' => true]);

        $this->assertStringContainsString("SiteFeed: content/{$expectedWarningFile} exists", $this->newLog());
        if ($this->has($name)) {
            $this->assertSame("author-owned\n", $this->read($name), 'our feed must never overwrite it');
        }
        $advertised = implode(' ', $this->advertisedLinks('blog/post.html') ?? []);
        $this->assertStringNotContainsString('/' . $name, $advertised, 'a skipped feed must not be advertised');
        foreach (array_diff(self::NEW_FEEDS, [$name]) as $other) {
            $this->assertNotSame('author-owned', trim($this->read($other)), $other);
        }
    }

    public function testCategoryFeedCollisionSkipsOnlyThatCategoryFile(): void
    {
        $this->raw('blog/feed.atom', "author-owned\n");

        $this->render(['enabled' => true, 'category_formats' => ['atom', 'json']]);

        $this->assertStringContainsString('SiteFeed: content/blog/feed.atom exists', $this->newLog());
        if ($this->has('blog/feed.atom')) {
            $this->assertSame("author-owned\n", $this->read('blog/feed.atom'));
        }
        $this->assertSame(['Post'], $this->jsonTitles('blog/feed.json'));
        $this->assertTrue($this->has('feed.atom'));
    }

    public function testPodcastCategoryIsExcludedFromSiteFeedAndCategoryAtomJsonButItsRssIsUnchanged(): void
    {
        $this->page('podcast.md', ['title' => 'Podcast', 'type' => 'category', 'podcast' => true], '');
        $this->page('ep.md', ['title' => 'Episode', 'category' => 'Podcast', 'date' => '2024-05-01']);
        $this->page('ep-loose.md', ['title' => 'Episode Feed True', 'category' => 'Podcast', 'feed' => true, 'date' => '2024-05-02']);

        $this->render(['enabled' => true, 'category_formats' => ['rss', 'atom', 'json']]);

        foreach (self::NEW_FEEDS as $file) {
            $this->assertStringNotContainsString('Episode', $this->read($file), $file);
        }
        $this->assertFalse($this->has('podcast/feed.atom'));
        $this->assertFalse($this->has('podcast/feed.json'));
        $this->assertSame(['Episode Feed True', 'Episode'], $this->rssTitles('podcast/rss.xml'));
    }

    public function testPodcastFlagAsQuotedTrueStringAlsoExcludesTheCategory(): void
    {
        $this->page('podcast.md', ['title' => 'Podcast', 'type' => 'category', 'podcast' => 'true'], '');
        $this->page('ep.md', ['title' => 'Episode', 'category' => 'Podcast', 'date' => '2024-05-01']);

        $this->render(['enabled' => true]);

        $this->assertStringNotContainsString('Episode', $this->read('feed.json'));
    }

    public function testFeedsAreNotListedInSitemapSearchIndexOrRobotsTxt(): void
    {
        $this->render(['enabled' => true, 'category_formats' => ['atom', 'json']], ['search' => ['engine' => 'minisearch']]);

        $this->assertTrue($this->has('feed.json'));
        foreach (['sitemap.xml', 'robots.txt'] as $file) {
            $content = $this->read($file);
            $this->assertStringNotContainsString('feed.xml', $content, $file);
            $this->assertStringNotContainsString('feed.atom', $content, $file);
            $this->assertStringNotContainsString('feed.json', $content, $file);
        }
        // The test template prints the links into page text, which search may index; the feed
        // files themselves must not be documents.
        $search = json_decode($this->read('search.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($search);
        foreach ($search as $doc) {
            $this->assertDoesNotMatchRegularExpression('~/feed\.(xml|atom|json)$~', (string) $doc['url']);
        }
        $this->assertCount(3, $search);
        $this->assertStringContainsString('post.html', $this->read('sitemap.xml'), 'sanity: the sitemap was really generated');
    }
}
