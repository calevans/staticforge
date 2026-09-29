<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Mocks;

use EICC\StaticForge\Features\DevServer\Services\ClockInterface;

/**
 * Manually advanced clock so debounce timing is deterministic.
 */
final class FakeClock implements ClockInterface
{
    public function __construct(public int $now = 0)
    {
    }

    public function nowMs(): int
    {
        return $this->now;
    }

    public function advance(int $ms): void
    {
        $this->now += $ms;
    }
}
