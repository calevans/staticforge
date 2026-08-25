<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Tags;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureFactory;
use EICC\StaticForge\Features\Tags\Feature;
use EICC\StaticForge\Features\Tags\Services\TagPageService;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class FeatureTest extends UnitTestCase
{
    private Feature $feature;
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();
        $feature = (new FeatureFactory($this->container))->make(Feature::class);
        $this->assertInstanceOf(Feature::class, $feature);
        $this->feature = $feature;
        $this->eventManager = new EventManager();
        $this->feature->register($this->eventManager);
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
        $listeners = $this->eventManager->getListeners('POST_LOOP');

        $this->assertCount(1, $listeners);
        $this->assertSame([$this->feature, 'generateTagPages'], $listeners[0]['callback']);
        $this->assertSame(110, $listeners[0]['priority']);
    }

    public function testHandlePreRenderReturnsParametersUnchangedWhenBypassFlagSet(): void
    {
        $event = new RenderEvent(
            name: 'PRE_RENDER',
            filePath: '__tag__:php',
            fileUrl: '',
            metadata: [],
            extra: ['bypass_tag_defer' => true, 'some_other_key' => 'value'],
        );

        $this->feature->handlePreRender($event);

        $this->assertArrayNotHasKey('tag_data', $event->extra);
    }
}
