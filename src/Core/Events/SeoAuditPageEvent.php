<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core\Events;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Fired by SeoCommand for each rendered page during `audit:seo`, so a
 * Feature can add its own checks to the audit's issue list.
 */
class SeoAuditPageEvent extends Event
{
    /**
     * @param array<int, array{file: string, type: string, message: string}> $issues
     */
    public function __construct(
        string $name,
        public readonly Crawler $crawler,
        public readonly string $filename,
        public array $issues,
    ) {
        parent::__construct($name);
    }
}
