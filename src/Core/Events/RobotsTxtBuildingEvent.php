<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core\Events;

/**
 * Fired by RobotsTxtService before writing robots.txt, so a Feature can add
 * or remove disallow rules.
 */
class RobotsTxtBuildingEvent extends Event
{
    /**
     * @param array<string, array{Disallow?: array<int, string>, Allow?: array<int, string>}> $rules
     */
    public function __construct(string $name, public array $rules)
    {
        parent::__construct($name);
    }
}
