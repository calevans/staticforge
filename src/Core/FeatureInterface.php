<?php

declare(strict_types=1);

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
     * Get the simple name for this feature
     */
    public function getName(): string;
}
