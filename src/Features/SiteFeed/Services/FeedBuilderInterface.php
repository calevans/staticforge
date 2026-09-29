<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\SiteFeed\Services;

use EICC\StaticForge\Features\SiteFeed\Models\SiteFeedItem;

interface FeedBuilderInterface
{
    public function getFileName(): string;

    /**
     * @param array<string, mixed> $channel title, link, description, feed_url, author, language
     * @param list<SiteFeedItem> $items
     */
    public function build(array $channel, array $items, \DateTimeImmutable $now): string;
}
