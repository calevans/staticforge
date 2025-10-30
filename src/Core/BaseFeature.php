<?php

namespace EICC\StaticForge\Core;

use EICC\Utils\Container;

/**
 * Base class providing common functionality for all features
 */
abstract class BaseFeature implements FeatureInterface
{
    protected Container $container;
    protected EventManager $eventManager;

    /**
     * Event listener registrations
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [];

    /**
     * Simple name for this feature (used as key in FEATURES array)
     * Defaults to the class namespace, but features should override this
     */
    protected string $name;

    /**
     * Get the simple name for this feature
     */
    public function getName(): string
    {
        // If name is empty, use class name
        if (empty($this->name)) {
            $this->name = static::class;
        }
        return $this->name;
    }

    /**
     * Store container and event manager references
     */
    public function register(EventManager $eventManager, Container $container): void
    {
        $this->container = $container;
        $this->eventManager = $eventManager;

        // Register extensions if this feature processes files
        $this->registerExtensions();

        // Register event listeners defined by the feature
        $this->registerEventListeners();
    }

    /**
     * Get list of events this feature listens to
     *
     * @return array<string, array{method: string, priority: int}>
     */
    public function getEventListeners(): array
    {
        return $this->eventListeners;
    }

    /**
     * Register event listeners for this feature
     * Override in concrete features to define event handling
     */
    protected function registerEventListeners(): void
    {
        foreach ($this->eventListeners as $eventName => $config) {
            $callback = [$this, $config['method']];
            $priority = $config['priority'] ?? 100;

            $this->eventManager->registerListener($eventName, $callback, $priority);
        }
    }

    /**
     * Get the features array from container
     *
     * @return array<string, mixed>
     */
    protected function getFeatures(): array
    {
        return $this->container->getVariable('features') ?? [];
    }

    /**
     * Register extensions this feature can process
     * Override in concrete features to register file extensions
     */
    protected function registerExtensions(): void
    {
        // Default implementation does nothing
        // Override in renderer features to register extensions
    }

    /**
     * Register a file extension with the extension registry
     */
    protected function registerExtension(string $extension): void
    {
        $extensionRegistry = $this->container->getVariable('extension_registry');
        if ($extensionRegistry instanceof ExtensionRegistry) {
            $extensionRegistry->registerExtension($extension);
        }
    }

    /**
     * Get data for a specific feature
     *
     * @return array<string, mixed>
     */
    protected function getFeatureData(string $featureName): array
    {
        $features = $this->getFeatures();
        return $features[$featureName] ?? [];
    }
}
