<?php

namespace EICC\StaticForge\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;

class BaseFeatureTest extends TestCase
{
    private TestFeature $feature;
    private Container $container;
    private EventManager $eventManager;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->eventManager = new EventManager($this->container);
        $this->feature = new TestFeature();
    }

    public function testRegisterStoresReferences(): void
    {
        $this->feature->register($this->eventManager, $this->container);

        $this->assertSame($this->container, $this->feature->getContainer());
        $this->assertSame($this->eventManager, $this->feature->getEventManager());
    }

    public function testGetEventListeners(): void
    {
        $listeners = $this->feature->getEventListeners();

        $this->assertEquals(['TEST_EVENT', 'ANOTHER_EVENT'], $listeners);
    }

    public function testRegisterEventListeners(): void
    {
        $this->feature->register($this->eventManager, $this->container);

        $listeners = $this->eventManager->getListeners('TEST_EVENT');
        $this->assertCount(1, $listeners);
        $this->assertEquals(150, $listeners[0]['priority']);
    }

    public function testFeatureDataMethods(): void
    {
        $this->feature->register($this->eventManager, $this->container);

        // Initialize features array (simulating FeatureManager)
        $this->container->setVariable('features', ['existing' => ['data' => 'test']]);

        // Test getting feature data
        $retrievedData = $this->feature->getTestFeatureData('existing');
        $this->assertEquals(['data' => 'test'], $retrievedData);

        // Test getting nonexistent feature data
        $emptyData = $this->feature->getTestFeatureData('NonexistentFeature');
        $this->assertEquals([], $emptyData);
    }

    public function testGetFeaturesMethod(): void
    {
        $this->feature->register($this->eventManager, $this->container);

        // Test with no features array
        $features = $this->feature->getTestFeatures();
        $this->assertEquals([], $features);

        // Test with features array
        $this->container->setVariable('features', ['test' => ['data' => 'value']]);
        $features = $this->feature->getTestFeatures();
        $this->assertEquals(['test' => ['data' => 'value']], $features);
    }
}

class TestFeature extends BaseFeature
{
    protected array $eventListeners = [
        'TEST_EVENT' => ['method' => 'handleTestEvent', 'priority' => 150],
        'ANOTHER_EVENT' => ['method' => 'handleAnotherEvent']
    ];

    public function handleTestEvent(Container $container, array $parameters): array
    {
        return $parameters;
    }

    public function handleAnotherEvent(Container $container, array $parameters): array
    {
        return $parameters;
    }

    // Expose protected methods for testing
    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getEventManager(): EventManager
    {
        return $this->eventManager;
    }

    public function getTestFeatures(): array
    {
        return $this->getFeatures();
    }

    public function getTestFeatureData(string $featureName): array
    {
        return $this->getFeatureData($featureName);
    }
}