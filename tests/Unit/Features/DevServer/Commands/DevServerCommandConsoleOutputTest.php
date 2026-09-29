<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Commands;

use EICC\StaticForge\Features\DevServer\Services\BuildRequest;
use EICC\StaticForge\Tests\Mocks\FakeBuildRunner;
use EICC\StaticForge\Tests\Mocks\FakeClock;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What the developer sees in the terminal: failed-build error text and server log lines.
 */
class DevServerCommandConsoleOutputTest extends DevServerCommandTestCase
{
    private function failedBuildConsole(string $buildOutput): string
    {
        $runner = new FakeBuildRunner();
        $command = $this->command($runner, new FakeClock(5000));
        $this->attachStateDir($command);
        [$output, $io] = $this->bufferedIo();
        $runner->finish(1, $buildOutput);

        $this->callPrivate(
            $command,
            'finishBuild',
            $io,
            $runner,
            new BuildRequest(false, [$this->base . '/content/a.md'], []),
            4000,
            $this->base . '/app'
        );

        return $output->fetch();
    }

    public function testFailedBuildPrintsTheErrorToTheConsoleNotOnlyToTheStateFile(): void
    {
        $console = $this->failedBuildConsole("Starting\nTemplate error: unexpected token\nmore");

        $this->assertStringContainsString('FAILED', $console);
        $this->assertStringContainsString('Template error: unexpected token', $console);
        $this->assertStringNotContainsString('Starting', $console);
    }

    public function testFailedBuildErrorOnTheConsoleHasTheProjectPathMasked(): void
    {
        $console = $this->failedBuildConsole(
            "Fatal error: cannot read {$this->base}/app/templates/base.twig and {$this->base}/apple/x"
        );

        $this->assertStringContainsString('cannot read ./templates/base.twig', $console);
        $this->assertStringNotContainsString($this->base . '/app/', $console);
        $this->assertStringContainsString('/apple/x', $console, 'sibling path must not be masked');
        $this->assertStringNotContainsString('./le/x', $console);
    }

    public function testFailedBuildErrorOnTheConsoleIsCappedAtFiveLines(): void
    {
        $lines = [];
        for ($i = 1; $i <= 9; $i++) {
            $lines[] = "Error number {$i}";
        }

        $console = $this->failedBuildConsole(implode("\n", $lines));

        $this->assertStringContainsString('Error number 5', $console);
        $this->assertStringNotContainsString('Error number 6', $console);
    }

    public function testFailedBuildErrorOnTheConsoleIsCappedAtFiveHundredCharacters(): void
    {
        $console = $this->failedBuildConsole('Fatal error: ' . str_repeat('z', 2000));

        // "Fatal error: " is 13 characters; the rest of the 500-character cap is the filler.
        $this->assertSame(487, substr_count($console, "z"));
    }

    public function testFailedBuildErrorWithStyleTagsIsPrintedLiterally(): void
    {
        $console = $this->failedBuildConsole('Fatal error: <fg=red>boom</> <error>x</error>');

        $this->assertStringContainsString('<fg=red>boom</>', $console);
        $this->assertStringContainsString('<error>x</error>', $console);
    }

    public function testFailedBuildErrorWithInvalidUtf8DoesNotBreakTheConsoleLine(): void
    {
        $console = $this->failedBuildConsole("Fatal error: bad \xff\xfe bytes");

        $this->assertTrue(mb_check_encoding($console, 'UTF-8'));
        $this->assertStringContainsString('bad', $console);
        $this->assertStringContainsString('bytes', $console);
    }

    public function testSuccessfulBuildPrintsNoErrorLine(): void
    {
        $runner = new FakeBuildRunner();
        $command = $this->command($runner, new FakeClock(5000));
        $this->attachStateDir($command);
        [$output, $io] = $this->bufferedIo();
        $runner->finish(0, 'Error: this is only informational output');

        $this->callPrivate(
            $command,
            'finishBuild',
            $io,
            $runner,
            new BuildRequest(false, ['/x'], []),
            4000,
            $this->base . '/app'
        );

        $this->assertStringNotContainsString('informational', $output->fetch());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function logLineProvider(): array
    {
        return [
            'style tag' => ['[Mon] 127.0.0.1 [200]: GET /<fg=red>x</>', '<fg=red>x</>'],
            'closing tag only' => ['[Mon] note </>', 'note </>'],
            'error tag' => ['<error>boom</error> happened', '<error>boom</error> happened'],
        ];
    }

    #[DataProvider('logLineProvider')]
    public function testServerLogLineMarkupIsPrintedLiterally(string $line, string $expectedFragment): void
    {
        [$output, $io] = $this->bufferedIo();

        $this->callPrivate($this->command(), 'printLine', $io, $line, false);

        $this->assertStringContainsString($expectedFragment, $output->fetch());
    }

    public function testServerLogLineTerminalControlCharactersAreStripped(): void
    {
        [$output, $io] = $this->bufferedIo();

        $this->callPrivate(
            $this->command(),
            'printLine',
            $io,
            "PHP Warning: \e[2J\e[31mred\e[0m bell\x07 nul\x00 del\x7f end",
            false
        );

        $printed = $output->fetch();
        $this->assertDoesNotMatchRegularExpression('/[\x00-\x08\x0b-\x1f\x7f]/', $printed);
        $this->assertStringContainsString('PHP Warning:', $printed);
        $this->assertStringContainsString('end', $printed);
    }

    public function testServerLogLineWithInvalidUtf8IsPrintedAsValidText(): void
    {
        [$output, $io] = $this->bufferedIo();

        $this->callPrivate($this->command(), 'printLine', $io, "GET /caf\xe9 [404]", false);

        $printed = $output->fetch();
        $this->assertTrue(mb_check_encoding($printed, 'UTF-8'));
        $this->assertStringContainsString('[404]', $printed);
    }

    public function testOverlongServerLogLineIsTruncated(): void
    {
        [$output, $io] = $this->bufferedIo();

        $this->callPrivate($this->command(), 'printLine', $io, str_repeat('a', 20000), false);

        $this->assertLessThanOrEqual(8192 + 8, strlen(trim($output->fetch())));
    }

    public function testStateEndpointPollNoiseIsNotPrinted(): void
    {
        [$output, $io] = $this->bufferedIo();

        $this->callPrivate($this->command(), 'printLine', $io, '[Mon] 127.0.0.1 [200]: GET /__staticforge/state', true);
        $this->callPrivate($this->command(), 'printLine', $io, '[Mon] 127.0.0.1:5000 Accepted', true);

        $this->assertSame('', trim($output->fetch()));
    }
}
