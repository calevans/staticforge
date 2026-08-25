<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core\Events;

use Symfony\Component\Console\Application;

/**
 * Fired once during CLI bootstrap so Features can register their own
 * Symfony Console commands on the shared Application instance.
 */
class ConsoleInitEvent extends Event
{
    public function __construct(string $name, public readonly Application $application)
    {
        parent::__construct($name);
    }
}
