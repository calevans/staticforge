<?php

namespace EICC\StaticForge\Core;

use EICC\Utils\Container;
use EICC\StaticForge\Environment\EnvironmentLoader;

/**
 * Bootstrap class for initializing the application
 */
class Bootstrap
{
    private Container $container;

    public function __construct()
    {
        $this->container = new Container();
    }

    /**
     * Initialize the application with environment and dependencies
     */
    public function initialize(string $envPath = '.env'): Container
    {
        $this->loadEnvironment($envPath);
        $this->registerServices();

        return $this->container;
    }

    /**
     * Load environment configuration
     */
    private function loadEnvironment(string $envPath): void
    {
        $loader = new EnvironmentLoader($this->container);
        $loader->load($envPath);
    }

    /**
     * Register core services in container
     */
    private function registerServices(): void
    {
        // Core services will be registered here as we build them
        // For now, just ensure the container is accessible
        $this->container->add('container', $this->container);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}