<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Tags\Models;

class TagFile
{
    public string $title;
    public string $url;
    public string $date;
    /** @var array<string, mixed> */
    public array $metadata = [];

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(string $title, string $url, string $date, array $metadata = [])
    {
        $this->title = $title;
        $this->url = $url;
        $this->date = $date;
        $this->metadata = $metadata;
    }
}
