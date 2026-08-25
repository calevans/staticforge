<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\CategoryIndex;

use EICC\StaticForge\Features\CategoryIndex\Services\CategoryPageService;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

/**
 * Config resolution moved from a private Feature method to
 * CategoryPageService::resolveItemsPerPage() so it can be shared between the
 * Feature and its bootstrap.php registration without a reflection-only test.
 */
class FeatureTest extends UnitTestCase
{
    public function testDefaultsToTenWhenSiteConfigMissing(): void
    {
        $this->assertSame(10, CategoryPageService::resolveItemsPerPage([]));
    }

    public function testDefaultsToTenWhenCategoryIndexKeyMissing(): void
    {
        $this->assertSame(10, CategoryPageService::resolveItemsPerPage(['site' => ['name' => 'Test']]));
    }

    public function testDefaultsToTenWhenItemsPerPageIsZero(): void
    {
        $config = ['category_index' => ['items_per_page' => 0]];
        $this->assertSame(10, CategoryPageService::resolveItemsPerPage($config));
    }

    public function testDefaultsToTenWhenItemsPerPageIsNegative(): void
    {
        $config = ['category_index' => ['items_per_page' => -5]];
        $this->assertSame(10, CategoryPageService::resolveItemsPerPage($config));
    }

    public function testDefaultsToTenWhenItemsPerPageIsNonNumeric(): void
    {
        $config = ['category_index' => ['items_per_page' => 'abc']];
        $this->assertSame(10, CategoryPageService::resolveItemsPerPage($config));
    }

    public function testReturnsConfiguredValueWhenValid(): void
    {
        $config = ['category_index' => ['items_per_page' => 5]];
        $this->assertSame(5, CategoryPageService::resolveItemsPerPage($config));
    }
}
