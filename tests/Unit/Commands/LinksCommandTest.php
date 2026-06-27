<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\Audit\LinksCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class LinksCommandTest extends UnitTestCase
{
    public function testHttpClientOptionsVerifyTlsByDefault(): void
    {
        $command = new class($this->container) extends LinksCommand {
            /**
             * @return array<string, mixed>
             */
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

            /**
             * @return array<string, mixed>
             */
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

    public function testFailsWhenOutputDirectoryMissing(): void
    {
        $this->setContainerVariable('OUTPUT_DIR', '/nonexistent/staticforge-output-dir-' . uniqid());

        $application = new Application();
        $application->add(new LinksCommand($this->container));
        $command = $application->find('audit:links');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--url' => 'http://localhost']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Output directory not found', $tester->getDisplay());
    }

    public function testIsExternalDetectsHttpAndHttpsLinks(): void
    {
        $command = new class($this->container) extends LinksCommand {
            public function isExternalForTest(string $href): bool
            {
                $reflection = new \ReflectionMethod($this, 'isExternal');
                $reflection->setAccessible(true);
                return $reflection->invoke($this, $href);
            }
        };

        $this->assertTrue($command->isExternalForTest('https://example.com'));
        $this->assertTrue($command->isExternalForTest('http://example.com'));
        $this->assertFalse($command->isExternalForTest('/internal/page.html'));
        $this->assertFalse($command->isExternalForTest('relative/page.html'));
    }
}
