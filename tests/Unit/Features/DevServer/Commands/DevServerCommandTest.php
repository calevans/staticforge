<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Commands;

use EICC\StaticForge\Features\DevServer\Commands\DevServerCommand;
use EICC\StaticForge\Features\DevServer\Services\PrivateStateDir;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use ReflectionMethod;
use ReflectionProperty;

/**
 * DevServerCommand starts a long-running `php -S` process via popen(), which is not
 * suitable to exercise in a unit test. These tests focus on the testable, side-effect-free
 * logic: option configuration, the "missing public dir" failure guard, the router file
 * template content, and the port-in-use detection helper (which itself is a pure socket check).
 */
class DevServerCommandTest extends UnitTestCase
{
    private string $tempCwd;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = (string) getcwd();
        $this->tempCwd = sys_get_temp_dir() . '/staticforge_devserver_test_' . uniqid();
        mkdir($this->tempCwd, 0755, true);
        chdir($this->tempCwd);
        $this->setContainerVariable('OUTPUT_DIR', $this->tempCwd . '/public');
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeDirectory($this->tempCwd);
        parent::tearDown();
    }

    public function testConfigureDefinesExpectedOptions(): void
    {
        $command = new DevServerCommand($this->container);

        $this->assertTrue($command->getDefinition()->hasOption('port'));
        $this->assertTrue($command->getDefinition()->hasOption('host'));
        $this->assertSame('8000', $command->getDefinition()->getOption('port')->getDefault());
        $this->assertSame('localhost', $command->getDefinition()->getOption('host')->getDefault());
    }

    public function testExecuteFailsWhenPublicDirectoryMissing(): void
    {
        // No /public directory created under tempCwd
        $application = new Application();
        $application->addCommand(new DevServerCommand($this->container));

        $command = $application->find('site:devserver');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Public directory not found', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testExecuteFailsWhenPortAlreadyInUse(): void
    {
        mkdir($this->tempCwd . '/public', 0755, true);

        // Bind a real socket to occupy a port, then ask the command to use that same port
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($socket, 'Failed to open test socket: ' . $errstr);

        $name = stream_socket_get_name($socket, false);
        $this->assertNotFalse($name, 'Failed to resolve bound socket name');
        $port = (int) substr($name, strrpos($name, ':') + 1);

        $application = new Application();
        $application->addCommand(new DevServerCommand($this->container));

        $command = $application->find('site:devserver');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['--host' => '127.0.0.1', '--port' => (string) $port]);

        $output = $commandTester->getDisplay();
        fclose($socket);

        $this->assertStringContainsString('already in use', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testIsPortInUseReturnsFalseForUnusedPort(): void
    {
        $command = new DevServerCommand($this->container);
        $method = new ReflectionMethod($command, 'isPortInUse');

        // Port 0 / an arbitrarily high unlikely-to-be-bound port
        $result = $method->invoke($command, '127.0.0.1', 65530);

        $this->assertFalse($result);
    }

    public function testPrivateStateDirLivesOutsidePublicDir(): void
    {
        mkdir($this->tempCwd . '/public', 0755, true);

        // Regression: a live site:render wipes and regenerates publicDir, which
        // would delete the router file out from under a running dev server.
        $dir = PrivateStateDir::create();
        try {
            $this->assertStringStartsNotWith($this->tempCwd . '/public', $dir->path());
            $this->assertTrue(str_starts_with($dir->path(), sys_get_temp_dir()));
            $this->assertSame(0700, fileperms($dir->path()) & 0777);
        } finally {
            $dir->remove();
        }
    }

    public function testInitializeUsesOutputDirFromContainer(): void
    {
        $configuredOutputDir = sys_get_temp_dir() . '/staticforge_devserver_outputdir_' . uniqid();
        mkdir($configuredOutputDir, 0755, true);
        $this->setContainerVariable('OUTPUT_DIR', $configuredOutputDir);

        $command = new DevServerCommand($this->container);
        $method = new ReflectionMethod($command, 'initialize');

        $input = new \Symfony\Component\Console\Input\ArrayInput([]);
        $input->bind($command->getDefinition());
        $method->invoke($command, $input, new \Symfony\Component\Console\Output\NullOutput());

        $publicDirProp = new ReflectionProperty($command, 'publicDir');

        $this->assertSame($configuredOutputDir, $publicDirProp->getValue($command));

        $this->removeDirectory($configuredOutputDir);
    }

    public function testInitializeFallsBackToCwdPublicWhenOutputDirNotSet(): void
    {
        $container = new \EICC\Utils\Container();
        $command = new DevServerCommand($container);
        $method = new ReflectionMethod($command, 'initialize');

        $input = new \Symfony\Component\Console\Input\ArrayInput([]);
        $input->bind($command->getDefinition());
        $method->invoke($command, $input, new \Symfony\Component\Console\Output\NullOutput());

        $publicDirProp = new ReflectionProperty($command, 'publicDir');

        $this->assertSame($this->tempCwd . '/public', $publicDirProp->getValue($command));
    }

    public function testRouterSourceDelegatesToRouterClassWithDocroot(): void
    {
        $command = new DevServerCommand($this->container);
        (new ReflectionProperty($command, 'publicDir'))->setValue($command, $this->tempCwd . '/public');

        $source = $command->buildRouterSource('localhost', false, '/x/state.json');

        $this->assertStringContainsString('DevServerRouter', $source);
        $this->assertStringContainsString(var_export($this->tempCwd . '/public', true), $source);
        $this->assertStringNotContainsString('__DIR__', $source);
    }

    public function testConfigureDefinesWatchOptions(): void
    {
        $definition = (new DevServerCommand($this->container))->getDefinition();

        $this->assertTrue($definition->hasOption('watch'));
        $this->assertTrue($definition->hasOption('allow-remote'));
        $this->assertTrue($definition->hasOption('include-drafts'));
    }

    public function testCleanupRemovesPrivateDirWhenPresent(): void
    {
        $dir = PrivateStateDir::create();
        $dir->write('router.php', '<?php // router');
        $routerFile = $dir->file('router.php');

        $command = new DevServerCommand($this->container);
        (new ReflectionProperty($command, 'stateDir'))->setValue($command, $dir);

        $this->assertFileExists($routerFile);
        $command->cleanup();
        $this->assertFileDoesNotExist($routerFile);
        $this->assertDirectoryDoesNotExist($dir->path());
    }

    public function testCleanupIsSafeWhenNothingWasCreated(): void
    {
        $command = new DevServerCommand($this->container);

        $command->cleanup();
        $this->expectNotToPerformAssertions();
    }
    public function testSubscribesToInterruptAndTerminateSignals(): void
    {
        $command = new DevServerCommand($this->container);

        $this->assertContains(\SIGINT, $command->getSubscribedSignals());
        $this->assertContains(\SIGTERM, $command->getSubscribedSignals());
    }

    public function testSubscribesToHangupAndQuitSoAClosedTerminalCleansUp(): void
    {
        if (!\function_exists('pcntl_signal')) {
            $this->markTestSkipped('pcntl not available');
        }
        $command = new DevServerCommand($this->container);

        $this->assertEqualsCanonicalizing(
            [\SIGINT, \SIGTERM, \SIGHUP, \SIGQUIT],
            $command->getSubscribedSignals()
        );
    }

    /**
     * Regression: initialize() used to call pcntl_signal(SIGINT, [$this,
     * 'handleSignal']). pcntl invokes a handler as (int $signo, array|null
     * $siginfo), but handleSignal() inherits Symfony's Command signature and
     * types its second parameter int|false, so the siginfo array made every
     * Ctrl+C a fatal TypeError under strict_types. Signal registration belongs
     * to Symfony's SignalRegistry via SignalableCommandInterface.
     */
    public function testInitializeDoesNotInstallItsOwnSignalHandler(): void
    {
        if (!\function_exists('pcntl_signal_get_handler')) {
            $this->markTestSkipped('pcntl not available');
        }

        mkdir($this->tempCwd . '/public', 0755, true);
        $before = pcntl_signal_get_handler(\SIGINT);

        $command = new DevServerCommand($this->container);
        $input = new \Symfony\Component\Console\Input\ArrayInput([]);
        $input->bind($command->getDefinition());
        (new ReflectionMethod($command, 'initialize'))
            ->invoke($command, $input, new \Symfony\Component\Console\Output\NullOutput());

        $this->assertSame(
            $before,
            pcntl_signal_get_handler(\SIGINT),
            'initialize() must not register its own pcntl handler'
        );
    }

    /**
     * Drives a real SIGINT through Symfony's own SignalRegistry -- the component
     * that actually invokes handleSignal() at runtime -- rather than calling the
     * method directly, since the bug was in how the handler gets invoked.
     */
    public function testRealSigintReachesHandlerAndCleansUp(): void
    {
        if (!\function_exists('pcntl_signal') || !\function_exists('posix_kill')) {
            $this->markTestSkipped('pcntl/posix not available');
        }

        $dir = PrivateStateDir::create();
        $dir->write('router.php', '<?php');
        $routerFile = $dir->file('router.php');

        $command = new DevServerCommand($this->container);
        (new ReflectionProperty($command, 'stateDir'))->setValue($command, $dir);

        $original = pcntl_signal_get_handler(\SIGINT);
        $result = 'handler-never-ran';

        try {
            // Mirrors Symfony\Component\Console\Application::doRunCommand().
            $registry = new \Symfony\Component\Console\SignalRegistry\SignalRegistry();
            $registry->register(\SIGINT, function (int $signal) use ($command, &$result): void {
                $result = $command->handleSignal($signal);
            });

            ob_start();
            posix_kill((int) getmypid(), \SIGINT);
            // pcntl_async_signals is enabled by SignalRegistry; dispatch defensively.
            if (\function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            $echoed = (string) ob_get_clean();
        } finally {
            pcntl_signal(\SIGINT, $original ?: \SIG_DFL);
        }

        $this->assertSame(0, $result, 'handleSignal must run and return an exit code');
        $this->assertStringContainsString('Shutting down development server', $echoed);
        $this->assertFileDoesNotExist($routerFile, 'Router file must be cleaned up on shutdown');
    }
}
