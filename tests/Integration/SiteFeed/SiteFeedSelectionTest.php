<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration\SiteFeed;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Which pages land in the site feeds, how many, and in what order.
 */
class SiteFeedSelectionTest extends SiteFeedTestCase
{
    public function testUndatedPagesAreExcluded(): void
    {
        $this->category('blog');
        $this->page('dated.md', ['title' => 'Dated', 'category' => 'Blog', 'date' => '2024-01-01']);
        $this->page('undated.md', ['title' => 'Undated', 'category' => 'Blog']);

        $this->render(['enabled' => true]);

        $this->assertSame(['Dated'], $this->rssTitles());
        $this->assertSame(['Dated'], $this->atomTitles());
        $this->assertSame(['Dated'], $this->jsonTitles());
    }

    public function testFeedFalseLeavesThePageOutOfTheSiteFeedButNotTheCategoryRss(): void
    {
        $this->category('blog');
        $this->page('shown.md', ['title' => 'Shown', 'category' => 'Blog', 'date' => '2024-01-01']);
        $this->page('optout.md', ['title' => 'Opted Out', 'category' => 'Blog', 'date' => '2024-01-02', 'feed' => false]);

        $this->render(['enabled' => true]);

        $this->assertSame(['Shown'], $this->rssTitles());
        $this->assertSame(['Opted Out', 'Shown'], $this->rssTitles('blog/rss.xml'));
    }

    public function testFeedTrueWithoutCategoryIsIncluded(): void
    {
        $this->page('loose.md', ['title' => 'Loose', 'feed' => true, 'date' => '2024-01-01']);
        $this->page('ignored.md', ['title' => 'No Opt In', 'date' => '2024-01-02']);

        $this->render(['enabled' => true]);

        $this->assertSame(['Loose'], $this->jsonTitles());
    }

    public function testFeedTrueWithoutDateIsStillExcluded(): void
    {
        $this->page('loose.md', ['title' => 'Loose', 'feed' => true]);

        $this->render(['enabled' => true]);

        $this->assertSame([], $this->jsonTitles());
    }

    public function testUpdatedTakesPrecedenceOverDateForModifiedTimeAndFeedTimestamps(): void
    {
        $this->page('old.md', [
            'title' => 'Edited',
            'feed' => true,
            'date' => '2024-01-01',
            'updated' => '2024-03-05T10:00:00+00:00',
        ]);

        $this->render(['enabled' => true]);

        $item = $this->json()['items'][0];
        $this->assertSame('2024-01-01T00:00:00+00:00', $item['date_published']);
        $this->assertSame('2024-03-05T10:00:00+00:00', $item['date_modified']);
        $this->assertStringContainsString('<updated>2024-03-05T10:00:00+00:00</updated>', $this->read('feed.atom'));
        $this->assertStringContainsString('<lastBuildDate>Tue, 05 Mar 2024 10:00:00 +0000</lastBuildDate>', $this->read('feed.xml'));
    }

    public function testUnparseableDateLeavesPageOutAndLogsWarning(): void
    {
        $this->page('bad.md', ['title' => 'Bad Date', 'feed' => true, 'date' => 'not a date at all']);
        $this->page('good.md', ['title' => 'Good', 'feed' => true, 'date' => '2024-01-01']);

        $this->render(['enabled' => true]);

        $this->assertSame(['Good'], $this->jsonTitles());
        $this->assertStringContainsString('unparseable date', $this->newLog());
    }

    public function testLimitKeepsOnlyTheNewestItems(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->page("p{$i}.md", ['title' => "Post {$i}", 'feed' => true, 'date' => "2024-01-0{$i}"]);
        }

        $this->render(['enabled' => true, 'limit' => 2]);

