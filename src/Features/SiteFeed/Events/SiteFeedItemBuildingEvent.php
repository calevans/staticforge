<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\SiteFeed\Events;

use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Features\SiteFeed\Models\SiteFeedItem;

/**
 * Fired by SiteFeedWriter for each item of each feed. The item is a per-feed
 * copy, so changes affect only the feed named by $format and $scope.
 */
class SiteFeedItemBuildingEvent extends Event
{
    public function __construct(
        string $name,
        public readonly string $format,
        public readonly string $scope,
        public readonly SiteFeedItem $item,
    ) {
        parent::__construct($name);
    }
}
