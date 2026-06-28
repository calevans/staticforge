<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\CategoryIndex;

use EICC\StaticForge\Features\CategoryIndex\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class FeatureTest extends UnitTestCase
{
    private Feature $feature;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feature = new Feature();
        $this->feature->setContainer($this->container);
    }

    private function resolveItemsPerPage(): int
    {
        $reflection = new \ReflectionClass($this->feature);
        $method = $reflection->getMethod('resolveItemsPerPage');
        $method->setAccessible(true);
        return $method->invoke($this->feature);
    }

    public function testDefaultsToTenWhenSiteConfigMissing(): void
    {
        $this->setContainerVariable('site_config', []);
        $this->assertSame(10, $this->resolveItemsPerPage());
    }

    public function testDefaultsToTenWhenCategoryIndexKeyMissing(): void
    {
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test']]);
        $this->assertSame(10, $this->resolveItemsPerPage());
    }

    public function testDefaultsToTenWhenItemsPerPageIsZero(): void
    {
        $this->setContainerVariable('site_config', ['category_index' => ['items_per_page' => 0]]);
        $this->assertSame(10, $this->resolveItemsPerPage());
    }

    public function testDefaultsToTenWhenItemsPerPageIsNegative(): void
    {
        $this->setContainerVariable('site_config', ['category_index' => ['items_per_page' => -5]]);
        $this->assertSame(10, $this->resolveItemsPerPage());
    }

    public function testDefaultsToTenWhenItemsPerPageIsNonNumeric(): void
    {
        $this->setContainerVariable('site_config', ['category_index' => ['items_per_page' => 'abc']]);
        $this->assertSame(10, $this->resolveItemsPerPage());
    }

    public function testReturnsConfiguredValueWhenValid(): void
    {
        $this->setContainerVariable('site_config', ['category_index' => ['items_per_page' => 5]]);
        $this->assertSame(5, $this->resolveItemsPerPage());
    }
}
