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
    protected array $eventListeners = [];

    /**
     * Store container and event manager references
     */
    public function register(EventManager $eventManager, Container $container): void
    {
        $this->container = $container;
        $this->eventManager = $eventManager;

        // Register event listeners defined by the feature
        $this->registerEventListeners();
    }

    /**
     * Get list of events this feature listens to
     */
    public function getEventListeners(): array
    {
        return array_keys($this->eventListeners);
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
     */
    protected function getFeatures(): array
    {
        return $this->container->getVariable('features') ?? [];
    }

    /**
     * Update a specific feature's data in the features array
     * Note: Due to Container constraints, features should be managed by FeatureManager
     */
    protected function updateFeatureData(string $featureName, array $data): void
    {
        // For now, this is a placeholder - actual implementation will depend on
        // FeatureManager providing a mutable features reference
        $features = $this->getFeatures();
        $features[$featureName] = $data;
        // Note: Cannot update container directly due to setVariable constraints
    }

    /**
     * Get data for a specific feature
     */
    protected function getFeatureData(string $featureName): array
    {
        $features = $this->getFeatures();
        return $features[$featureName] ?? [];
    }
}