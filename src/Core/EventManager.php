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

    /**
     * Registered event listeners
     * @var array<string, array<int, array{callback: array{object, string}, priority: int}>>
     */
    private array $listeners = [];

    /**
     * List of registered event names
     * @var array<string>
     */
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
     *
     * @param array{object, string} $callback
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
     *
     * @param array{object, string} $callback
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
     *
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
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
     *
     * @param array<string, mixed> $newFeaturesData
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
     *
     * @return array<string>
     */
    public function list(): array
    {
        return $this->registeredEvents;
    }

    /**
     * Get listeners for an event (for testing/debugging)
     *
     * @return array<int, array{callback: array{object, string}, priority: int}>
     */
    public function getListeners(string $eventName): array
    {
        return $this->listeners[$eventName] ?? [];
    }
}
