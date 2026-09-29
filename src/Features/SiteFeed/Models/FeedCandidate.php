<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\SiteFeed\Models;

/**
 * A page that passed the hidden-page rules, plus what the writer needs to
 * decide which feeds it belongs to.
 */
final class FeedCandidate
{
    public function __construct(
        public readonly SiteFeedItem $item,
        public readonly string $path,
        public readonly ?string $categorySlug,
        public readonly ?string $categoryName,
        public readonly bool $optIn,
        public readonly bool $optOut,
    ) {
    }
}
