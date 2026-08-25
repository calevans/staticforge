<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core\Events;

use EICC\StaticForge\Features\RssFeed\Services\RssBuilder;

/**
 * Fired by RssFeedService when the RSS builder is initialized for a
 * category feed, allowing a Feature to modify channel-level metadata before
 * the feed XML is assembled.
 */
class RssBuilderInitEvent extends Event
{
    /**
     * @param array<string, mixed> $categoryMetadata
     */
    public function __construct(
        string $name,
        public readonly RssBuilder $builder,
        public array $categoryMetadata,
    ) {
        parent::__construct($name);
    }
}
