<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\DevServer\Services;

interface BuildRunnerInterface
{
    public function start(bool $clean): void;

    public function isRunning(): bool;

    public function exitCode(): ?int;

    public function output(): string;

    public function stop(): void;
}
