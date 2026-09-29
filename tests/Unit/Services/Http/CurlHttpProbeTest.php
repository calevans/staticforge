<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services\Http;

use EICC\StaticForge\Services\Http\CurlHttpProbe;
use EICC\StaticForge\Services\Http\HttpProbeInterface;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Network-free checks only: the probe is restricted to http/https, so other schemes are
 * rejected by libcurl before any socket is opened. Real status/redirect/TLS/timeout
 * behaviour is not covered here.
 */
class CurlHttpProbeTest extends UnitTestCase
{
    public function testImplementsProbeInterface(): void
    {
        $this->assertInstanceOf(HttpProbeInterface::class, new CurlHttpProbe());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function disallowedUrlProvider(): array
    {
        return [
            'file' => ['file:///etc/passwd'],
            'ftp' => ['ftp://127.0.0.1/x'],
            'gopher' => ['gopher://127.0.0.1/x'],
        ];
    }

    #[DataProvider('disallowedUrlProvider')]
    public function testNonHttpSchemesReturnStatusZeroWithErrorMessage(string $url): void
    {
        $result = (new CurlHttpProbe())->probe($url);

        $this->assertSame(0, $result['status']);
        $this->assertNotSame('', $result['error']);
    }

    public function testMalformedUrlReturnsStatusZeroWithErrorMessage(): void
    {
        $result = (new CurlHttpProbe())->probe('http://', false);

        $this->assertSame(0, $result['status']);
        $this->assertNotSame('', $result['error']);
    }
}
