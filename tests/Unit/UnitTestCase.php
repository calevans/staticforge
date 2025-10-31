<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit;

use EICC\Utils\Container;
use PHPUnit\Framework\TestCase;

/**
 * Base class for all unit tests
 * Provides bootstrapped container with logger from tests/.env.testing
 */
abstract class UnitTestCase extends TestCase
{
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        // Bootstrap with testing environment
        // Use include instead of require so each test gets a fresh container
        // Note: $envPath must be set before include for bootstrap to use it
        $envPath = __DIR__ . '/../.env.testing';
        $this->container = include __DIR__ . '/../../src/bootstrap.php';
    }

    protected function tearDown(): void
    {
        // Clean up any environment pollution
        parent::tearDown();
    }

    /**
     * Set or update a container variable
     * Uses updateVariable if the key exists, setVariable otherwise
     *
     * @param string $key The variable key
     * @param mixed $value The variable value
     */
    protected function setContainerVariable(string $key, mixed $value): void
    {
        if ($this->container->hasVariable($key)) {
            $this->container->updateVariable($key, $value);
        } else {
            $this->container->setVariable($key, $value);
        }
    }

    /**
     * Add a service to the container only if it doesn't already exist
     *
     * @param string $key The service key
     * @param mixed $value The service instance
     */
    protected function addToContainer(string $key, mixed $value): void
    {
        if (!$this->container->has($key)) {
            $this->container->add($key, $value);
        }
    }
}
