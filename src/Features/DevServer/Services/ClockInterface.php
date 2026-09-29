<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\DevServer\Services;

interface ClockInterface
{
    public function nowMs(): int;
}
