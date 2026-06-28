<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Tags\Services;

use EICC\StaticForge\Features\Tags\Models\Pagination;

class PaginationService
{
    /**
     * @param array<int, mixed> $items
     * @return array<int, mixed>
     */
    public function sliceForPage(array $items, int $page, int $itemsPerPage): array
    {
        $offset = ($page - 1) * $itemsPerPage;
        return array_slice($items, $offset, $itemsPerPage);
    }

    public function totalPages(int $totalItems, int $itemsPerPage): int
    {
        if ($totalItems === 0) {
            return 1;
        }
        return (int) ceil($totalItems / $itemsPerPage);
    }

    public function buildPagination(int $currentPage, int $totalPages, string $tagUrl): Pagination
    {
        $prevUrl = $currentPage > 1
            ? $this->pageUrl($tagUrl, $currentPage - 1)
            : null;

        $nextUrl = $currentPage < $totalPages
            ? $this->pageUrl($tagUrl, $currentPage + 1)
            : null;

        return new Pagination($currentPage, $totalPages, $prevUrl, $nextUrl);
    }

    /**
     * Page 1 is always the bare tag URL ("/tags/{slug}/").
     * Page N>1 is "/tags/{slug}/page/{n}/".
     */
    public function pageUrl(string $tagUrl, int $page): string
    {
        $base = rtrim($tagUrl, '/');
        return $page <= 1
            ? $base . '/'
            : $base . '/page/' . $page . '/';
    }
}
