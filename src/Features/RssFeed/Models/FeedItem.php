<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\RssFeed\Models;

class FeedItem
{
    public string $title;
    public string $link;
    public string $guid;
    public string $pubDate;
    public ?string $description = null;
    public ?string $content = null;
    public ?string $author = null;
    /** @var array{url: string, length: int, type: string}|null */
    public ?array $enclosure = null;
    /** @var array<string, mixed> */
    public array $metadata = [];

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $title,
        string $link,
        string $guid,
        string $pubDate,
        array $metadata = []
    ) {
        $this->title = $title;
        $this->link = $link;
        $this->guid = $guid;
        $this->pubDate = $pubDate;
        $this->metadata = $metadata;
    }
}
