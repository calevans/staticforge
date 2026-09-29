<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\SiteFeed;

use DateTimeImmutable;
use EICC\StaticForge\Features\SiteFeed\Models\SiteFeedItem;

trait ItemFactory
{
    /**
     * @param list<string> $tags
     */
    private function item(
        string $title = 'Title',
        string $content = '<p>Body</p>',
        string $summary = 'Summary',
        string $published = '2024-01-01T00:00:00+00:00',
        ?string $updated = null,
        ?string $author = null,
        array $tags = [],
        string $url = 'https://example.com/a.html'
    ): SiteFeedItem {
        return new SiteFeedItem(
            $url,
            $url,
            $title,
            $summary,
            $content,
            new DateTimeImmutable($published),
            new DateTimeImmutable($updated ?? $published),
            $author,
            $tags
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function channel(): array
    {
        return [
            'title' => 'Site',
            'link' => 'https://example.com/',
            'description' => 'Desc',
            'author' => 'Site',
            'feed_url' => 'https://example.com/feed',
        ];
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2030-05-05T05:05:05+00:00');
    }
}
