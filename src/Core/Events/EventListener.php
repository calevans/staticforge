<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core\Events;

use Attribute;

/**
 * Marks a Feature method as a listener for $eventName, replacing the old
 * `protected array $eventListeners = [...]` declaration. Colocating the
 * event name and priority with the handler method means BaseFeature can
 * discover listeners via reflection instead of a separately-maintained
 * array, and gives PHPStan's dead-code detector a precise, single-purpose
 * marker instead of a manually maintained method-name regex.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class EventListener
{
    public function __construct(
        public readonly string $eventName,
        public readonly int $priority = 100,
    ) {
    }
}
