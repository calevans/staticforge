<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\Audit\LiveCommand;
use EICC\StaticForge\Tests\Mocks\FakeHttpProbe;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class LiveCommandSoftNotFoundTest extends UnitTestCase
{
    /**
     * @return array<int, array{type: string, scope: string, message: string}>
     */
    private function check(FakeHttpProbe $probe, string $url = 'https://example.com/', bool $insecure = false): array
    {
        $command = new class ($this->container, $probe) extends LiveCommand {
            /**
             * @return array<int, array{type: string, scope: string, message: string}>
             */
            public function runCheck(string $url, bool $insecure): array
            {
                $this->io = new SymfonyStyle(new ArrayInput([]), new BufferedOutput());
                $this->insecure = $insecure;

                return $this->checkSoftNotFound($url);
            }
        };

        return $command->runCheck($url, $insecure);
    }

    public function testPassesWhenBothUnknownUrlsReturn404(): void
    {
        $issues = $this->check(FakeHttpProbe::status(404));

        $this->assertCount(1, $issues);
        $this->assertSame('success', $issues[0]['type']);
        $this->assertSame('Unknown URLs return 404.', $issues[0]['message']);
    }

    public function testRequestsBothExtensionlessAndHtmlPathsUnderTheAuditedHost(): void
    {
        $probe = FakeHttpProbe::status(404);
        $this->check($probe, 'https://example.com/sub/');

        $this->assertCount(2, $probe->requests);
        $first = $probe->requests[0]['url'];
        $second = $probe->requests[1]['url'];
        $this->assertMatchesRegularExpression('#^https://example\.com/sub/staticforge-audit-[0-9a-f]{16}$#', $first);
        $this->assertSame($first . '.html', $second);
        foreach ($probe->requests as $request) {
            $this->assertSame('example.com', parse_url($request['url'], PHP_URL_HOST));
        }
    }

    public function testUsesDifferentRandomPathOnEachRun(): void
    {
        $a = FakeHttpProbe::status(404);
        $b = FakeHttpProbe::status(404);
        $this->check($a);
        $this->check($b);

        $this->assertNotSame($a->requests[0]['url'], $b->requests[0]['url']);
    }

    public function testFailsWhenUnknownUrlReturns200(): void
    {
        $issues = $this->check(FakeHttpProbe::status(200));

        $this->assertSame('error', $issues[0]['type']);
        $this->assertStringContainsString('returned 200 instead of 404', $issues[0]['message']);
        $this->assertStringContainsString('soft 404', $issues[0]['message']);
    }

    public function testFailsWhenOnlyTheHtmlPathReturns200(): void
    {
        $probe = new FakeHttpProbe([['status' => 404, 'error' => ''], ['status' => 200, 'error' => '']]);
        $issues = $this->check($probe);

        $this->assertCount(2, $probe->requests);
        $this->assertSame('error', $issues[0]['type']);
        $this->assertStringContainsString('.html returned 200', $issues[0]['message']);
    }

    public function testFailsWhenServerErrorsOnUnknownUrl(): void
    {
        $issues = $this->check(FakeHttpProbe::status(500));

        $this->assertSame('error', $issues[0]['type']);
        $this->assertStringContainsString('returned 500', $issues[0]['message']);
    }

    public function testFailsAndMentionsRedirectsFor301And302(): void
    {
        foreach ([301, 302] as $status) {
            $probe = FakeHttpProbe::status($status);
            $issues = $this->check($probe);

            $this->assertSame('error', $issues[0]['type']);
            $this->assertStringContainsString('redirect', $issues[0]['message']);
            $this->assertStringContainsString((string) $status, $issues[0]['message']);
            $this->assertCount(1, $probe->requests, 'stops at first failing probe');
        }
    }

    public function testWarnsWhenNetworkFailsOrTimesOut(): void
    {
        $issues = $this->check(FakeHttpProbe::networkFailure('Operation timed out'));

        $this->assertSame('warning', $issues[0]['type']);
        $this->assertStringContainsString('Operation timed out', $issues[0]['message']);
    }

    public function testVerifiesTlsByDefault(): void
    {
        $probe = FakeHttpProbe::status(404);
        $this->check($probe);

        $this->assertTrue($probe->requests[0]['verifyTls']);
        $this->assertTrue($probe->requests[1]['verifyTls']);
    }

    public function testInsecureFlagDisablesTlsVerificationForBothProbes(): void
    {
        $probe = FakeHttpProbe::status(404);
        $this->check($probe, 'https://example.com/', true);

        $this->assertFalse($probe->requests[0]['verifyTls']);
        $this->assertFalse($probe->requests[1]['verifyTls']);
    }
}