        $this->assertSame(['Post 5', 'Post 4'], $this->jsonTitles());
        $this->assertSame(['Post 5', 'Post 4'], $this->rssTitles());
        $this->assertSame(['Post 5', 'Post 4'], $this->atomTitles());
    }

    public function testLimitLargerThanTheItemCountReturnsEverything(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->page("p{$i}.md", ['title' => "Post {$i}", 'feed' => true, 'date' => "2024-01-0{$i}"]);
        }

        $this->render(['enabled' => true, 'limit' => 500]);

        $this->assertCount(3, $this->jsonTitles());
    }

    public function testDefaultLimitIsTwenty(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->page(sprintf('p%02d.md', $i), ['title' => "Post {$i}", 'feed' => true, 'date' => sprintf('2024-02-%02d', $i)]);
        }

        $this->render(['enabled' => true]);

        $titles = $this->jsonTitles();
        $this->assertCount(20, $titles);
        $this->assertSame('Post 25', $titles[0]);
        $this->assertSame('Post 6', $titles[19]);
    }

    public function testLimitDoesNotCapCategoryAtomAndJsonFeeds(): void
    {
        $this->category('blog');
        for ($i = 1; $i <= 4; $i++) {
            $this->page("p{$i}.md", ['title' => "Post {$i}", 'category' => 'Blog', 'date' => "2024-01-0{$i}"]);
        }

        $this->render(['enabled' => true, 'limit' => 2, 'category_formats' => ['rss', 'atom', 'json']]);

        $this->assertCount(2, $this->jsonTitles());
        $this->assertCount(4, $this->jsonTitles('blog/feed.json'));
        $this->assertCount(4, $this->atomTitles('blog/feed.atom'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidLimits(): array
    {
        return [
            'non numeric string' => ['abc'],
            'zero' => [0],
            'negative' => [-1],
            'float' => [2.5],
            'list' => [[5]],
            'numeric string' => ['5'],
        ];
    }

    #[DataProvider('invalidLimits')]
    public function testInvalidLimitFallsBackToTwentyAndLogsWarning(mixed $limit): void
    {
        for ($i = 1; $i <= 22; $i++) {
            $this->page(sprintf('p%02d.md', $i), ['title' => "Post {$i}", 'feed' => true, 'date' => sprintf('2024-02-%02d', $i)]);
        }

        $this->render(['enabled' => true, 'limit' => $limit]);

        $this->assertCount(20, $this->jsonTitles());
        $this->assertStringContainsString('feed.limit must be a positive integer', $this->newLog());
    }

    public function testEmptyItemSetWritesValidEmptyFeeds(): void
    {
        $this->category('blog');

        $this->render(['enabled' => true]);

        $rss = $this->xml('feed.xml');
        $this->assertSame(1, $rss->getElementsByTagName('channel')->length);
        $this->assertSame(0, $rss->getElementsByTagName('item')->length);

        $atom = $this->xml('feed.atom');
        $this->assertSame(0, $atom->getElementsByTagName('entry')->length);
        $this->assertNotSame('', $atom->getElementsByTagName('updated')->item(0)?->textContent);

        $json = $this->json();
        $this->assertSame('https://jsonfeed.org/version/1.1', $json['version']);
        $this->assertSame([], $json['items']);
    }

    public function testEqualDatesAreOrderedByOutputPathAscending(): void
    {
        foreach (['c', 'a', 'b'] as $name) {
            $this->page("{$name}.md", ['title' => "Post {$name}", 'feed' => true, 'date' => '2024-01-01']);
        }

        $this->render(['enabled' => true]);

        $expected = ['Post a', 'Post b', 'Post c'];
        $this->assertSame($expected, $this->jsonTitles());
        $this->assertSame($expected, $this->rssTitles());
        $this->assertSame($expected, $this->atomTitles());
    }

    public function testNewerDateSortsBeforeOlderRegardlessOfPath(): void
    {
        $this->page('a.md', ['title' => 'Older', 'feed' => true, 'date' => '2024-01-01']);
        $this->page('z.md', ['title' => 'Newer', 'feed' => true, 'date' => '2024-01-02']);

        $this->render(['enabled' => true]);

        $this->assertSame(['Newer', 'Older'], $this->jsonTitles());
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function equivalentExclusions(): array
    {
        return [
            'exact slug' => [['news-views']],
            'display name' => [['News & Views']],
            'upper case' => [['NEWS-VIEWS']],
            'punctuation variant' => [['news_views!']],
            'extra whitespace' => [['  news   views  ']],
        ];
    }

    /**
     * @param list<string> $exclude
     */
    #[DataProvider('equivalentExclusions')]
    public function testExcludeCategoriesMatchesBySlugSoNameVariantsAreEquivalent(array $exclude): void
    {
        $this->category('news');
        $this->page('n.md', ['title' => 'Excluded', 'category' => 'News & Views', 'date' => '2024-01-01']);
        $this->page('k.md', ['title' => 'Kept', 'category' => 'Other', 'date' => '2024-01-02']);

        $this->render(['enabled' => true, 'exclude_categories' => $exclude]);

        $this->assertSame(['Kept'], $this->jsonTitles());
        $this->assertSame(['Kept'], $this->rssTitles());
    }

    public function testExcludedCategoryGetsNoCategoryAtomOrJsonFeedsEither(): void
    {
        $this->page('n.md', ['title' => 'Excluded', 'category' => 'Secret', 'date' => '2024-01-01']);
        $this->page('k.md', ['title' => 'Kept', 'category' => 'Other', 'date' => '2024-01-02']);

        $this->render(['enabled' => true, 'exclude_categories' => ['secret'], 'category_formats' => ['atom', 'json']]);

        $this->assertFalse($this->has('secret/feed.atom'));
        $this->assertFalse($this->has('secret/feed.json'));
        $this->assertTrue($this->has('other/feed.atom'));
    }

    public function testExcludingANonexistentCategoryChangesNothing(): void
    {
        $this->page('k.md', ['title' => 'Kept', 'category' => 'Other', 'date' => '2024-01-02']);

        $this->render(['enabled' => true, 'exclude_categories' => ['does-not-exist']]);

        $this->assertSame(['Kept'], $this->jsonTitles());
        $this->assertStringNotContainsString('exclude_categories', $this->newLog());
    }

    public function testExcludeEntryTheSluggerRejectsIsIgnoredWithWarningAndOthersStillApply(): void
    {
        $this->page('n.md', ['title' => 'Excluded', 'category' => 'Secret', 'date' => '2024-01-01']);
        $this->page('k.md', ['title' => 'Kept', 'category' => 'Other', 'date' => '2024-01-02']);

        $this->render(['enabled' => true, 'exclude_categories' => ['!!!', '日本語', 'secret']]);

        $this->assertSame(['Kept'], $this->jsonTitles());
        $this->assertStringContainsString('feed.exclude_categories entry ignored', $this->newLog());
    }

    public function testExcludeCategoriesThatIsNotAListIsIgnoredWithWarning(): void
    {
        $this->page('n.md', ['title' => 'Still Here', 'category' => 'Secret', 'date' => '2024-01-01']);

        $this->render(['enabled' => true, 'exclude_categories' => 'secret']);

        $this->assertSame(['Still Here'], $this->jsonTitles());
        $this->assertStringContainsString('feed.exclude_categories must be a list', $this->newLog());
    }
}
