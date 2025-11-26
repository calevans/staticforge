<?php

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;

class BaseFeatureTest extends UnitTestCase
{
    private TestFeature $feature;

    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();
        // Use bootstrapped container from parent::setUp()
        $this->eventManager = new EventManager($this->container);
        $this->feature = new TestFeature();
    }

    public function testRegisterEventListeners(): void
    {
        $this->feature->register($this->eventManager, $this->container);

        // Test that events are actually registered with correct priorities
        $listeners = $this->eventManager->getListeners('TEST_EVENT');
        $this->assertCount(1, $listeners);
        $this->assertEquals(100, $listeners[0]['priority']);

        $listeners = $this->eventManager->getListeners('ANOTHER_EVENT');
        $this->assertCount(1, $listeners);
        $this->assertEquals(50, $listeners[0]['priority']);
    }

    public function testEventListenerExecution(): void
    {
        $this->feature->register($this->eventManager, $this->container);

        // Test that event listeners are properly callable and modify parameters
        $parameters = ['test' => 'value'];
        $result = $this->eventManager->fire('TEST_EVENT', $parameters);

        $this->assertEquals(['test' => 'value', 'processed' => true], $result);
    }
}

class TestFeature extends BaseFeature
{
    protected array $eventListeners = [
        'TEST_EVENT' => ['method' => 'handleTestEvent', 'priority' => 100],
        'ANOTHER_EVENT' => ['method' => 'handleAnotherEvent', 'priority' => 50]
    ];

    public function handleTestEvent(Container $container, array $parameters): array
    {
        $parameters['processed'] = true;
        return $parameters;
    }

    public function handleAnotherEvent(Container $container, array $parameters): array
    {
        $parameters['another'] = true;
        return $parameters;
    }
}