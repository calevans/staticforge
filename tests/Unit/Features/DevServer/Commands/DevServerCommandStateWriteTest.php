<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Commands;

use EICC\StaticForge\Features\DevServer\Services\DevServerRouter;

/**
 * DevServerCommand::writeState() must never take the server down and must never
 * leave state.json invalid or reset.
 */
class DevServerCommandStateWriteTest extends DevServerCommandTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Worst case: the failed write surfaces as an ErrorException, not just a false return.
        set_error_handler(static function (int $no, string $message): never {
            throw new \ErrorException($message);
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        parent::tearDown();
    }

    public function testInvalidUtf8InBuildOutputStillProducesValidJsonWithSubstitutedCharacters(): void
    {
        $command = $this->command();
        $dir = $this->attachStateDir($command);
        $this->setProperty($command, 'stateVersion', 4);

        $this->callPrivate($command, 'writeState', 'failed', "Fatal error: bad \xff\xfe byte");

        $state = $this->readState($dir);
        $this->assertSame(4, $state['v']);
        $this->assertSame('failed', $state['status']);
        $this->assertTrue(mb_check_encoding($state['error'], 'UTF-8'));
        $this->assertStringStartsWith('Fatal error: bad ', $state['error']);
        $this->assertStringEndsWith(' byte', $state['error']);
        $this->assertStringNotContainsString("\xff", (string) file_get_contents($dir->file('state.json')));
    }

    public function testStateWrittenByTheCommandIsServedByTheRouterEndpointAfterInvalidUtf8(): void
    {
        $command = $this->command();
        $dir = $this->attachStateDir($command);
        $this->setProperty($command, 'stateVersion', 3);
        $this->callPrivate($command, 'writeState', 'failed', "Error: \xc3\x28 broken");

        $router = new DevServerRouter($this->base . '/public', true, $dir->file('state.json'), ['localhost']);
        $response = $router->handle('GET', '/__staticforge/state', 'localhost:8000');

        $this->assertNotNull($response);
        $served = json_decode($response->body, true);
        $this->assertIsArray($served);
        $this->assertSame(3, $served['v']);
        $this->assertSame('failed', $served['status']);
    }

    public function testWriteFailureIsLoggedAndDoesNotThrow(): void
    {
        if ($this->runningAsRoot()) {
            $this->markTestSkipped('root ignores directory permissions');
        }
        $command = $this->command();
        $dir = $this->attachStateDir($command);
        [$output, $io] = $this->bufferedIo();
        $this->setProperty($command, 'io', $io);
        chmod($dir->path(), 0500);

        $this->callPrivate($command, 'writeState', 'building', '');

        $this->assertStringContainsString('Could not update the reload state', $output->fetch());
    }

    public function testWriteFailureKeepsThePreviousStateFileUntouched(): void
    {
        if ($this->runningAsRoot()) {
            $this->markTestSkipped('root ignores directory permissions');
        }
        $command = $this->command();
        $dir = $this->attachStateDir($command);
        [, $io] = $this->bufferedIo();
        $this->setProperty($command, 'io', $io);
        $this->setProperty($command, 'stateVersion', 5);
        $this->callPrivate($command, 'writeState', 'ok', '');
        chmod($dir->path(), 0500);

        $this->setProperty($command, 'stateVersion', 6);
        $this->callPrivate($command, 'writeState', 'failed', 'Fatal error: later');
        chmod($dir->path(), 0700);

        $this->assertSame(['v' => 5, 'status' => 'ok', 'error' => ''], $this->readState($dir));
    }

    public function testWriteStateWithoutAStateDirectoryIsANoOp(): void
    {
        $command = $this->command();
        [$output, $io] = $this->bufferedIo();
        $this->setProperty($command, 'io', $io);

        $this->callPrivate($command, 'writeState', 'ok', '');

        $this->assertSame('', $output->fetch());
    }

    public function testWriteStateFailureBeforeTheConsoleExistsDoesNotThrow(): void
    {
        $command = $this->command();
        $dir = $this->attachStateDir($command);
        $dir->remove();

        $this->callPrivate($command, 'writeState', 'ok', '');

        $this->assertDirectoryDoesNotExist($dir->path());
    }
}
