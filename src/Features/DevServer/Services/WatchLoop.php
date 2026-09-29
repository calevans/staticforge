<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\DevServer\Services;

/**
 * Pure state machine: feed it a file signature per tick, it says when to build.
 * Changes are debounced, merged into a single pending follow-up while a build
 * runs, and a deleted/renamed source forces the next build to be a clean one.
 */
final class WatchLoop
{
    /** @var array<string, string>|null */
    private ?array $last = null;
    /** @var array<string, true> */
    private array $changed = [];
    /** @var array<string, true> */
    private array $deleted = [];
    /** @var array<string, true> deleted paths of builds that have not succeeded yet */
    private array $carriedDeleted = [];
    private int $lastChangeAt = 0;

    public function __construct(private readonly ClockInterface $clock, private readonly int $debounceMs = 300)
    {
    }

    /**
     * @param array<string, string> $signature
     */
    public function tick(array $signature, bool $buildRunning): ?BuildRequest
    {
        $now = $this->clock->nowMs();

        if ($this->last === null) {
            $this->last = $signature;
            return null;
        }

        $dirty = false;
        foreach ($signature as $path => $sig) {
            if (!isset($this->last[$path]) || $this->last[$path] !== $sig) {
                $this->changed[$path] = true;
                $dirty = true;
            }
        }
        foreach ($this->last as $path => $sig) {
            if (!isset($signature[$path])) {
                $this->deleted[$path] = true;
                unset($this->changed[$path]);
                $dirty = true;
            }
        }
        $this->last = $signature;

        if ($dirty) {
            $this->lastChangeAt = $now;
        }

        if ($this->changed === [] && $this->deleted === []) {
            return null;
        }
        if ($buildRunning || $now - $this->lastChangeAt < $this->debounceMs) {
            return null;
        }

        $deleted = $this->carriedDeleted + $this->deleted;
        $request = new BuildRequest(
            $deleted !== [],
            array_map('strval', array_keys($this->changed)),
            array_map('strval', array_keys($deleted))
        );
        // Stays pending until buildFinished(true): a failed --clean build leaves a partial output.
        $this->carriedDeleted = $deleted;
        $this->changed = [];
        $this->deleted = [];

        return $request;
    }

    /**
     * A failed build keeps the clean requirement (and deleted paths) pending for the
     * next request; it is never retried automatically.
     */
    public function buildFinished(bool $success): void
    {
        if ($success) {
            $this->carriedDeleted = [];
        }
    }
}
