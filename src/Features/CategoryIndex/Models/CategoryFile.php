<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\CategoryIndex\Models;

class CategoryFile
{
    public string $title;
    public string $url;
    public string $date;
    public ?string $image = null;
    /** @var array<string, mixed> */
    public array $metadata = [];

    public function __construct(
        string $title,
        string $url,
        string $date,
        array $metadata = []
    ) {
        $this->title = $title;
        $this->url = $url;
        $this->date = $date;
        $this->metadata = $metadata;
    }
}
