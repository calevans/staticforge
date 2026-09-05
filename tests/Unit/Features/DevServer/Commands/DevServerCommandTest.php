<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Commands;

use EICC\StaticForge\Features\DevServer\Commands\DevServerCommand;
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

    public function testInitializePlacesRouterFileOutsidePublicDir(): void
    {
        mkdir($this->tempCwd . '/public', 0755, true);

        $command = new DevServerCommand($this->container);
        $method = new ReflectionMethod($command, 'initialize');

        $input = new \Symfony\Component\Console\Input\ArrayInput([]);
        $input->bind($command->getDefinition());
        $output = new \Symfony\Component\Console\Output\NullOutput();
        $method->invoke($command, $input, $output);

        $publicDirProp = new ReflectionProperty($command, 'publicDir');
        $routerFileProp = new ReflectionProperty($command, 'routerFile');

        $publicDir = $publicDirProp->getValue($command);
        $routerFile = $routerFileProp->getValue($command);

        // Regression: a live site:render wipes and regenerates publicDir, which
        // would delete the router file out from under a running dev server.
        $tempDir = sys_get_temp_dir();
        $this->assertNotEmpty($tempDir);
        $this->assertStringStartsNotWith($publicDir, $routerFile);
        $this->assertStringStartsWith($tempDir, $routerFile);
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

    public function testGetRouterTemplateResolvesFilesRelativeToWorkingDirectory(): void
    {
        // Regression: the router script must resolve requested files via getcwd(),
        // not __DIR__ - the built-in server sets cwd to the docroot per request,
        // but the router script itself now lives outside that docroot.
        $command = new DevServerCommand($this->container);
        $method = new ReflectionMethod($command, 'getRouterTemplate');

        $template = $method->invoke($command);

        $this->assertStringContainsString('getcwd()', $template);
        $this->assertStringNotContainsString('__DIR__', $template);
    }

    public function testGetRouterTemplateContainsExpected404Markup(): void
    {
        $command = new DevServerCommand($this->container);
        $method = new ReflectionMethod($command, 'getRouterTemplate');

        $template = $method->invoke($command);

        $this->assertStringContainsString('http_response_code(404)', $template);
        $this->assertStringContainsString('404 - Page Not Found', $template);
        $this->assertStringContainsString('REQUEST_URI', $template);
    }

    public function testCleanupRemovesRouterFileWhenPresent(): void
    {
        mkdir($this->tempCwd . '/public', 0755, true);
        $routerFile = sys_get_temp_dir() . '/staticforge-devserver-router-test-' . uniqid() . '.php';
        file_put_contents($routerFile, '<?php // router');

        $command = new DevServerCommand($this->container);

        $publicDirProp = new ReflectionProperty($command, 'publicDir');
        $publicDirProp->setValue($command, $this->tempCwd . '/public');

        $routerFileProp = new ReflectionProperty($command, 'routerFile');
        $routerFileProp->setValue($command, $routerFile);

        $this->assertFileExists($routerFile);
        $command->cleanup();
        $this->assertFileDoesNotExist($routerFile);
    }

    public function testCleanupIsSafeWhenRouterFileMissing(): void
    {
        $command = new DevServerCommand($this->container);

        $routerFileProp = new ReflectionProperty($command, 'routerFile');
        $missingRouterFile = sys_get_temp_dir() . '/staticforge-devserver-router-test-' . uniqid() . '.php';
        $routerFileProp->setValue($command, $missingRouterFile);

        // Should not throw even though the file was never created
        $command->cleanup();
        $this->assertFileDoesNotExist($missingRouterFile);
    }
    public function testSubscribesToInterruptAndTerminateSignals(): void
    {
        $command = new DevServerCommand($this->container);

        $this->assertContains(\SIGINT, $command->getSubscribedSignals());
        $this->assertContains(\SIGTERM, $command->getSubscribedSignals());
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

        $routerFile = $this->tempCwd . '/router-probe.php';
        file_put_contents($routerFile, '<?php');

        $command = new DevServerCommand($this->container);
        (new ReflectionProperty($command, 'routerFile'))->setValue($command, $routerFile);

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
