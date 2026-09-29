<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\SiteFeed\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Supplies the timezone frontmatter dates are read in, and "now" for empty
 * feeds. Extend or replace in tests to pin both.
 */
class FeedClock
{
    public function timezone(): DateTimeZone
    {
        return new DateTimeZone(date_default_timezone_get());
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone());
    }
}
