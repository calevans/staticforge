<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Tags;

use EICC\StaticForge\Features\Tags\Feature;
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

    public function testDefaultsToTenWhenTagsKeyMissing(): void
    {
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test']]);
        $this->assertSame(10, $this->resolveItemsPerPage());
    }

    public function testDefaultsToTenWhenItemsPerPageIsZero(): void
    {
        $this->setContainerVariable('site_config', ['tags' => ['items_per_page' => 0]]);
        $this->assertSame(10, $this->resolveItemsPerPage());
    }

    public function testDefaultsToTenWhenItemsPerPageIsNegative(): void
    {
        $this->setContainerVariable('site_config', ['tags' => ['items_per_page' => -5]]);
        $this->assertSame(10, $this->resolveItemsPerPage());
    }

    public function testDefaultsToTenWhenItemsPerPageIsNonNumeric(): void
    {
        $this->setContainerVariable('site_config', ['tags' => ['items_per_page' => 'abc']]);
        $this->assertSame(10, $this->resolveItemsPerPage());
    }

    public function testReturnsConfiguredValueWhenValid(): void
    {
        $this->setContainerVariable('site_config', ['tags' => ['items_per_page' => 5]]);
        $this->assertSame(5, $this->resolveItemsPerPage());
    }

    public function testEventListenersIncludePostLoop(): void
    {
        $reflection = new \ReflectionClass($this->feature);
        $prop = $reflection->getProperty('eventListeners');
        $prop->setAccessible(true);
        $listeners = $prop->getValue($this->feature);

        $this->assertArrayHasKey('POST_LOOP', $listeners);
        $this->assertSame('generateTagPages', $listeners['POST_LOOP']['method']);
        $this->assertSame(110, $listeners['POST_LOOP']['priority']);
    }

    public function testHandlePreRenderReturnsParametersUnchangedWhenBypassFlagSet(): void
    {
        $parameters = [
            'bypass_tag_defer' => true,
            'file_path' => '__tag__:php',
            'some_other_key' => 'value',
        ];

        $result = $this->feature->handlePreRender($this->container, $parameters);

        $this->assertSame($parameters, $result);
    }
}
