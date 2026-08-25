<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core\Events;

/**
 * Base type for everything fired through EventManager::fire(). Carries no
 * payload of its own — used directly for lifecycle events that have nothing
 * to pass (CREATE, PRE_GLOB, POST_GLOB, PRE_LOOP, POST_LOOP, DESTROY).
 * Events that carry data extend this with typed properties.
 */
class Event
{
    public function __construct(public readonly string $name)
    {
    }
}
