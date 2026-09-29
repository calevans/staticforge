<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\DevServer\Services;

final class SystemClock implements ClockInterface
{
    public function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
