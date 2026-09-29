<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration;

use DOMDocument;
use EICC\StaticForge\Core\Application;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Tests\Integration\Support\FeedListenerSpy;

/**
 * Characterization ("golden") test for per-category RSS feeds as produced by 3.3.x.
 *
 * Freezes the byte output of /{category}/rss.xml and the payload of the RSS_BUILDER_INIT /
 * RSS_ITEM_BUILDING events, which external podcast packages depend on. Written before the
 * 3.4 feed refactor so the refactor can be proven not to change either.
 *
 * Regenerate goldens (deliberately, and review the diff) with:
 *     lando ssh -c "env UPDATE_GOLDEN=1 vendor/bin/phpunit tests/Integration/CategoryFeedGoldenTest.php"
 *     (plain `lando phpunit` does not forward environment variables into the container)
 * Without UPDATE_GOLDEN=1 this test only compares.
 *
 * Normalisations applied before comparing (everything else is byte-exact):
 *  1. <lastBuildDate>: FeedChannel sets it to date('r') at build time, so it is the only
 *     genuinely wall-clock value in the file. Replaced with the literal LAST_BUILD_DATE.
 *
 * Deliberately NOT normalised, made deterministic instead:
 *  - Item pubDate: the default timezone is pinned to UTC for the test, so date('r') is stable.
 *  - The undated page: RssFeedService falls back to the source file mtime, so the fixture's
 *    mtime is set to a fixed instant with touch() rather than normalising the output.
 */
class CategoryFeedGoldenTest extends IntegrationTestCase
{
    private const GOLDEN_DIR = __DIR__ . '/Golden/feeds';
    private const UNDATED_MTIME = '2023-06-15 12:00:00';

    private string $testOutputDir;
    private string $testContentDir;
    private string $testTemplateDir;
    private string $previousTimezone;
    private FeedListenerSpy $spy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        $suffix = uniqid('', true) . '_' . getmypid();
        $this->testOutputDir = sys_get_temp_dir() . '/staticforge_feedgolden_output_' . $suffix;
        $this->testContentDir = sys_get_temp_dir() . '/staticforge_feedgolden_content_' . $suffix;
        $this->testTemplateDir = sys_get_temp_dir() . '/staticforge_feedgolden_templates_' . $suffix;

        mkdir($this->testOutputDir, 0755, true);
        mkdir($this->testContentDir, 0755, true);
        mkdir($this->testTemplateDir . '/sample', 0755, true);

        $_ENV['SOURCE_DIR'] = $this->testContentDir;
        $_ENV['OUTPUT_DIR'] = $this->testOutputDir;
        $_ENV['PUBLIC_DIR'] = $this->testOutputDir;
        $_ENV['TEMPLATE_DIR'] = $this->testTemplateDir;
        $_ENV['SITE_NAME'] = 'Golden Site';
        $_ENV['SITE_BASE_URL'] = 'https://golden.example.com/';

        file_put_contents(
            $this->testTemplateDir . '/sample/base.html.twig',
            "<!DOCTYPE html>\n<html>\n<head><title>{{ title | default('Untitled') }}</title></head>\n"
            . "<body>\n<h1>{{ title }}</h1>\n<main>{{ content | raw }}</main>\n</body>\n</html>\n"
        );
        file_put_contents(
            $this->testTemplateDir . '/sample/category-index.html.twig',
            "<!DOCTYPE html>\n<html><head><title>{{ title | default('Category') }}</title></head>\n"
            . "<body>{% for file in category_files %}<a href=\"{{ file.url }}\">{{ file.title }}</a>{% endfor %}"
            . "</body></html>\n"
        );

