<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration\SiteFeed;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * SEC-5 / SEC2-8: pages the author hid must not be published by any new feed.
 */
class SiteFeedHiddenPagesTest extends SiteFeedTestCase
{
    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function hiddenFrontmatter(): array
    {
        return [
            'draft true' => [['draft' => true]],
            'draft "true"' => [['draft' => 'true']],
            'draft "yes"' => [['draft' => 'yes']],
            'noindex true' => [['noindex' => true]],
            'noindex "true"' => [['noindex' => 'true']],
            'noindex "1"' => [['noindex' => '1']],
            'robots no' => [['robots' => 'no']],
            'robots No' => [['robots' => 'No']],
            'robots false' => [['robots' => false]],
            'sitemap false' => [['sitemap' => false]],
            'sitemap "false"' => [['sitemap' => 'false']],
            'sitemap "no"' => [['sitemap' => 'no']],
        ];
    }

    /**
     * @param array<string, mixed> $hidden
     */
    #[DataProvider('hiddenFrontmatter')]
    public function testHiddenPageIsAbsentFromSiteFeedsAndCategoryAtomAndJson(array $hidden): void
    {
        $this->category('blog');
        $this->page('visible.md', ['title' => 'Visible', 'category' => 'Blog', 'date' => '2024-01-01']);
        $this->page('hidden.md', ['title' => 'Hidden Page', 'category' => 'Blog', 'date' => '2024-01-02'] + $hidden);
        $this->page('loose.md', ['title' => 'Hidden Loose', 'feed' => true, 'date' => '2024-01-03'] + $hidden);

        $this->render(['enabled' => true, 'category_formats' => ['rss', 'atom', 'json']]);

        foreach (['feed.xml', 'feed.atom', 'feed.json', 'blog/feed.atom', 'blog/feed.json'] as $feed) {
            $this->assertStringNotContainsString('Hidden', $this->read($feed), $feed);
            $this->assertStringContainsString('Visible', $this->read($feed), $feed);
        }
    }

    public function testThe404PageIsAbsentFromEveryNewFeed(): void
    {
        $this->category('blog');
        $this->page('visible.md', ['title' => 'Visible', 'category' => 'Blog', 'date' => '2024-01-01']);
        $this->page('404.md', ['title' => 'Page Gone', 'date' => '2024-01-02', 'feed' => true]);

        $this->render(['enabled' => true, 'category_formats' => ['atom', 'json']]);

        $this->assertTrue($this->has('404.html'));
        foreach (['feed.xml', 'feed.atom', 'feed.json', 'blog/feed.atom', 'blog/feed.json'] as $feed) {
            $this->assertStringNotContainsString('Page Gone', $this->read($feed), $feed);
        }
    }

    public function testGeneratedCategoryIndexPageIsAbsentEvenWhenItsDefinitionHasADate(): void
    {
        $this->page('blog.md', ['title' => 'Blog Index Title', 'type' => 'category', 'date' => '2024-09-09', 'feed' => true], '');
        $this->page('post.md', ['title' => 'Real Post', 'category' => 'Blog', 'date' => '2024-01-01']);

        $this->render(['enabled' => true, 'category_formats' => ['atom', 'json']]);

        $this->assertTrue($this->has('blog/index.html'));
        foreach (['feed.xml', 'feed.atom', 'feed.json', 'blog/feed.atom', 'blog/feed.json'] as $feed) {
            $this->assertStringNotContainsString('Blog Index Title', $this->read($feed), $feed);
            $this->assertStringContainsString('Real Post', $this->read($feed), $feed);
        }
    }

    public function testGeneratedTagPagesAreAbsentFromEveryNewFeed(): void
    {
        $this->category('blog');
        $this->page('post.md', [
            'title' => 'Tagged Post',
            'category' => 'Blog',
            'date' => '2024-01-01',
            'tags' => ['alpha', 'beta'],
        ]);
        foreach (['tag.html.twig', 'tag-index.html.twig', 'tags.html.twig'] as $tpl) {
            copy($this->templateDir . '/sample/category-index.html.twig', $this->templateDir . '/sample/' . $tpl);
        }

        $this->render(['enabled' => true, 'category_formats' => ['atom', 'json']]);

        $tagPages = glob($this->outputDir . '/tags/*/index.html') ?: [];
        $this->assertNotEmpty($tagPages, 'fixture must actually generate tag pages');
        foreach (['feed.xml', 'feed.atom', 'feed.json', 'blog/feed.atom', 'blog/feed.json'] as $feed) {
            $this->assertSame(['Tagged Post'], $this->titlesOf($feed), $feed);
        }
    }

    public function testQuotedFalseFeedOptOutIsHonouredForTheSiteFeedOnly(): void
    {
        $this->category('blog');
        $this->page('a.md', ['title' => 'Opted Out', 'category' => 'Blog', 'date' => '2024-01-01', 'feed' => 'false']);

        $this->render(['enabled' => true, 'category_formats' => ['atom', 'json']]);

        $this->assertSame([], $this->jsonTitles());
        $this->assertSame(['Opted Out'], $this->jsonTitles('blog/feed.json'));
    }

    /**
     * @return list<string>
     */
    private function titlesOf(string $feed): array
    {
        return match (pathinfo($feed, PATHINFO_EXTENSION)) {
            'xml' => $this->rssTitles($feed),
            'atom' => $this->atomTitles($feed),
            default => $this->jsonTitles($feed),
        };
    }
}
