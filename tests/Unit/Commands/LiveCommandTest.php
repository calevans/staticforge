<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\Audit\LiveCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class LiveCommandTest extends UnitTestCase
{
    public function testCurlDefaultsVerifyTlsByDefault(): void
    {
        $command = new class($this->container) extends LiveCommand {
            public function getCurlDefaultsForTest(string $url): array
            {
                return $this->buildCurlDefaults($url);
            }
        };

        $defaults = $command->getCurlDefaultsForTest('https://example.com');

        $this->assertTrue($defaults[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $defaults[CURLOPT_SSL_VERIFYHOST]);
    }

    public function testCurlDefaultsDisableTlsWhenInsecure(): void
    {
        $command = new class($this->container) extends LiveCommand {
            public function setInsecureForTest(bool $value): void
            {
                $this->insecure = $value;
            }

            public function getCurlDefaultsForTest(string $url): array
            {
                return $this->buildCurlDefaults($url);
            }
        };

        $command->setInsecureForTest(true);
        $defaults = $command->getCurlDefaultsForTest('https://example.com');

        $this->assertFalse($defaults[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(0, $defaults[CURLOPT_SSL_VERIFYHOST]);
    }
}
