<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Tags\Models;

class Pagination
{
    public int $currentPage;
    public int $totalPages;
    public ?string $prevUrl;
    public ?string $nextUrl;

    public function __construct(
        int $currentPage,
        int $totalPages,
        ?string $prevUrl,
        ?string $nextUrl
    ) {
        $this->currentPage = $currentPage;
        $this->totalPages = $totalPages;
        $this->prevUrl = $prevUrl;
        $this->nextUrl = $nextUrl;
    }
}
