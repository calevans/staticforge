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

        // Register event listeners defined by the feature
        $this->registerEventListeners();
    }

    /**
     * Check if required features are enabled
     *
     * @param array<string> $requiredFeatures List of feature names that must be enabled
     * @return bool True if all required features are enabled, false otherwise
     */
    protected function requireFeatures(array $requiredFeatures): bool
    {
        // If no requirements, we're good
        if (empty($requiredFeatures)) {
            return true;
        }

        try {
            $featureManager = $this->container->get(FeatureManager::class);
        } catch (\Exception $e) {
            // If we can't get the feature manager, we can't check requirements
            // This shouldn't happen in normal operation
            return false;
        }

        foreach ($requiredFeatures as $feature) {
            if (!$featureManager->isFeatureEnabled($feature)) {
                // Log a warning so the developer knows why this feature is skipping its logic
                $logger = $this->container->get('logger');
                $logger->log('WARNING',
                    "Feature '{$this->getName()}' requires feature '{$feature}' which is disabled. Skipping functionality."
                );
                return false;
            }
        }

        return true;
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
}
