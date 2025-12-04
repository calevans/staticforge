<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\RssFeed\Models;

class FeedChannel
{
    public string $title;
    public string $link;
    public string $description;
    public string $language = 'en-us';
    public ?string $copyright = null;
    public string $lastBuildDate;
    public string $atomLink;
    /** @var array<string, mixed> */
    public array $metadata = [];

    public function __construct(
        string $title,
        string $link,
        string $description,
        string $atomLink,
        array $metadata = []
    ) {
        $this->title = $title;
        $this->link = $link;
        $this->description = $description;
        $this->atomLink = $atomLink;
        $this->metadata = $metadata;
        $this->lastBuildDate = date('r');
    }
}
