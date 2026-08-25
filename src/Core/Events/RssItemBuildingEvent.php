<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core\Events;

use EICC\StaticForge\Features\RssFeed\Models\FeedItem;

/**
 * Fired by RssFeedService for each item being added to the feed, allowing a
 * Feature (e.g. a Podcast package adding enclosure tags) to modify it before
 * it's written.
 */
class RssItemBuildingEvent extends Event
{
    /**
     * @param array<string, mixed> $file
     */
    public function __construct(
        string $name,
        public readonly FeedItem $item,
        public readonly array $file,
    ) {
        parent::__construct($name);
    }
}
