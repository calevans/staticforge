<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Mocks;

use EICC\StaticForge\Features\DevServer\Services\ClockInterface;

/**
 * Clock that advances by a fixed step on every read and can run a callback on a chosen
 * read, so a test can touch files or finish a fake build from inside the command's loop.
 */
final class ScriptedClock implements ClockInterface
{
    private int $reads = 0;

    /**
     * @param (\Closure(int): void)|null $onRead Called with the 1-based read number after the clock advanced
     */
    public function __construct(
        private int $now,
        private readonly int $stepMs,
        private readonly ?\Closure $onRead = null
    ) {
    }

    public function nowMs(): int
    {
        $this->reads++;
        $this->now += $this->stepMs;
        if ($this->onRead !== null) {
            ($this->onRead)($this->reads);
        }

        return $this->now;
    }

    public function reads(): int
    {
        return $this->reads;
    }
}
