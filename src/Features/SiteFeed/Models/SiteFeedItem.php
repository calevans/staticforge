<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\SiteFeed\Models;

use DateTimeImmutable;

/**
 * One entry in a site or category feed. Mutable on purpose: SITE_FEED_ITEM_BUILDING
 * listeners may adjust it before a builder serializes it.
 */
class SiteFeedItem
{
    /**
     * @param list<string> $tags
     * @param array<string, mixed> $metadata Frontmatter of the source page
     */
    public function __construct(
        public string $id,
        public string $url,
        public string $title,
        public string $summary,
        public string $contentHtml,
        public DateTimeImmutable $published,
        public DateTimeImmutable $updated,
        public ?string $author = null,
        public array $tags = [],
        public array $metadata = [],
    ) {
    }
}
