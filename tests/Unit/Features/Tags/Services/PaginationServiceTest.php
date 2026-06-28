<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Tags\Services;

use EICC\StaticForge\Features\Tags\Services\PaginationService;
use PHPUnit\Framework\TestCase;

class PaginationServiceTest extends TestCase
{
    private PaginationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaginationService();
    }

    public function testTotalPagesWithZeroItems(): void
    {
        $this->assertSame(1, $this->service->totalPages(0, 10));
    }

    public function testTotalPagesWithExactlyOnePageWorth(): void
    {
        $this->assertSame(1, $this->service->totalPages(10, 10));
    }

    public function testTotalPagesWithOneMoreThanOnePageWorth(): void
    {
        $this->assertSame(2, $this->service->totalPages(11, 10));
    }

    public function testTotalPagesWithExactMultiple(): void
    {
        $this->assertSame(2, $this->service->totalPages(20, 10));
    }

    public function testSliceForPageReturnsFirstPage(): void
    {
        $items = range(1, 25);
        $this->assertSame(range(1, 10), $this->service->sliceForPage($items, 1, 10));
    }

    public function testSliceForPageReturnsSecondPage(): void
    {
        $items = range(1, 25);
        $this->assertSame(range(11, 20), $this->service->sliceForPage($items, 2, 10));
    }

    public function testSliceForPageReturnsPartialLastPage(): void
    {
        $items = range(1, 25);
        $this->assertSame(range(21, 25), $this->service->sliceForPage($items, 3, 10));
    }

    public function testSliceForPageOutOfRangeReturnsEmptyArray(): void
    {
        $items = range(1, 20);
        $this->assertSame([], $this->service->sliceForPage($items, 5, 10));
    }

    public function testPageUrlForFirstPage(): void
    {
        $this->assertSame('/tags/php/', $this->service->pageUrl('/tags/php', 1));
    }

    public function testPageUrlForSecondPage(): void
    {
        $this->assertSame('/tags/php/page/2/', $this->service->pageUrl('/tags/php', 2));
    }

    public function testPageUrlTrailingSlashIdempotency(): void
    {
        $this->assertSame(
            $this->service->pageUrl('/tags/php', 2),
            $this->service->pageUrl('/tags/php/', 2)
        );
    }

    public function testBuildPaginationFirstOfThree(): void
    {
        $pagination = $this->service->buildPagination(1, 3, '/tags/php/');

        $this->assertNull($pagination->prevUrl);
        $this->assertSame('/tags/php/page/2/', $pagination->nextUrl);
        $this->assertSame(1, $pagination->currentPage);
        $this->assertSame(3, $pagination->totalPages);
    }

    public function testBuildPaginationMiddleOfThree(): void
    {
        $pagination = $this->service->buildPagination(2, 3, '/tags/php/');

        $this->assertSame('/tags/php/', $pagination->prevUrl);
        $this->assertSame('/tags/php/page/3/', $pagination->nextUrl);
    }

    public function testBuildPaginationLastOfThree(): void
    {
        $pagination = $this->service->buildPagination(3, 3, '/tags/php/');

        $this->assertSame('/tags/php/page/2/', $pagination->prevUrl);
        $this->assertNull($pagination->nextUrl);
    }

    public function testBuildPaginationSinglePage(): void
    {
        $pagination = $this->service->buildPagination(1, 1, '/tags/php/');

        $this->assertNull($pagination->prevUrl);
        $this->assertNull($pagination->nextUrl);
    }
}
