<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\DevServer\Services;

final class ProcessBuildRunner implements BuildRunnerInterface
{
    private const MAX_OUTPUT = 65536;

    /** @var resource|null */
    private $process = null;
    /** @var array<int, resource> */
    private array $pipes = [];
    private string $output = '';
    private ?int $exitCode = null;

    public function __construct(private readonly string $appRoot, private readonly bool $includeDrafts = false)
    {
    }

    public function start(bool $clean): void
    {
        if ($this->process !== null) {
            throw new \LogicException('A build is already running');
        }

        $argv = [PHP_BINARY, $this->appRoot . '/bin/staticforge.php', 'site:render', '--incremental'];
        if ($clean) {
            $argv[] = '--clean';
        }
        if ($this->includeDrafts) {
            $argv[] = '--include-drafts';
        }
        $argv[] = '--no-ansi';

        $process = proc_open(
            $argv,
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->appRoot
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start the build process');
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $this->process = $process;
        $this->pipes = [1 => $pipes[1], 2 => $pipes[2]];
        $this->output = '';
        $this->exitCode = null;
    }

    public function isRunning(): bool
    {
        $process = $this->process;
        if ($process === null) {
            return false;
        }

        $this->drain();
        $status = proc_get_status($process);
        if ($status['running']) {
            return true;
        }

        $code = $status['exitcode'];
        $this->drain();
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($process);
        $this->process = null;
        $this->pipes = [];
        $this->exitCode = $code;

        return false;
    }

    public function exitCode(): ?int
    {
        return $this->exitCode;
    }

    public function output(): string
    {
        return $this->output;
    }

    public function stop(): void
    {
        if ($this->process === null) {
            return;
        }

        proc_terminate($this->process);
        $deadline = microtime(true) + 2.0;
        while (microtime(true) < $deadline && proc_get_status($this->process)['running']) {
            usleep(50000);
        }
        if (proc_get_status($this->process)['running']) {
            proc_terminate($this->process, 9);
        }
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($this->process);
        $this->process = null;
        $this->pipes = [];
    }

    private function drain(): void
    {
        foreach ($this->pipes as $pipe) {
            $chunk = @stream_get_contents($pipe);
            if (is_string($chunk) && $chunk !== '') {
                // Errors are at the end of the output: keep a rolling tail, not the head.
                $this->output = substr($this->output . substr($chunk, -self::MAX_OUTPUT), -self::MAX_OUTPUT);
            }
        }
    }
}
