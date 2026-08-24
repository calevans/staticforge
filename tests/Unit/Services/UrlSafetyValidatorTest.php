<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services;

use EICC\StaticForge\Services\UrlSafetyValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UrlSafetyValidatorTest extends TestCase
{
    public function testAllowsPublicHttpsUrl(): void
    {
        UrlSafetyValidator::assertSafe('https://93.184.216.34/path');
        $this->addToAssertionCount(1);
    }

    public function testAllowsPublicHttpUrl(): void
    {
        UrlSafetyValidator::assertSafe('http://8.8.8.8/');
        $this->addToAssertionCount(1);
    }

    public function testRejectsFileScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/scheme must be http or https/');
        UrlSafetyValidator::assertSafe('file:///etc/passwd');
    }

    public function testRejectsGopherScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UrlSafetyValidator::assertSafe('gopher://example.com/');
    }

    public function testRejectsMissingHost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UrlSafetyValidator::assertSafe('https:///path-only');
    }

    public function testRejectsLoopbackIp(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/private, loopback, or reserved/');
        UrlSafetyValidator::assertSafe('http://127.0.0.1/');
    }

    public function testRejectsIpv6Loopback(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UrlSafetyValidator::assertSafe('http://[::1]/');
    }

    public function testRejectsPrivateRfc1918Address(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UrlSafetyValidator::assertSafe('http://10.0.0.5/');
    }

    public function testRejectsLinkLocalAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UrlSafetyValidator::assertSafe('http://169.254.169.254/latest/meta-data/');
    }

    public function testRejectsUnresolvableHost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Could not resolve host/');
        UrlSafetyValidator::assertSafe('https://this-host-does-not-exist.invalid/');
    }
}
