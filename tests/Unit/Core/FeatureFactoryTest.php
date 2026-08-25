<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Core\FeatureFactory;
use EICC\Utils\Container;
use EICC\Utils\Log;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FeatureFactoryTest extends TestCase
{
    private Container $container;
    private FeatureFactory $factory;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->factory = new FeatureFactory($this->container);
    }

    public function testReturnsExplicitContainerEntryWithoutReflecting(): void
    {
        $registered = new FeatureFactoryTestNoDeps();
        $this->container->add(FeatureFactoryTestNoDeps::class, $registered);

        $this->assertSame($registered, $this->factory->make(FeatureFactoryTestNoDeps::class));
    }

    public function testConstructsClassWithNoConstructorArguments(): void
    {
        $instance = $this->factory->make(FeatureFactoryTestNoDeps::class);

        $this->assertInstanceOf(FeatureFactoryTestNoDeps::class, $instance);
    }

    public function testResolvesLogViaTheLoggerContainerKey(): void
    {
        $log = new Log('test', sys_get_temp_dir() . '/featurefactory-test.log', 'INFO');
        $this->container->stuff('logger', function () use ($log) {
            return $log;
        });

        $instance = $this->factory->make(FeatureFactoryTestNeedsLogger::class);

        $this->assertInstanceOf(FeatureFactoryTestNeedsLogger::class, $instance);
        $this->assertSame($log, $instance->logger);
    }

    public function testInjectsTheContainerItself(): void
    {
        $instance = $this->factory->make(FeatureFactoryTestNeedsContainer::class);

        $this->assertInstanceOf(FeatureFactoryTestNeedsContainer::class, $instance);
        $this->assertSame($this->container, $instance->container);
    }

    public function testRecursivelyAutowiresUnregisteredDependencies(): void
    {
        $instance = $this->factory->make(FeatureFactoryTestNeedsCollaborator::class);

        $this->assertInstanceOf(FeatureFactoryTestNeedsCollaborator::class, $instance);
        $this->assertInstanceOf(FeatureFactoryTestCollaborator::class, $instance->collaborator);
    }

    public function testPrefersExplicitlyRegisteredDependencyOverAutowiring(): void
    {
        $registered = new FeatureFactoryTestCollaborator();
        $this->container->add(FeatureFactoryTestCollaborator::class, $registered);

        $instance = $this->factory->make(FeatureFactoryTestNeedsCollaborator::class);

        $this->assertInstanceOf(FeatureFactoryTestNeedsCollaborator::class, $instance);
        $this->assertSame($registered, $instance->collaborator);
    }

    public function testUsesConstructorDefaultWhenDependencyIsUnresolvable(): void
    {
        $instance = $this->factory->make(FeatureFactoryTestWithDefault::class);

        $this->assertInstanceOf(FeatureFactoryTestWithDefault::class, $instance);
        $this->assertSame('fallback', $instance->value);
    }

    public function testThrowsOnUnresolvableRequiredScalarParameter(): void
    {
        $this->expectException(RuntimeException::class);
        $this->factory->make(FeatureFactoryTestUnresolvable::class);
    }

    public function testThrowsForNonexistentClass(): void
    {
        $this->expectException(RuntimeException::class);
        $this->factory->make('EICC\\StaticForge\\Tests\\NoSuchClassAtAll');
    }
}

class FeatureFactoryTestNoDeps
{
}

class FeatureFactoryTestNeedsLogger
{
    public function __construct(public Log $logger)
    {
    }
}

class FeatureFactoryTestNeedsContainer
{
    public function __construct(public Container $container)
    {
    }
}

class FeatureFactoryTestCollaborator
{
}

class FeatureFactoryTestNeedsCollaborator
{
    public function __construct(public FeatureFactoryTestCollaborator $collaborator)
    {
    }
}

class FeatureFactoryTestWithDefault
{
    public function __construct(public string $value = 'fallback')
    {
    }
}

class FeatureFactoryTestUnresolvable
{
    public function __construct(public string $required)
    {
    }
}
