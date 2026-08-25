<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Events\Event;

class EventManagerTest extends UnitTestCase
{
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventManager = new EventManager();
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

        // Confirm ordering also reflects the correct listener instances, not just priorities
        $instance0 = $listeners[0]['callback'][0];
        $instance1 = $listeners[1]['callback'][0];
        $instance2 = $listeners[2]['callback'][0];
        $this->assertInstanceOf(TestListener::class, $instance0);
        $this->assertInstanceOf(TestListener::class, $instance1);
        $this->assertInstanceOf(TestListener::class, $instance2);
        $this->assertEquals('third', $instance0->getName());
        $this->assertEquals('first', $instance1->getName());
        $this->assertEquals('second', $instance2->getName());
    }

    public function testUnregisterListener(): void
    {
        $callback = [new TestListener(), 'handle'];
        $this->eventManager->registerListener('TEST_EVENT', $callback);
        $this->eventManager->unregisterListener('TEST_EVENT', $callback);

        $listeners = $this->eventManager->getListeners('TEST_EVENT');
        $this->assertEmpty($listeners);
    }

    public function testUnregisterListenerForUnknownEventIsNoOp(): void
    {
        $callback = [new TestListener(), 'handle'];

        // Unregistering for an event that was never registered should not throw
        $this->eventManager->unregisterListener('NEVER_REGISTERED', $callback);

        $this->assertEmpty($this->eventManager->getListeners('NEVER_REGISTERED'));
    }

    public function testFireEventWithNoListenersReturnsSameEventUnchanged(): void
    {
        $event = new TestPayloadEvent('NONEXISTENT_EVENT');
        $result = $this->eventManager->fire('NONEXISTENT_EVENT', $event);

        $this->assertSame($event, $result);
        $this->assertSame([], $event->steps);
    }

    public function testFireEventChainsMutationsAcrossListenersInPriorityOrder(): void
    {
        $listener1 = new StepAppendingListener('step1');
        $listener2 = new StepAppendingListener('step2');

        $this->eventManager->registerListener('TEST_EVENT', [$listener1, 'handle'], 100);
        $this->eventManager->registerListener('TEST_EVENT', [$listener2, 'handle'], 200);

        $event = new TestPayloadEvent('TEST_EVENT');
        $result = $this->eventManager->fire('TEST_EVENT', $event);

        $this->assertSame($event, $result);
        $this->assertSame(['step1', 'step2'], $result->steps);
    }

    public function testFireEventWithNonCallableListenerIsSkipped(): void
    {
        // Register a callback referencing a method that does not exist.
        // is_callable() will be false, so fire() must skip it without error.
        $listener = new TestListener();
        $this->eventManager->registerListener('TEST_EVENT', [$listener, 'nonexistentMethod']);

        $event = new TestPayloadEvent('TEST_EVENT');
        $result = $this->eventManager->fire('TEST_EVENT', $event);

        // Event passes through unchanged since the only listener was skipped
        $this->assertInstanceOf(TestPayloadEvent::class, $result);
        $this->assertSame([], $result->steps);
    }
}

/**
 * Local test-only Event carrying a mutable list, standing in for whatever
 * typed properties a real Event subclass would expose.
 */
class TestPayloadEvent extends Event
{
    /**
     * @var array<int, string>
     */
    public array $steps = [];
}

class TestListener
{
    private string $name;

    public function __construct(string $name = 'test')
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function handle(Event $event): void
    {
    }
}

class StepAppendingListener
{
    public function __construct(private readonly string $step)
    {
    }

    public function handle(TestPayloadEvent $event): void
    {
        $event->steps[] = $this->step;
    }
}
