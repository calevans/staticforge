<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\CategoryIndex\Models;

class Category
{
    public string $slug;
    public string $title;
    public ?string $menuPosition = null;
    /** @var array<string, mixed> */
    public array $metadata = [];
    /** @var CategoryFile[] */
    public array $files = [];

    public function __construct(string $slug, array $metadata)
    {
        $this->slug = $slug;
        $this->metadata = $metadata;
        $this->title = $metadata['title'] ?? ucfirst($slug);
        $this->menuPosition = isset($metadata['menu']) ? (string)$metadata['menu'] : null;
    }

    public function addFile(CategoryFile $file): void
    {
        $this->files[] = $file;
    }
}
