<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration\SiteFeed;

use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\SiteFeed\Events\SiteFeedInitEvent;
use EICC\StaticForge\Features\SiteFeed\Events\SiteFeedItemBuildingEvent;
use EICC\StaticForge\Tests\Integration\Support\FeedListenerSpy;

/**
 * Compatibility contract (design 4.1): RSS_BUILDER_INIT / RSS_ITEM_BUILDING belong to the
 * category rss.xml path alone. New feeds fire SITE_FEED_INIT / SITE_FEED_ITEM_BUILDING.
 */
class SiteFeedEventsTest extends SiteFeedTestCase
{
    private FeedListenerSpy $spy;

    /** @var list<array{string, string}> */
    private array $initCalls = [];

    /** @var list<array{string, string, string}> */
    private array $itemCalls = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->category('blog');
        $this->page('podcast.md', ['title' => 'Podcast', 'type' => 'category', 'podcast' => true], '');
        foreach ([1, 2, 3] as $i) {
            $this->page("b{$i}.md", ['title' => "Blog {$i}", 'category' => 'Blog', 'date' => "2024-01-0{$i}"]);
        }
        foreach ([1, 2] as $i) {
            $this->page("e{$i}.md", ['title' => "Episode {$i}", 'category' => 'Podcast', 'date' => "2024-02-0{$i}"]);
        }
        $this->page('loose.md', ['title' => 'Loose', 'feed' => true, 'date' => '2024-03-01']);
    }

    /**
     * @param array<string, mixed> $feed
     * @param callable(EventManager): void|null $extra
     */
    private function build(array $feed, ?callable $extra = null): void
    {
        $this->spy = new FeedListenerSpy();
        $this->render($feed, [], function (EventManager $events) use ($extra): void {
            $events->registerListener('RSS_BUILDER_INIT', [$this->spy, 'onBuilderInit']);
            $events->registerListener('RSS_ITEM_BUILDING', [$this->spy, 'onItemBuilding']);
            $events->registerListener('SITE_FEED_INIT', [$this, 'recordInit']);
            $events->registerListener('SITE_FEED_ITEM_BUILDING', [$this, 'recordItem']);
            if ($extra !== null) {
                $extra($events);
            }
        });
    }

    public function recordInit(SiteFeedInitEvent $event): void
    {
        $this->initCalls[] = [$event->format, $event->scope];
    }

    public function recordItem(SiteFeedItemBuildingEvent $event): void
    {
        $this->itemCalls[] = [$event->format, $event->scope, $event->item->title];
    }

    public function testPodcastListenersReceiveNoCallsFromSiteFeedGeneration(): void
    {
        $this->build(['enabled' => true, 'category_formats' => ['rss', 'atom', 'json']]);

        // Exactly what category rss.xml generation alone produces: one init per category,
        // one item event per category item. Any site-feed call would push these higher.
        $this->assertCount(2, $this->spy->builderInitCalls);
        $this->assertCount(5, $this->spy->itemCalls);
        $titles = array_map(static fn (array $c): mixed => $c['title'], $this->spy->itemCalls);
        sort($titles);
        $this->assertSame(
            ['Blog 1', 'Blog 2', 'Blog 3', 'Episode 1', 'Episode 2'],
            $titles,
            'each category item exactly once; "Loose" only exists in the site feed'
        );
        $this->assertNotContains('Loose', $titles);
    }

    public function testCategoryRssFeedsStillReceiveTheirPodcastEvents(): void
    {
        $this->build(['enabled' => true]);

        $podcastFlags = array_map(
            static fn (array $c): mixed => $c['categoryMetadata']['podcast'] ?? null,
            $this->spy->builderInitCalls
        );
        sort($podcastFlags);
        $this->assertEquals([null, true], $podcastFlags);
        $this->assertTrue($this->has('blog/rss.xml'));
        $this->assertTrue($this->has('podcast/rss.xml'));
    }

    public function testSiteFeedEventsFireOncePerFeedAndPerItemPerFeed(): void
    {
        $this->build(['enabled' => true, 'category_formats' => ['atom', 'json']]);

        $inits = $this->initCalls;
        sort($inits);
        // Podcast category is excluded from every new feed
        $this->assertSame([
            ['atom', 'blog'],
            ['atom', 'site'],
            ['json', 'blog'],
            ['json', 'site'],
            ['rss', 'site'],
        ], $inits);

        // site feeds: Loose + 3 blog posts, in each of 3 formats; blog category: 3 posts in atom and json
        $this->assertCount(4 * 3 + 3 * 2, $this->itemCalls);
        $siteJson = array_values(array_filter(
            $this->itemCalls,
            static fn (array $c): bool => $c[0] === 'json' && $c[1] === 'site'
        ));
        $this->assertSame(['Loose', 'Blog 3', 'Blog 2', 'Blog 1'], array_column($siteJson, 2));
    }

    public function testListenerMutatingTheItemCopyAffectsOnlyThatFeed(): void
    {
        $this->build(
            ['enabled' => true, 'category_formats' => ['atom', 'json']],
            static function (EventManager $events): void {
                $events->registerListener('SITE_FEED_ITEM_BUILDING', [new class {
                    public function tag(SiteFeedItemBuildingEvent $e): void
                    {
                        if ($e->format === 'json' && $e->scope === 'site') {
                            $e->item->title .= ' [site-json-only]';
                            $e->item->tags[] = 'mutated';
                        }
                    }
                }, 'tag'], 50);
            }
        );

        $this->assertSame(
            ['Loose [site-json-only]', 'Blog 3 [site-json-only]', 'Blog 2 [site-json-only]', 'Blog 1 [site-json-only]'],
            $this->jsonTitles()
        );
        $this->assertSame(['Loose', 'Blog 3', 'Blog 2', 'Blog 1'], $this->rssTitles());
        $this->assertSame(['Loose', 'Blog 3', 'Blog 2', 'Blog 1'], $this->atomTitles());
        $this->assertSame(['Blog 3', 'Blog 2', 'Blog 1'], $this->jsonTitles('blog/feed.json'));
        $this->assertSame(['Blog 3', 'Blog 2', 'Blog 1'], $this->atomTitles('blog/feed.atom'));
        $this->assertStringNotContainsString('mutated', $this->read('feed.atom'));
        $this->assertStringNotContainsString('mutated', $this->read('blog/feed.json'));
        $this->assertStringNotContainsString('site-json-only', $this->read('blog/rss.xml'));
    }

    public function testListenerCanRetitleTheChannelOfOneFeedOnly(): void
    {
        $this->build(['enabled' => true], static function (EventManager $events): void {
            $events->registerListener('SITE_FEED_INIT', [new class {
                public function retitle(SiteFeedInitEvent $e): void
                {
                    if ($e->format === 'atom') {
                        $e->channel['title'] = 'Renamed Atom';
                    }
                }
            }, 'retitle'], 50);
        });

        $this->assertStringContainsString('<title>Renamed Atom</title>', $this->read('feed.atom'));
        $this->assertSame('Feed Site', $this->json()['title']);
    }

    public function testNoSiteFeedEventsFireWhenTheFeatureIsDisabled(): void
    {
        $this->build(['enabled' => false]);

        $this->assertSame([], $this->initCalls);
        $this->assertSame([], $this->itemCalls);
        $this->assertCount(2, $this->spy->builderInitCalls);
    }
}
