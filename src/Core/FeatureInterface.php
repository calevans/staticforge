<?php

namespace EICC\StaticForge\Core;

use EICC\Utils\Container;

/**
 * Interface that all features must implement
 */
interface FeatureInterface
{
    /**
     * Register feature with the system
     * Called during feature instantiation to set up event listeners
     */
    public function register(EventManager $eventManager, Container $container): void;

    /**
     * Get list of events this feature listens to
     *
     * @return array<string, array{method: string, priority: int}>
     */
    public function getEventListeners(): array;
}
