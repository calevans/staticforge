<?php

namespace EICC\StaticForge\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;

class FeatureInterfaceTest extends TestCase
{
    public function testFeatureInterfaceContract(): void
    {
        $feature = new ConcreteTestFeature();

        $this->assertInstanceOf(FeatureInterface::class, $feature);

        // Test that required methods exist
        $this->assertTrue(method_exists($feature, 'register'));
        $this->assertTrue(method_exists($feature, 'getEventListeners'));
    }

    public function testFeatureImplementsInterface(): void
    {
        $container = new Container();
        $eventManager = new EventManager($container);
        $feature = new ConcreteTestFeature();

        // Should not throw any exceptions
        $feature->register($eventManager, $container);

        $listeners = $feature->getEventListeners();
        $this->assertIsArray($listeners);
    }
}

class ConcreteTestFeature implements FeatureInterface
{
    public function register(EventManager $eventManager, Container $container): void
    {
        // Minimal implementation for testing
    }

    public function getEventListeners(): array
    {
        return ['TEST_EVENT'];
    }
}