<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services\Http;

/**
 * Minimal outbound HTTP client for audit checks.
 *
 * Implementations must verify TLS unless told otherwise, apply a 10 second
 * total timeout, never follow redirects, and read only the status line.
 */
interface HttpProbeInterface
{
    /**
     * @return array{status: int, error: string} status is 0 when no HTTP response was received
     */
    public function probe(string $url, bool $verifyTls = true): array;
}
