<?php

namespace EICC\StaticForge\Core;

use EICC\Utils\Container;

/**
 * Manages event registration, firing, and listener coordination
 * Supports priority-based ordering (0-999, default 100)
 */
class EventManager
{
    private Container $container;
    private array $listeners = [];
    private array $registeredEvents = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register an event that can be fired
     */
    public function registerEvent(string $eventName): void
    {
        if (!in_array($eventName, $this->registeredEvents)) {
            $this->registeredEvents[] = $eventName;
        }
    }

    /**
     * Register a listener for an event with priority
     */
    public function registerListener(string $eventName, array $callback, int $priority = 100): void
    {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = [];
        }

        $this->listeners[$eventName][] = [
            'callback' => $callback,
            'priority' => $priority
        ];

        // Sort by priority (lower numbers = higher priority)
        usort($this->listeners[$eventName], function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }

    /**
     * Unregister a listener for an event
     */
    public function unregisterListener(string $eventName, array $callback): void
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        $this->listeners[$eventName] = array_filter(
            $this->listeners[$eventName],
            function ($listener) use ($callback) {
                return $listener['callback'] !== $callback;
            }
        );
    }

    /**
     * Fire an event and pass parameters through listener chain
     */
    public function fire(string $eventName, array $parameters = []): array
    {
        if (!isset($this->listeners[$eventName])) {
            return $parameters;
        }

        $currentParameters = $parameters;

        foreach ($this->listeners[$eventName] as $listener) {
            $callback = $listener['callback'];

            if (is_callable($callback)) {
                $result = call_user_func($callback, $this->container, $currentParameters);
                if (is_array($result)) {
                    $currentParameters = $result;
                }
            }
        }

        // If features data was returned, store it in the container
        if (isset($currentParameters['features']) && is_array($currentParameters['features'])) {
            $this->updateFeaturesInContainer($currentParameters['features']);
        }

        return $currentParameters;
    }

    /**
     * Update features data in container
     */
    private function updateFeaturesInContainer(array $newFeaturesData): void
    {
        // Get existing features or initialize empty array
        $features = $this->container->getVariable('features') ?? [];

        // Merge new feature data
        foreach ($newFeaturesData as $featureName => $featureData) {
            $features[$featureName] = $featureData;
        }

        // Update or set the features variable
        if ($this->container->hasVariable('features')) {
            $this->container->updateVariable('features', $features);
        } else {
            $this->container->setVariable('features', $features);
        }
    }

    /**
     * List all registered events
     */
    public function list(): array
    {
        return $this->registeredEvents;
    }

    /**
     * Get listeners for an event (for testing/debugging)
     */
    public function getListeners(string $eventName): array
    {
        return $this->listeners[$eventName] ?? [];
    }
}
