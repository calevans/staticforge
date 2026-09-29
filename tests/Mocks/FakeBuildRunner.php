<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Mocks;

use EICC\StaticForge\Features\DevServer\Services\BuildRunnerInterface;

/**
 * Scripted build runner: records start() calls and lets the test finish a build
 * with a chosen exit code and output. No process is ever spawned.
 */
final class FakeBuildRunner implements BuildRunnerInterface
{
    /** @var list<bool> the $clean argument of every start() call */
    public array $starts = [];
    public bool $stopped = false;
    private bool $running = false;
    private ?int $exitCode = null;
    private string $output = '';

    public function start(bool $clean): void
    {
        $this->starts[] = $clean;
        $this->running = true;
        $this->exitCode = null;
        $this->output = '';
    }

    public function finish(int $exitCode, string $output = ''): void
    {
        $this->running = false;
        $this->exitCode = $exitCode;
        $this->output = $output;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function exitCode(): ?int
    {
        return $this->exitCode;
    }

    public function output(): string
    {
        return $this->output;
    }

    public function stop(): void
    {
        $this->stopped = true;
        $this->running = false;
    }
}
