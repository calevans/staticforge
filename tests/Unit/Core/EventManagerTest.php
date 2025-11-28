<?php

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;

class EventManagerTest extends UnitTestCase
{
    private EventManager $eventManager;


    protected function setUp(): void
    {
        parent::setUp();
        // Use bootstrapped container from parent::setUp()
        $this->eventManager = new EventManager($this->container);
    }

    public function testRegisterEvent(): void
    {
        $this->eventManager->registerEvent('TEST_EVENT');

        $events = $this->eventManager->list();
        $this->assertContains('TEST_EVENT', $events);
    }

    public function testRegisterEventDuplicateIgnored(): void
    {
        $this->eventManager->registerEvent('TEST_EVENT');
        $this->eventManager->registerEvent('TEST_EVENT');

        $events = $this->eventManager->list();
        $this->assertEquals(1, array_count_values($events)['TEST_EVENT']);
    }

    public function testRegisterListener(): void
    {
        $callback = [new TestListener(), 'handle'];
        $this->eventManager->registerListener('TEST_EVENT', $callback);

        $listeners = $this->eventManager->getListeners('TEST_EVENT');
        $this->assertCount(1, $listeners);
        $this->assertEquals($callback, $listeners[0]['callback']);
        $this->assertEquals(100, $listeners[0]['priority']);
    }

    public function testRegisterListenerWithPriority(): void
    {
        $callback = [new TestListener(), 'handle'];
        $this->eventManager->registerListener('TEST_EVENT', $callback, 50);

        $listeners = $this->eventManager->getListeners('TEST_EVENT');
        $this->assertEquals(50, $listeners[0]['priority']);
    }

    public function testPriorityOrdering(): void
    {
        $listener1 = [new TestListener('first'), 'handle'];
        $listener2 = [new TestListener('second'), 'handle'];
        $listener3 = [new TestListener('third'), 'handle'];

        // Register in reverse priority order
        $this->eventManager->registerListener('TEST_EVENT', $listener2, 200);
        $this->eventManager->registerListener('TEST_EVENT', $listener1, 100);
        $this->eventManager->registerListener('TEST_EVENT', $listener3, 50);

        $listeners = $this->eventManager->getListeners('TEST_EVENT');
        $this->assertEquals(50, $listeners[0]['priority']);  // third (highest priority)
        $this->assertEquals(100, $listeners[1]['priority']); // first
        $this->assertEquals(200, $listeners[2]['priority']); // second (lowest priority)
    }

    public function testUnregisterListener(): void
    {
        $callback = [new TestListener(), 'handle'];
        $this->eventManager->registerListener('TEST_EVENT', $callback);
        $this->eventManager->unregisterListener('TEST_EVENT', $callback);

        $listeners = $this->eventManager->getListeners('TEST_EVENT');
        $this->assertEmpty($listeners);
    }

    public function testFireEventWithNoListeners(): void
    {
        $parameters = ['key' => 'value'];
        $result = $this->eventManager->fire('NONEXISTENT_EVENT', $parameters);

        $this->assertEquals($parameters, $result);
    }

    public function testFireEventParameterChaining(): void
    {
        $listener1 = new ParameterModifyingListener('step1');
        $listener2 = new ParameterModifyingListener('step2');

        $this->eventManager->registerListener('TEST_EVENT', [$listener1, 'handle'], 100);
        $this->eventManager->registerListener('TEST_EVENT', [$listener2, 'handle'], 200);

        $parameters = ['steps' => []];
        $result = $this->eventManager->fire('TEST_EVENT', $parameters);

        $this->assertEquals(['steps' => ['step1', 'step2']], $result);
    }

    public function testFireEventWithContainerParameter(): void
    {
        $this->setContainerVariable('test_value', 'container_data');
        $listener = new ContainerAwareListener();

        $this->eventManager->registerListener('TEST_EVENT', [$listener, 'handle']);

        $parameters = ['data' => 'original'];
        $result = $this->eventManager->fire('TEST_EVENT', $parameters);

        $this->assertEquals(['data' => 'container_data'], $result);
    }
}

class TestListener
{
    private string $name;

    public function __construct(string $name = 'test')
    {
        $this->name = $name;
    }

    public function handle(Container $container, array $parameters): array
    {
        return $parameters;
    }
}

class ParameterModifyingListener
{
    private string $step;

    public function __construct(string $step)
    {
        $this->step = $step;
    }

    public function handle(Container $container, array $parameters): array
    {
        $parameters['steps'][] = $this->step;
        return $parameters;
    }
}

class ContainerAwareListener
{
    public function handle(Container $container, array $parameters): array
    {
        $value = $container->getVariable('test_value');
        return ['data' => $value];
    }
}
