<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Mocks;

use EICC\StaticForge\Services\Http\HttpProbeInterface;

/**
 * Scripted HttpProbeInterface: records every request and answers from a queue of
 * responses (or a status per path suffix). No network access.
 */
class FakeHttpProbe implements HttpProbeInterface
{
    /** @var list<array{url: string, verifyTls: bool}> */
    public array $requests = [];

    /**
     * @param list<array{status: int, error: string}> $responses consumed in order; the last one repeats
     */
    public function __construct(private array $responses)
    {
    }

    public static function status(int $status): self
    {
        return new self([['status' => $status, 'error' => '']]);
    }

    public static function networkFailure(string $error = 'Operation timed out'): self
    {
        return new self([['status' => 0, 'error' => $error]]);
    }

    public function probe(string $url, bool $verifyTls = true): array
    {
        $this->requests[] = ['url' => $url, 'verifyTls' => $verifyTls];

        return count($this->responses) > 1 ? array_shift($this->responses) : $this->responses[0];
    }
}
