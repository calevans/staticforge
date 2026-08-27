<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\Utils\Container;

class BaseFeatureTest extends UnitTestCase
{
    private TestFeature $feature;

    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();
        // Use bootstrapped container from parent::setUp()
        $this->eventManager = new EventManager();
        $this->feature = new TestFeature();
    }

    public function testRegisterEventListenersDiscoversEventListenerAttributes(): void
    {
        $this->feature->setContainer($this->container);
        $this->feature->register($this->eventManager);

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
        $this->feature->setContainer($this->container);
        $this->feature->register($this->eventManager);

        // Test that event listeners are properly callable and mutate the event
        $event = new TestFeatureEvent('TEST_EVENT');
        $result = $this->eventManager->fire('TEST_EVENT', $event);

        $this->assertSame($event, $result);
        $this->assertTrue($result->processed);
    }

    public function testRequireFeatures(): void
    {
        // Mock FeatureManager
        $featureManager = $this->createMock(\EICC\StaticForge\Core\FeatureManager::class);

        // Configure mock behavior
        $featureManager->method('isFeatureEnabled')
            ->willReturnMap([
                ['EnabledFeature', true],
                ['DisabledFeature', false]
            ]);

        // Add mock to container
        // Note: FeatureManager is already added in bootstrap, so we need to overwrite it
        // But Container::add throws if exists. We should use stuff() or just replace the instance if possible.
        // Container doesn't have replace. But UnitTestCase::setContainerVariable is for variables, not services.
        // Wait, Container::stuff overwrites? No, stuff is for lazy loading.
        // Let's check Container.php again.

        // Actually, bootstrap adds it with $container->add(FeatureManager::class, $featureManager);
        // If I want to replace it, I might need to use reflection on Container or just create a new container.
        // But UnitTestCase sets up the container in setUp.

        // Let's try to use reflection to replace the service in the container.
        $reflection = new \ReflectionClass($this->container);
        $property = $reflection->getProperty('data');
        $services = $property->getValue($this->container);
        $services[\EICC\StaticForge\Core\FeatureManager::class] = $featureManager;
        $property->setValue($this->container, $services);

        $this->feature->setContainer($this->container);
        $this->feature->register($this->eventManager);

        // Test with enabled feature
        $this->assertTrue($this->feature->checkRequirements(['EnabledFeature']));

        // Test with disabled feature
        $this->assertFalse($this->feature->checkRequirements(['DisabledFeature']));

        // Test with mixed features
        $this->assertFalse($this->feature->checkRequirements(['EnabledFeature', 'DisabledFeature']));

        // Test with no requirements
        $this->assertTrue($this->feature->checkRequirements([]));
    }

    public function testGetConfigHelpDefaultsToNullForAnyKey(): void
    {
        $this->assertNull($this->feature->getConfigHelp('any_key'));
        $this->assertNull($this->feature->getConfigHelp('another_key'));
    }

    public function testRequireFeaturesReturnsFalseWhenFeatureManagerUnavailable(): void
    {
        // Replace the FeatureManager service slot with a callable that throws,
        // simulating a container resolution failure (Container::get() wraps callable
        // failures in a RuntimeException).
        $reflection = new \ReflectionClass($this->container);
        $property = $reflection->getProperty('data');
        $services = $property->getValue($this->container);
        $services[\EICC\StaticForge\Core\FeatureManager::class] = function () {
            throw new \RuntimeException('Service unavailable');
        };
        $property->setValue($this->container, $services);

        $this->feature->setContainer($this->container);
        $this->feature->register($this->eventManager);

        // requireFeatures() should catch the failure and return false rather than propagate
        $this->assertFalse($this->feature->checkRequirements(['AnyFeature']));
    }
}

class TestFeatureEvent extends Event
{
    public bool $processed = false;
    public bool $another = false;
}

class TestFeature extends BaseFeature
{
    #[EventListener('TEST_EVENT', priority: 100)]
    public function handleTestEvent(TestFeatureEvent $event): void
    {
        $event->processed = true;
    }

    #[EventListener('ANOTHER_EVENT', priority: 50)]
    public function handleAnotherEvent(TestFeatureEvent $event): void
    {
        $event->another = true;
    }

    /**
     * @param array<string> $requiredFeatures
     */
    public function checkRequirements(array $requiredFeatures): bool
    {
        return $this->requireFeatures($this->container, $requiredFeatures);
    }
}
