<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\DevServer\Services;

final class BuildRequest
{
    /**
     * @param list<string> $changed Added or modified paths
     * @param list<string> $deleted Deleted or renamed-away paths
     */
    public function __construct(
        public readonly bool $clean,
        public readonly array $changed,
        public readonly array $deleted
    ) {
    }

    public function firstPath(): string
    {
        return $this->deleted[0] ?? $this->changed[0] ?? '';
    }
}
