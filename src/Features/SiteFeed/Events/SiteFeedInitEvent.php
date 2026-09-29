<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\SiteFeed\Events;

use EICC\StaticForge\Core\Events\Event;

/**
 * Fired by SiteFeedWriter before each feed is built. Listeners may change
 * $channel (title, link, description, feed_url, author, language).
 */
class SiteFeedInitEvent extends Event
{
    /**
     * @param string $format rss|atom|json
     * @param string $scope 'site' or the category slug
     * @param array<string, mixed> $channel
     */
    public function __construct(
        string $name,
        public readonly string $format,
        public readonly string $scope,
        public array $channel,
    ) {
        parent::__construct($name);
    }
}
