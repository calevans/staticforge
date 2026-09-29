<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Commands;

use EICC\StaticForge\Features\DevServer\Commands\DevServerCommand;
use EICC\StaticForge\Features\DevServer\Services\BuildRunnerInterface;
use EICC\StaticForge\Features\DevServer\Services\ClockInterface;
use EICC\StaticForge\Features\DevServer\Services\PrivateStateDir;
use EICC\StaticForge\Tests\Unit\Features\DevServer\Services\SymlinkSafeCleanup;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shared fixture for tests that drive DevServerCommand internals without a real server:
 * a throwaway site layout, a command wired to it and helpers to reach private members.
 */
abstract class DevServerCommandTestCase extends UnitTestCase
{
    use SymlinkSafeCleanup;

    protected string $base;
    /** @var list<PrivateStateDir> */
    private array $stateDirs = [];
    /** @var list<DevServerCommand> */
    private array $commands = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = (string) realpath(sys_get_temp_dir()) . '/sf_devcmd_' . bin2hex(random_bytes(4));
        foreach (['public', 'content', 'templates', 'app'] as $name) {
            mkdir($this->base . '/' . $name, 0755, true);
        }

        $this->setContainerVariable('app_root', $this->base . '/app');
        $this->setContainerVariable('OUTPUT_DIR', $this->base . '/public');
        $this->setContainerVariable('SOURCE_DIR', $this->base . '/content');
        $this->setContainerVariable('TEMPLATE_DIR', $this->base . '/templates');
    }

    protected function tearDown(): void
    {
        foreach ($this->commands as $command) {
            $command->cleanup();
        }
        foreach ($this->stateDirs as $dir) {
            $this->makeWritable($dir->path());
            $dir->remove();
        }
        $this->removeDirectory($this->base);
        parent::tearDown();
    }

    protected function command(?BuildRunnerInterface $runner = null, ?ClockInterface $clock = null): DevServerCommand
    {
        $command = new DevServerCommand($this->container, $runner, $clock);
        $this->setProperty($command, 'publicDir', $this->base . '/public');
        $this->commands[] = $command;

        return $command;
    }

    protected function attachStateDir(DevServerCommand $command): PrivateStateDir
    {
        $dir = PrivateStateDir::create();
        $this->stateDirs[] = $dir;
        $this->setProperty($command, 'stateDir', $dir);

        return $dir;
    }

    /**
     * @return array{0: BufferedOutput, 1: SymfonyStyle}
     */
    protected function bufferedIo(): array
    {
        $output = new BufferedOutput();

        return [$output, new SymfonyStyle(new ArrayInput([]), $output)];
    }

    protected function setProperty(object $object, string $name, mixed $value): void
    {
        (new ReflectionProperty($object, $name))->setValue($object, $value);
    }

    protected function callPrivate(object $object, string $method, mixed ...$args): mixed
    {
        return (new ReflectionMethod($object, $method))->invoke($object, ...$args);
    }

    /**
     * @return array{v: int, status: string, error: string}
     */
    protected function readState(PrivateStateDir $dir): array
    {
        $data = json_decode((string) file_get_contents($dir->file('state.json')), true);
        $this->assertIsArray($data, 'state.json must be valid JSON');

        return ['v' => (int) $data['v'], 'status' => (string) $data['status'], 'error' => (string) $data['error']];
    }

    protected function makeWritable(string $path): void
    {
        if (is_dir($path)) {
            @chmod($path, 0700);
        }
    }

    protected function runningAsRoot(): bool
    {
        return function_exists('posix_geteuid') && posix_geteuid() === 0;
    }
}
