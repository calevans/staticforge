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
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeDirectory($this->tempCwd);
        parent::tearDown();
    }

    public function testConfigureDefinesExpectedOptions(): void
    {
        $command = new DevServerCommand();

        $this->assertTrue($command->getDefinition()->hasOption('port'));
        $this->assertTrue($command->getDefinition()->hasOption('host'));
        $this->assertSame('8000', $command->getDefinition()->getOption('port')->getDefault());
        $this->assertSame('localhost', $command->getDefinition()->getOption('host')->getDefault());
    }

    public function testExecuteFailsWhenPublicDirectoryMissing(): void
    {
        // No /public directory created under tempCwd
        $application = new Application();
        $application->add(new DevServerCommand());

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
        $application->add(new DevServerCommand());

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
        $command = new DevServerCommand();
        $method = new ReflectionMethod($command, 'isPortInUse');
        $method->setAccessible(true);

        // Port 0 / an arbitrarily high unlikely-to-be-bound port
        $result = $method->invoke($command, '127.0.0.1', 65530);

        $this->assertFalse($result);
    }

    public function testGetRouterTemplateContainsExpected404Markup(): void
    {
        $command = new DevServerCommand();
        $method = new ReflectionMethod($command, 'getRouterTemplate');
        $method->setAccessible(true);

        $template = $method->invoke($command);

        $this->assertStringContainsString('http_response_code(404)', $template);
        $this->assertStringContainsString('404 - Page Not Found', $template);
        $this->assertStringContainsString('REQUEST_URI', $template);
    }

    public function testCleanupRemovesRouterFileWhenPresent(): void
    {
        mkdir($this->tempCwd . '/public', 0755, true);
        $routerFile = $this->tempCwd . '/public/.ht.route.php';
        file_put_contents($routerFile, '<?php // router');

        $command = new DevServerCommand();

        $publicDirProp = new ReflectionProperty($command, 'publicDir');
        $publicDirProp->setAccessible(true);
        $publicDirProp->setValue($command, $this->tempCwd . '/public');

        $routerFileProp = new ReflectionProperty($command, 'routerFile');
        $routerFileProp->setAccessible(true);
        $routerFileProp->setValue($command, $routerFile);

        $this->assertFileExists($routerFile);
        $command->cleanup();
        $this->assertFileDoesNotExist($routerFile);
    }

    public function testCleanupIsSafeWhenRouterFileMissing(): void
    {
        $command = new DevServerCommand();

        $routerFileProp = new ReflectionProperty($command, 'routerFile');
        $routerFileProp->setAccessible(true);
        $routerFileProp->setValue($command, $this->tempCwd . '/public/.ht.route.php');

        // Should not throw even though the file was never created
        $command->cleanup();
        $this->assertFileDoesNotExist($this->tempCwd . '/public/.ht.route.php');
    }
}
