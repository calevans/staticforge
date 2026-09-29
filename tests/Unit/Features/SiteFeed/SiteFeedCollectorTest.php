<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\SiteFeed;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\SiteFeed\Services\FeedClock;
use EICC\StaticForge\Features\SiteFeed\Services\FeedConfig;
use EICC\StaticForge\Features\SiteFeed\Services\SiteFeedCollector;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use PHPUnit\Framework\Attributes\DataProvider;

class SiteFeedCollectorTest extends UnitTestCase
{
    private SiteFeedCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setContainerVariable('OUTPUT_DIR', '/out');
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com/');
        $this->setContainerVariable('site_config', ['feed' => ['enabled' => true]]);
        $this->collector = $this->makeCollector();
    }

    private function makeCollector(): SiteFeedCollector
    {
        $logger = $this->container->get('logger');
        $this->assertInstanceOf(Log::class, $logger);

        return new SiteFeedCollector(
            $this->container,
            $logger,
            new FeedConfig($this->container, $logger),
            new FeedClock()
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function collect(array $metadata, string $out = '/out/blog/post.html', bool $skip = false, string $html = '<!--sf:content--><p>Hi</p><!--/sf:content-->'): void
    {
        $this->collector->collect(new RenderEvent(
            'POST_RENDER',
            '/src/post.md',
            '/blog/post.html',
            $metadata,
            $html,
            $out,
            $skip
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function eligible(): array
    {
        return ['title' => 'T', 'date' => '2024-01-01', 'category' => 'Blog'];
    }

    public function testEligiblePageBecomesACandidateWithAbsoluteIdAndCategorySlug(): void
    {
        $this->collect($this->eligible());

        $candidates = $this->collector->getCandidates();
        $this->assertCount(1, $candidates);
        $this->assertSame('https://example.com/blog/post.html', $candidates[0]->item->id);
        $this->assertSame('blog', $candidates[0]->categorySlug);
        $this->assertSame('Blog', $candidates[0]->categoryName);
        $this->assertSame('blog/post.html', $candidates[0]->path);
        $this->assertFalse($candidates[0]->optOut);
    }

    public function testIndexPagesUseTheDirectoryUrl(): void
    {
        $this->collect(['title' => 'Home', 'date' => '2024-01-01', 'feed' => true], '/out/index.html');
        $this->collect(['title' => 'Sec', 'date' => '2024-01-01', 'feed' => true], '/out/sec/index.html');

        $ids = array_map(static fn ($c): string => $c->item->id, $this->collector->getCandidates());
        $this->assertSame(['https://example.com/', 'https://example.com/sec/'], $ids);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function hiddenMetadata(): array
    {
        return [
            'draft' => [['draft' => true]],
            'draft string' => [['draft' => 'true']],
            'noindex' => [['noindex' => true]],
            'noindex string' => [['noindex' => 'true']],
            'robots no' => [['robots' => 'no']],
            'robots bool false' => [['robots' => false]],
            'sitemap false' => [['sitemap' => false]],
            'sitemap string false' => [['sitemap' => 'false']],
            'category definition' => [['type' => 'category']],
            'generated category listing' => [['category_files' => []]],
            'generated category listing (non-empty)' => [['category_files' => [['title' => 'x']]]],
            'generated tag listing' => [['tag_files' => []]],
            'generated tag slug' => [['tag_slug' => 'php']],
        ];
    }

    /**
     * @param array<string, mixed> $hidden
     */
    #[DataProvider('hiddenMetadata')]
    public function testHiddenOrGeneratedPagesAreNeverCollected(array $hidden): void
    {
        $this->collect($hidden + $this->eligible() + ['feed' => true]);

        $this->assertSame([], $this->collector->getCandidates());
    }

    public function testThe404PageAtTheOutputRootIsNeverCollected(): void
    {
        $this->collect(['title' => '404', 'date' => '2024-01-01', 'feed' => true], '/out/404.html');

        $this->assertSame([], $this->collector->getCandidates());
    }

    public function testRobotsYesAndSitemapTrueStillCollect(): void
    {
        $this->collect(['robots' => 'yes', 'sitemap' => true] + $this->eligible());

        $this->assertCount(1, $this->collector->getCandidates());
    }

    public function testFeedFalseIsRecordedAsOptOutNotDropped(): void
    {
        $this->collect(['feed' => 'false'] + $this->eligible());

        $this->assertTrue($this->collector->getCandidates()[0]->optOut);
    }

    public function testPageWithoutCategoryNeedsFeedTrue(): void
    {
        $this->collect(['title' => 'A', 'date' => '2024-01-01']);
        $this->assertSame([], $this->collector->getCandidates());

        $this->collect(['title' => 'B', 'date' => '2024-01-01', 'feed' => 'true']);
        $this->assertCount(1, $this->collector->getCandidates());
        $this->assertNull($this->collector->getCandidates()[0]->categorySlug);
    }

    public function testUnsluggableCategoryPageIsLeftOut(): void
    {
        $this->collect(['category' => '!!!'] + $this->eligible());

        $this->assertSame([], $this->collector->getCandidates());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableDates(): array
    {
        return [
            'missing' => [null],
            'empty string' => [''],
            'false' => [false],
            'gibberish' => ['not a date'],
            'array' => [[2024]],
        ];
    }

    #[DataProvider('unusableDates')]
    public function testPagesWithoutAUsableDateAreLeftOut(mixed $date): void
    {
        $this->collect(['date' => $date] + $this->eligible());

        $this->assertSame([], $this->collector->getCandidates());
    }

    public function testUpdatedFallsBackToDateAndOverridesItWhenPresent(): void
    {
        $this->collect($this->eligible());
        $this->collect(['updated' => '2024-05-05'] + $this->eligible(), '/out/blog/two.html');

        [$a, $b] = $this->collector->getCandidates();
        $this->assertEquals($a->item->published, $a->item->updated);
        $this->assertSame('2024-05-05', $b->item->updated->format('Y-m-d'));
        $this->assertSame('2024-01-01', $b->item->published->format('Y-m-d'));
    }

    public function testIntegerTimestampsAndDateTimeObjectsAreAccepted(): void
    {
        $this->collect(['date' => 1704067200] + $this->eligible());
        $this->collect(['date' => new \DateTimeImmutable('2024-02-02')] + $this->eligible(), '/out/blog/two.html');

        $this->assertCount(2, $this->collector->getCandidates());
    }

    public function testFeatureDisabledCollectsNothing(): void
    {
        $this->setContainerVariable('site_config', []);
        $collector = $this->makeCollector();

        $collector->collect(new RenderEvent('POST_RENDER', 'f', 'u', $this->eligible(), '<p>x</p>', '/out/a.html'));

        $this->assertSame([], $collector->getCandidates());
    }

    public function testSkippedFilesAndFilesOutsideTheOutputDirAreIgnored(): void
    {
        $this->collect($this->eligible(), '/out/blog/post.html', true);
        $this->collect($this->eligible(), '/elsewhere/post.html');
        $this->collect($this->eligible(), '');

        $this->assertSame([], $this->collector->getCandidates());
    }

    public function testResetClearsCandidates(): void
    {
        $this->collect($this->eligible());
        $this->collector->reset();

        $this->assertSame([], $this->collector->getCandidates());
    }

    public function testSummaryPrefersDescriptionThenTruncatesPlainTextOnAWordBoundary(): void
    {
        $this->collect(['description' => 'Given'] + $this->eligible());
        $long = '<!--sf:content--><p>' . str_repeat('word ', 80) . '</p><!--/sf:content-->';
        $this->collect($this->eligible(), '/out/blog/two.html', false, $long);

        [$a, $b] = $this->collector->getCandidates();
        $this->assertSame('Given', $a->item->summary);
        $this->assertStringEndsWith('word...', $b->item->summary);
        $this->assertLessThanOrEqual(203, mb_strlen($b->item->summary));
    }

    public function testBodyIsTheMarkedArticleOnlyAndUrlsAreAbsolutized(): void
    {
        $html = '<html><body><nav>menu</nav><!--sf:content--><p><a href="/x">x</a></p><!--/sf:content--></body></html>';
        $this->collect($this->eligible(), '/out/blog/post.html', false, $html);

        $body = $this->collector->getCandidates()[0]->item->contentHtml;
        $this->assertStringNotContainsString('menu', $body);
        $this->assertStringContainsString('href="https://example.com/x"', $body);
    }

    public function testTagsAreCategoryPlusPageTagsDeduplicated(): void
    {
        $this->collect(['tags' => ['php', 'Blog', ' php ', '', 7]] + $this->eligible());

        $this->assertSame(['Blog', 'php'], $this->collector->getCandidates()[0]->item->tags);
    }
}