        $this->writeFixtureSite();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone);
        $this->removeDirectory($this->testOutputDir);
        $this->removeDirectory($this->testContentDir);
        $this->removeDirectory($this->testTemplateDir);
        parent::tearDown();
    }

    public function testBlogCategoryFeedMatchesGolden(): void
    {
        $this->build();

        $this->assertGolden('blog.rss.xml', $this->readNormalisedFeed('blog'));
    }

    public function testPodcastCategoryFeedMatchesGolden(): void
    {
        $this->build();

        $this->assertGolden('podcast.rss.xml', $this->readNormalisedFeed('podcast'));
    }

    public function testPodcastFeedIsWellFormedXmlWithEnclosuresOnlyWhereListenerAddedThem(): void
    {
        $this->build();

        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($this->readFeed('podcast') ?: '<x/>'), 'podcast feed must be well-formed XML');
        $this->assertSame(4, $dom->getElementsByTagName('enclosure')->length);
        $this->assertGreaterThan(0, $dom->getElementsByTagName('explicit')->length);

        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($this->readFeed('blog') ?: '<x/>'), 'blog feed must be well-formed XML');
        $this->assertSame(0, $dom->getElementsByTagName('enclosure')->length);
        $this->assertSame(0, $dom->getElementsByTagName('explicit')->length);
    }

    public function testBuilderInitEventFiresOncePerCategoryWithCategoryDefinitionMetadata(): void
    {
        $this->build();

        $this->assertCount(2, $this->spy->builderInitCalls);
        $this->assertSame('RSS_BUILDER_INIT', $this->spy->builderInitCalls[0]['event']);

        $byPodcastFlag = [];
        foreach ($this->spy->builderInitCalls as $call) {
            $byPodcastFlag[var_export($call['categoryMetadata']['podcast'] ?? null, true)] = $call['categoryMetadata'];
        }
        ksort($byPodcastFlag);
        $this->assertSame(['NULL', 'true'], array_keys($byPodcastFlag));

        // Category definition frontmatter is passed through verbatim.
        $this->assertSame('category', $byPodcastFlag['true']['type']);
        $this->assertSame('Podcast', $byPodcastFlag['true']['title']);
        $this->assertSame('category', $byPodcastFlag['NULL']['type']);
        $this->assertSame('Blog', $byPodcastFlag['NULL']['title']);
    }

    public function testItemBuildingEventPayloadHasExactlyTheKeysPodcastListenersDependOn(): void
    {
        $this->build();

        $this->assertNotEmpty($this->spy->itemCalls);
        foreach ($this->spy->itemCalls as $call) {
            $this->assertSame('RSS_ITEM_BUILDING', $call['event']);
            $this->assertSame(
                ['title', 'url', 'description', 'date', 'metadata', 'content'],
                $call['fileKeys'],
                'the $file payload key set and order is a public contract for podcast listeners'
            );
            $this->assertIsString($call['title']);
            $this->assertStringStartsWith('/', $call['url']);
            $this->assertIsArray($call['metadata']);
        }
    }

    public function testItemBuildingEventFiresOncePerItemInNewestFirstOrderPerFeed(): void
    {
        $this->build();

        $byFeed = ['blog' => [], 'podcast' => []];
        foreach ($this->spy->itemCalls as $call) {
            $feed = str_starts_with($call['url'], '/podcast/') ? 'podcast' : 'blog';
            $byFeed[$feed][] = $call['title'];
        }

        // Newest first; the undated page falls back to its (fixed) mtime, 2023-06-15.
        $this->assertSame(
            ['Episode Three', 'Episode Two', 'Episode One', 'Bonus without audio_url', 'Undated Episode'],
            $byFeed['podcast']
        );
        $this->assertSame(
            ['Cafe ☕ 日本語 🎉 & <Friends>', 'Second Post', 'First Post', 'Undated Post', 'Raw CDATA Terminator'],
            $byFeed['blog']
        );
    }

    public function testItemBuildingEventCarriesDateMetadataAndAbsoluteFreeUrlAsCollected(): void
    {
        $this->build();

        $byTitle = [];
        foreach ($this->spy->itemCalls as $call) {
            $byTitle[$call['title']] = $call;
        }

        $this->assertSame('/podcast/ep-3.html', $byTitle['Episode Three']['url']);
        $this->assertSame('2024-03-03', $byTitle['Episode Three']['date']);
        $this->assertSame('episodes/three.mp3', $byTitle['Episode Three']['metadata']['audio_url']);
        // Undated: date is derived from the source file mtime as Y-m-d
        $this->assertSame('2023-06-15', $byTitle['Undated Episode']['date']);
    }

    private function build(): void
    {
        $container = $this->createContainer(__DIR__ . '/../.env.testing');

        $vars = [
            'SOURCE_DIR' => $this->testContentDir,
            'OUTPUT_DIR' => $this->testOutputDir,
            'PUBLIC_DIR' => $this->testOutputDir,
            'TEMPLATE_DIR' => $this->testTemplateDir,
            'TEMPLATE' => 'sample',
            'SITE_BASE_URL' => 'https://golden.example.com/',
        ];
        foreach ($vars as $key => $value) {
            if ($container->hasVariable($key)) {
                $container->updateVariable($key, $value);
            } else {
                $container->setVariable($key, $value);
            }
        }
        // Composer-installed feature packages (AEO, social metadata, analytics, chapter nav) rewrite
        // page HTML, and that HTML ends up in <content:encoded>. Disable them so the golden depends
        // only on this repository, not on whatever happens to be in vendor/.
        $config = [
            'site' => ['name' => 'Golden Site'],
            'disabled_features' => ['AnswerEngineOptimization', 'SocialMetadata', 'GoogleAnalytics', 'ChapterNav'],
        ];
        if ($container->hasVariable('site_config')) {
            $container->updateVariable('site_config', $config);
        } else {
            $container->setVariable('site_config', $config);
        }

        $application = new Application($container);

        // Test-only stand-in for what staticforge-podcast's PodcastFeedService does.
        $this->spy = new FeedListenerSpy();
        $events = $container->get(EventManager::class);
        $events->registerListener('RSS_BUILDER_INIT', [$this->spy, 'onBuilderInit']);
        $events->registerListener('RSS_ITEM_BUILDING', [$this->spy, 'onItemBuilding']);

        $this->assertTrue($application->generate());
    }

    private function readFeed(string $category): string
    {
        $path = $this->testOutputDir . '/' . $category . '/rss.xml';
        $this->assertFileExists($path);
        $xml = file_get_contents($path);
        $this->assertNotFalse($xml);

        return $xml;
    }

    private function readNormalisedFeed(string $category): string
    {
        $normalised = preg_replace(
            '#<lastBuildDate>[^<]*</lastBuildDate>#',
            '<lastBuildDate>LAST_BUILD_DATE</lastBuildDate>',
            $this->readFeed($category),
            -1,
            $count
        );
        $this->assertSame(1, $count, 'exactly one lastBuildDate expected');
        $this->assertNotNull($normalised);

        return $normalised;
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

    private function writeFixtureSite(): void
    {
        $this->write('blog.md', "---\ntitle: Blog\ntype: category\n---\n");
        $this->write(
            'podcast.md',
            "---\ntitle: Podcast\ntype: category\npodcast: true\n---\n"
        );

        $this->write('blog-1.md', $this->md('First Post', 'Blog', '2024-01-01', "First body.\n"));
        $this->write('blog-2.md', $this->md('Second Post', 'Blog', '2024-02-01', "Second body with **bold**.\n"));
        $this->write(
            'blog-unicode.md',
            $this->md(
                'Cafe ☕ 日本語 🎉 & <Friends>',
                'Blog',
                '2024-04-01',
                "Unicode: café ☕ 日本語 🎉 — “quotes” & ampersand.\n\n"
                . "![Logo](/assets/logo.png)\n\n[Home](/about.html)\n"
            )
        );
        $this->write('blog-undated.md', $this->md('Undated Post', 'Blog', null, "No date here.\n"));
        $this->write(
            'blog-cdata.html',
            "<!--\n---\ntitle: \"Raw CDATA Terminator\"\ncategory: \"Blog\"\ndate: \"2023-01-01\"\n---\n-->\n"
            . "<p>Code sample: <code>a[b[0]]>c</code> and <![CDATA[ raw ]]> marker.</p>\n"
        );

        $this->write('ep-1.md', $this->md('Episode One', 'Podcast', '2024-01-01', "Notes one.\n", [
            'audio_url' => 'episodes/one.mp3',
            'media_length' => 111111,
            'media_type' => 'audio/mpeg',
            'podcast_show_notes_html' => '<p>Show notes one</p>',
        ]));
        $this->write('ep-2.md', $this->md('Episode Two', 'Podcast', '2024-02-02', "Notes two.\n", [
            'audio_url' => 'https://cdn.example.org/two.mp3',
            'media_length' => 222222,
            'media_type' => 'audio/mpeg',
        ]));
        $this->write('ep-3.md', $this->md('Episode Three', 'Podcast', '2024-03-03', "Notes three ✓.\n", [
            'audio_url' => 'episodes/three.mp3',
            'media_length' => 333333,
            'media_type' => 'audio/mpeg',
            'podcast_show_notes_html' => '<p>Show notes three ]]> tricky</p>',
        ]));
        // audio_file is NOT what the listener reads (audio_url is), so this gets no enclosure.
        $this->write('ep-bonus.md', $this->md('Bonus without audio_url', 'Podcast', '2023-12-25', "Bonus.\n", [
            'audio_file' => 'episodes/bonus.mp3',
        ]));
        $this->write('ep-undated.md', $this->md('Undated Episode', 'Podcast', null, "Undated episode.\n", [
            'audio_url' => 'episodes/undated.mp3',
            'media_length' => 5,
            'media_type' => 'audio/mpeg',
        ]));
        touch($this->testContentDir . '/ep-undated.md', (int)strtotime(self::UNDATED_MTIME));
        touch($this->testContentDir . '/blog-undated.md', (int)strtotime(self::UNDATED_MTIME));
    }

    /**
     * @param array<string, string|int> $extra
     */
    private function md(string $title, string $category, ?string $date, string $body, array $extra = []): string
    {
        $fm = "---\ntitle: " . json_encode($title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
            . "category: {$category}\n";
        if ($date !== null) {
            $fm .= "date: \"{$date}\"\n";
        }
        foreach ($extra as $key => $value) {
            $fm .= $key . ': ' . (is_int($value)
                ? (string)$value
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "\n";
        }

        return $fm . "---\n" . $body;
    }

    private function write(string $name, string $content): void
    {
        file_put_contents($this->testContentDir . '/' . $name, $content);
    }
}
