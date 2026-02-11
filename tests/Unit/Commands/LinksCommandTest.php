<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\Audit\LinksCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class LinksCommandTest extends UnitTestCase
{
    public function testHttpClientOptionsVerifyTlsByDefault(): void
    {
        $command = new class($this->container) extends LinksCommand {
            public function getHttpClientOptionsForTest(bool $external): array
            {
                return $this->buildHttpClientOptions($external);
            }
        };

        $options = $command->getHttpClientOptionsForTest(false);

        $this->assertTrue($options['verify_peer']);
        $this->assertTrue($options['verify_host']);
    }

    public function testHttpClientOptionsDisableTlsWhenInsecure(): void
    {
        $command = new class($this->container) extends LinksCommand {
            public function setInsecureForTest(bool $value): void
            {
                $this->insecure = $value;
            }

            public function getHttpClientOptionsForTest(bool $external): array
            {
                return $this->buildHttpClientOptions($external);
            }
        };

        $command->setInsecureForTest(true);
        $options = $command->getHttpClientOptionsForTest(true);

        $this->assertFalse($options['verify_peer']);
        $this->assertFalse($options['verify_host']);
        $this->assertSame(3, $options['max_redirects']);
    }
}
