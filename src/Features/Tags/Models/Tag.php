<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Tags\Models;

class Tag
{
    public string $slug;
    public string $name;
    /** @var TagFile[] */
    public array $files = [];

    public function __construct(string $slug, string $name)
    {
        $this->slug = $slug;
        $this->name = $name;
    }

    public function addFile(TagFile $file): void
    {
        $this->files[] = $file;
    }
}
