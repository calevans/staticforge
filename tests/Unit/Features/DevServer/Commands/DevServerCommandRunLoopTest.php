<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Commands;

use EICC\StaticForge\Tests\Mocks\FakeBuildRunner;
use EICC\StaticForge\Tests\Mocks\ScriptedClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Drives DevServerCommand::runLoop() against a small stand-in child process (never a real
 * php -S, never a build) to cover what happens when the server ends and when files change.
 */
class DevServerCommandRunLoopTest extends DevServerCommandTestCase
{
    /**
     * @param list<string> $argv
     */
    private function attachChild(object $command, array $argv): void
    {
        $process = proc_open(
            $argv,
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        $this->assertIsResource($process);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $this->setProperty($command, 'serverProcess', $process);
        $this->setProperty($command, 'serverPipes', [1 => $pipes[1], 2 => $pipes[2]]);
    }

    public function testServerExitingByItselfPrintsItsDrainedOutputAndReportsFailure(): void
    {
        $command = $this->command();
        [$output, $io] = $this->bufferedIo();
        $this->attachChild($command, [
            PHP_BINARY,
            '-r',
            'fwrite(STDERR, "Failed to listen on 10.0.0.1:80 (reason: Cannot assign requested address)\n");'
            . 'fwrite(STDOUT, "unterminated last words");',
        ]);

        $stopped = $this->callPrivate($command, 'runLoop', $io, false, false);

        $console = $output->fetch();
        $this->assertFalse($stopped);
        $this->assertStringContainsString('Failed to listen on 10.0.0.1:80', $console);
        $this->assertStringContainsString('unterminated last words', $console, 'partial line buffer must be flushed');
        $this->assertStringContainsString('The development server exited unexpectedly', $console);
    }

    public function testServerExitingByItselfInWatchModeAlsoReportsFailure(): void
    {
        $command = $this->command(new FakeBuildRunner());
        [$output, $io] = $this->bufferedIo();
        $this->attachChild($command, [PHP_BINARY, '-r', 'fwrite(STDERR, "Failed to listen\n");']);

        $stopped = $this->callPrivate($command, 'runLoop', $io, true, false);

        $this->assertFalse($stopped);
        $this->assertStringContainsString('Failed to listen', $output->fetch());
    }

    public function testCommandReturnsFailureWhenTheServerCannotBind(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($socket);
        $name = (string) stream_socket_get_name($socket, false);
        $port = (int) substr($name, (int) strrpos($name, ':') + 1);
        fclose($socket);

        $application = new Application();
        $application->addCommand($this->command());
        $tester = new CommandTester($application->find('site:devserver'));
        // 10.255.255.1 is not assigned to this host, so php -S cannot bind it.
        $tester->execute(['--host' => '10.255.255.1', '--port' => (string) $port]);

        $this->assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('exited unexpectedly', $tester->getDisplay());
        $this->assertSame([], glob(sys_get_temp_dir() . '/staticforge-dev-*/router.php') ?: []);
    }

    public function testNoBuildIsRequestedAtStartupWhenNothingChanges(): void
    {
        $runner = new FakeBuildRunner();
        $clock = new ScriptedClock(0, 700);
        $command = $this->command($runner, $clock);
        $dir = $this->attachStateDir($command);
        file_put_contents($this->base . '/content/a.md', 'A');
        [, $io] = $this->bufferedIo();
        $this->attachChild($command, [PHP_BINARY, '-r', 'usleep(1200000);']);

        $this->callPrivate($command, 'runLoop', $io, true, false);

        $this->assertGreaterThan(3, $clock->reads(), 'the loop must have ticked several times');
        $this->assertSame([], $runner->starts);
        $this->assertFileDoesNotExist($dir->file('state.json'));
    }

    public function testFileChangeAfterTheBaselineStartsExactlyOneBuild(): void
    {
        $runner = new FakeBuildRunner();
        file_put_contents($this->base . '/content/a.md', 'A');
        $touched = false;
        $clock = new ScriptedClock(0, 700, function (int $read) use (&$touched): void {
            if ($read === 12 && !$touched) {
                $touched = true;
                file_put_contents($this->base . '/content/a.md', 'A changed, size differs');
            }
        });
        $command = $this->command($runner, $clock);
        $dir = $this->attachStateDir($command);
        [, $io] = $this->bufferedIo();
        $this->attachChild($command, [PHP_BINARY, '-r', 'usleep(2500000);']);

        $this->callPrivate($command, 'runLoop', $io, true, false);

        $this->assertTrue($touched, 'the scripted change never happened; the loop did not tick enough');
        $this->assertSame([false], $runner->starts);
        $this->assertSame('building', $this->readState($dir)['status']);
    }

    public function testFailedBuildIsReportedAndNeverRetriedAutomatically(): void
    {
        $runner = new FakeBuildRunner();
        file_put_contents($this->base . '/content/a.md', 'A');
        $step = 0;
        $clock = new ScriptedClock(0, 700, function (int $read) use (&$step, $runner): void {
            if ($step === 0 && $read === 12) {
                $step = 1;
                file_put_contents($this->base . '/content/a.md', 'first edit, longer');
            } elseif ($step === 1 && $runner->starts !== []) {
                $step = 2;
                $runner->finish(1, 'Fatal error: broken');
            }
        });
        $command = $this->command($runner, $clock);
        $dir = $this->attachStateDir($command);
        [$output, $io] = $this->bufferedIo();
        $this->attachChild($command, [PHP_BINARY, '-r', 'usleep(3500000);']);

        $this->callPrivate($command, 'runLoop', $io, true, false);

        $this->assertSame(2, $step, 'the build never finished inside the loop');
        $this->assertSame([false], $runner->starts, 'a failed build must not be retried automatically');
        $this->assertSame('failed', $this->readState($dir)['status']);
        $this->assertStringContainsString('FAILED', $output->fetch());
    }
}
