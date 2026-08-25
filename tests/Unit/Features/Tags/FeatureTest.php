<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Tags;

use EICC\StaticForge\Core\FeatureFactory;
use EICC\StaticForge\Features\Tags\Feature;
use EICC\StaticForge\Features\Tags\Services\TagPageService;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class FeatureTest extends UnitTestCase
{
    private Feature $feature;

    protected function setUp(): void
    {
        parent::setUp();
        $feature = (new FeatureFactory($this->container))->make(Feature::class);
        $this->assertInstanceOf(Feature::class, $feature);
        $this->feature = $feature;
    }

    public function testDefaultsToTenWhenSiteConfigMissing(): void
    {
        $this->assertSame(10, TagPageService::resolveItemsPerPage([]));
    }

    public function testDefaultsToTenWhenTagsKeyMissing(): void
    {
        $this->assertSame(10, TagPageService::resolveItemsPerPage(['site' => ['name' => 'Test']]));
    }

    public function testDefaultsToTenWhenItemsPerPageIsZero(): void
    {
        $config = ['tags' => ['items_per_page' => 0]];
        $this->assertSame(10, TagPageService::resolveItemsPerPage($config));
    }

    public function testDefaultsToTenWhenItemsPerPageIsNegative(): void
    {
        $config = ['tags' => ['items_per_page' => -5]];
        $this->assertSame(10, TagPageService::resolveItemsPerPage($config));
    }

    public function testDefaultsToTenWhenItemsPerPageIsNonNumeric(): void
    {
        $config = ['tags' => ['items_per_page' => 'abc']];
        $this->assertSame(10, TagPageService::resolveItemsPerPage($config));
    }

    public function testReturnsConfiguredValueWhenValid(): void
    {
        $config = ['tags' => ['items_per_page' => 5]];
        $this->assertSame(5, TagPageService::resolveItemsPerPage($config));
    }

    public function testEventListenersIncludePostLoop(): void
    {
        $reflection = new \ReflectionClass($this->feature);
        $prop = $reflection->getProperty('eventListeners');
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
