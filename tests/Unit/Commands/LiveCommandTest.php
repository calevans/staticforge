<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\Audit\LiveCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class LiveCommandTest extends UnitTestCase
{
    public function testCurlDefaultsVerifyTlsByDefault(): void
    {
        $command = new class($this->container) extends LiveCommand {
            /**
             * @return array<int, mixed>
             */
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

            /**
             * @return array<int, mixed>
             */
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

    public function testFailsWhenNoUrlProvidedOrConfigured(): void
    {
        $this->setContainerVariable('UPLOAD_URL', '');

        $application = new Application();
        $application->add(new LiveCommand($this->container));
        $command = $application->find('audit:live');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('No URL provided', $tester->getDisplay());
    }

    public function testUsesUploadUrlFromContainerWhenNoOptionProvided(): void
    {
        // An invalid host ensures the connectivity check fails fast without real network access,
        // while still proving the configured UPLOAD_URL was picked up and used as the audit target.
        // stream_socket_client() emits a PHP warning for the DNS failure, which the command
        // handles gracefully (it's reported as an audit issue), so we suppress it here.
        $this->setContainerVariable('UPLOAD_URL', 'https://staticforge-test-invalid-host.invalid');

        $application = new Application();
        $application->add(new LiveCommand($this->container));
        $command = $application->find('audit:live');
        $tester = new CommandTester($command);

        @$tester->execute([]);

        $this->assertStringContainsString('staticforge-test-invalid-host.invalid', $tester->getDisplay());
    }
}
