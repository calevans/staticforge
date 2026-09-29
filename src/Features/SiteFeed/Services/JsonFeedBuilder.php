<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\SiteFeed\Services;

use DateTimeImmutable;

class JsonFeedBuilder implements FeedBuilderInterface
{
    public function getFileName(): string
    {
        return 'feed.json';
    }

    public function build(array $channel, array $items, DateTimeImmutable $now): string
    {
        $feed = [
            'version' => 'https://jsonfeed.org/version/1.1',
            'title' => FeedText::scrub((string) ($channel['title'] ?? '')),
            'home_page_url' => FeedText::scrub((string) ($channel['link'] ?? '')),
            'feed_url' => FeedText::scrub((string) ($channel['feed_url'] ?? '')),
            'description' => FeedText::scrub((string) ($channel['description'] ?? '')),
        ];
        if (!empty($channel['language']) && is_string($channel['language'])) {
            $feed['language'] = FeedText::scrub($channel['language']);
        }

        $entries = [];
        foreach ($items as $item) {
            $entry = [
                'id' => FeedText::scrub($item->id),
                'url' => FeedText::scrub($item->url),
                'title' => FeedText::scrub($item->title),
                'content_html' => FeedText::scrub($item->contentHtml),
                'summary' => FeedText::scrub($item->summary),
                'date_published' => $item->published->format(DATE_ATOM),
                'date_modified' => $item->updated->format(DATE_ATOM),
            ];
            if ($item->author !== null && $item->author !== '') {
                $entry['authors'] = [['name' => FeedText::scrub($item->author)]];
            }
            if ($item->tags !== []) {
                $entry['tags'] = array_map(FeedText::scrub(...), $item->tags);
            }
            $entries[] = $entry;
        }
        $feed['items'] = $entries;

        return json_encode(
            $feed,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
