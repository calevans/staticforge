<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\CategoryIndex\Services;

use EICC\StaticForge\Features\CategoryIndex\Models\Pagination;

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

    public function buildPagination(int $currentPage, int $totalPages, string $categoryUrl): Pagination
    {
        $prevUrl = $currentPage > 1
            ? $this->pageUrl($categoryUrl, $currentPage - 1)
            : null;

        $nextUrl = $currentPage < $totalPages
            ? $this->pageUrl($categoryUrl, $currentPage + 1)
            : null;

        return new Pagination($currentPage, $totalPages, $prevUrl, $nextUrl);
    }

    /**
     * Page 1 is always the bare category URL ("/{slug}/").
     * Page N>1 is "/{slug}/page/{n}/".
     */
    public function pageUrl(string $categoryUrl, int $page): string
    {
        $base = rtrim($categoryUrl, '/');
        return $page <= 1
            ? $base . '/'
            : $base . '/page/' . $page . '/';
    }
}
