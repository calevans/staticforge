<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\DevServer\Services;

final class RouterResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body
    ) {
    }
}
