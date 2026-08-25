<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core;

use EICC\StaticForge\Core\Events\Event;

/**
 * Manages event registration, firing, and listener coordination
 * Supports priority-based ordering (0-999, default 100)
 */
class EventManager
{
    /**
     * Registered event listeners
     * @var array<string, array<int, array{callback: array{object, string}, priority: int}>>
     */
    private array $listeners = [];

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
     * Fire an event, passing it through the listener chain for $eventName.
     * Listeners mutate $event in place; the same instance is returned.
     */
    public function fire(string $eventName, Event $event): Event
    {
        if (!isset($this->listeners[$eventName])) {
            return $event;
        }

        foreach ($this->listeners[$eventName] as $listener) {
            $callback = $listener['callback'];

            if (is_callable($callback)) {
                call_user_func($callback, $event);
            }
        }

        return $event;
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
