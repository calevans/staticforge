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
        $command = new class ($this->container) extends LinksCommand {
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
        $command = new class ($this->container) extends LinksCommand {
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

    public function testHttpClientOptionsCapRedirectsForInternalLinksToo(): void
    {
        $command = new class ($this->container) extends LinksCommand {
            /**
             * @return array<string, mixed>
             */
            public function getHttpClientOptionsForTest(bool $external): array
            {
                return $this->buildHttpClientOptions($external);
            }
        };

        $options = $command->getHttpClientOptionsForTest(false);

        $this->assertSame(5, $options['max_redirects']);
    }

    public function testValidateExternalLinksRejectsUnsafeUrlWithoutRequestingIt(): void
    {
        $command = new class ($this->container) extends LinksCommand {
            /**
             * @param array<int, string> $urls
             * @param array<string, array<int, string>> $urlMap
             * @return array<int, array{source: string, link: string, reason: string}>
             */
            public function validateExternalLinksForTest(array $urls, array $urlMap): array
            {
                $reflection = new \ReflectionMethod($this, 'validateExternalLinks');
                $reflection->setAccessible(true);
                return $reflection->invoke($this, $urls, 1, $urlMap);
            }
        };

        $io = new \Symfony\Component\Console\Style\SymfonyStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput()
        );
        $ioProp = new \ReflectionProperty($command, 'io');
        $ioProp->setAccessible(true);
        $ioProp->setValue($command, $io);

        $url = 'http://127.0.0.1/admin';
        $errors = $command->validateExternalLinksForTest([$url], [$url => ['page.html']]);

        $this->assertCount(1, $errors);
        $this->assertSame('page.html', $errors[0]['source']);
        $this->assertSame($url, $errors[0]['link']);
        $this->assertStringContainsString('private, loopback, or reserved', $errors[0]['reason']);
    }

    public function testIsExternalDetectsHttpAndHttpsLinks(): void
    {
        $command = new class ($this->container) extends LinksCommand {
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
