<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core\Events;

/**
 * Fired by MenuBuilderService so other Features can inject their own entries
 * into the built menu structure (e.g. CategoryIndex adding category links).
 */
class CollectMenuItemsEvent extends Event
{
    /**
     * @param array<int, mixed> $menuData
     */
    public function __construct(string $name, public array $menuData)
    {
        parent::__construct($name);
    }
}
