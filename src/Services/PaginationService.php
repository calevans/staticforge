<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services;

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

    public function buildPagination(int $currentPage, int $totalPages, string $baseUrl): Pagination
    {
        $prevUrl = $currentPage > 1
            ? $this->pageUrl($baseUrl, $currentPage - 1)
            : null;

        $nextUrl = $currentPage < $totalPages
            ? $this->pageUrl($baseUrl, $currentPage + 1)
            : null;

        return new Pagination($currentPage, $totalPages, $prevUrl, $nextUrl);
    }

    /**
     * Page 1 is always the bare base URL ("/tech/", "/tags/php/").
     * Page N>1 is "/tech/page/{n}/", "/tags/php/page/{n}/".
     */
    public function pageUrl(string $baseUrl, int $page): string
    {
        $base = rtrim($baseUrl, '/');
        return $page <= 1
            ? $base . '/'
            : $base . '/page/' . $page . '/';
    }
}
