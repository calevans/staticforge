<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Services;

use EICC\StaticForge\Features\DevServer\Services\PrivateStateDir;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class PrivateStateDirTest extends UnitTestCase
{
    use SymlinkSafeCleanup;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = (string) realpath(sys_get_temp_dir()) . '/sf_psd_' . bin2hex(random_bytes(4));
        mkdir($this->base, 0755);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->base);
        parent::tearDown();
    }

    public function testDirectoryIsCreatedInsideTheGivenBaseWithMode0700(): void
    {
        $dir = PrivateStateDir::create($this->base);

        $this->assertSame($this->base, dirname($dir->path()));
        $this->assertDirectoryExists($dir->path());
        $this->assertSame(0700, fileperms($dir->path()) & 0777);
    }

    public function testModeIsExactRegardlessOfUmask(): void
    {
        foreach ([0000, 0077, 0002] as $mask) {
            $old = umask($mask);
            try {
                $dir = PrivateStateDir::create($this->base);
            } finally {
                umask($old);
            }

            $this->assertSame(0700, fileperms($dir->path()) & 0777, sprintf('umask %04o', $mask));
        }
    }

    public function testNameIsPrefixedAndRandom(): void
    {
        $names = [];
        for ($i = 0; $i < 25; $i++) {
            $dir = PrivateStateDir::create($this->base);
            $this->assertMatchesRegularExpression('/^staticforge-dev-[0-9a-f]{16}$/', basename($dir->path()));
            $names[] = $dir->path();
        }

        $this->assertCount(25, array_unique($names));
        $this->assertStringNotContainsString((string) getmypid(), basename($names[0]));
    }

    public function testDirectoryIsOwnedByTheCurrentUser(): void
    {
        $dir = PrivateStateDir::create($this->base);

        $stat = lstat($dir->path());
        $this->assertIsArray($stat);
        $this->assertSame(posix_geteuid(), $stat['uid']);
    }

    public function testCreateFailsWhenBaseDirectoryDoesNotExist(): void
    {
        $this->expectException(\RuntimeException::class);

        PrivateStateDir::create($this->base . '/missing');
    }

    public function testCreateNeverTouchesAnExistingSimilarlyNamedDirectory(): void
    {
        $existing = $this->base . '/' . PrivateStateDir::PREFIX . '0123456789abcdef';
        mkdir($existing, 0755);
        file_put_contents($existing . '/keep.txt', 'keep');

        $dir = PrivateStateDir::create($this->base);

        $this->assertNotSame($existing, $dir->path());
        $this->assertFileExists($existing . '/keep.txt');
        $this->assertSame(0755, fileperms($existing) & 0777);
    }

    public function testWriteStoresContentsWithOwnerOnlyModeAndLeavesNoTempFiles(): void
    {
        $dir = PrivateStateDir::create($this->base);

        $dir->write('state.json', '{"v":1}');
        $dir->write('state.json', '{"v":2}');

        $this->assertSame('{"v":2}', file_get_contents($dir->file('state.json')));
        $this->assertSame(0600, fileperms($dir->file('state.json')) & 0777);
        $this->assertSame(['.', '..', 'state.json'], scandir($dir->path()));
    }

    public function testWriteReplacesAPlantedSymlinkInsteadOfWritingThroughIt(): void
    {
        $dir = PrivateStateDir::create($this->base);
        $victim = $this->base . '/victim.txt';
        file_put_contents($victim, 'ORIGINAL');
        symlink($victim, $dir->file('router.php'));

        $dir->write('router.php', '<?php // new');

        $this->assertSame('ORIGINAL', file_get_contents($victim));
        $this->assertFalse(is_link($dir->file('router.php')));
        $this->assertSame('<?php // new', file_get_contents($dir->file('router.php')));
    }

    public function testWriteThatFailsToRenameLeavesNoTempFileAndKeepsThePreviousContents(): void
    {
        $dir = PrivateStateDir::create($this->base);
        $dir->write('state.json', '{"v":1}');
        // A non-empty directory in the target's place makes rename() fail after the temp file exists.
        unlink($dir->file('state.json'));
        mkdir($dir->file('state.json'));
        file_put_contents($dir->file('state.json') . '/keep', 'keep');
        set_error_handler(static function (int $no, string $message): never {
            throw new \ErrorException($message);
        });

        $thrown = false;
        try {
            $dir->write('state.json', '{"v":2}');
        } catch (\Throwable) {
            $thrown = true;
        } finally {
            restore_error_handler();
        }

        $this->assertTrue($thrown, 'write() must throw when the target cannot be replaced');
        $this->assertSame(['.', '..', 'state.json'], scandir($dir->path()));
        $this->assertSame('keep', file_get_contents($dir->file('state.json') . '/keep'));
    }

    public function testWriteIntoAReadOnlyDirectoryThrowsAndLeavesNoTempFile(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root ignores directory permissions');
        }
        $dir = PrivateStateDir::create($this->base);
        $dir->write('state.json', '{"v":1}');
        chmod($dir->path(), 0500);
        set_error_handler(static function (int $no, string $message): never {
            throw new \ErrorException($message);
        });

        $thrown = false;
        try {
            $dir->write('state.json', '{"v":2}');
        } catch (\Throwable) {
            $thrown = true;
        } finally {
            restore_error_handler();
            chmod($dir->path(), 0700);
        }

        $this->assertTrue($thrown, 'write() must throw when the directory is not writable');
        $this->assertSame(['.', '..', 'state.json'], scandir($dir->path()));
        $this->assertSame('{"v":1}', file_get_contents($dir->file('state.json')));
    }

    public function testWriteToAnUnwritableDirectoryWithoutAnErrorHandlerStillThrowsARuntimeException(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root ignores directory permissions');
        }
        $dir = PrivateStateDir::create($this->base);
        chmod($dir->path(), 0500);
        // Silence the warning file_put_contents raises so only the exception contract is checked.
        set_error_handler(static fn(): bool => true);

        try {
            $this->expectException(\RuntimeException::class);
            $dir->write('state.json', 'x');
        } finally {
            restore_error_handler();
            chmod($dir->path(), 0700);
        }
    }

    public function testRemoveDeletesDirectoryAndItsFilesAndIsIdempotent(): void
    {
        $dir = PrivateStateDir::create($this->base);
        $dir->write('router.php', 'x');
        $dir->write('state.json', 'y');

        $dir->remove();
        $dir->remove();

        $this->assertDirectoryDoesNotExist($dir->path());
    }

    public function testRemoveDoesNotFollowSymlinksInsideTheDirectory(): void
    {
        $dir = PrivateStateDir::create($this->base);
        mkdir($this->base . '/outside');
        file_put_contents($this->base . '/outside/keep.txt', 'keep');
        symlink($this->base . '/outside', $dir->file('link'));

        $dir->remove();

        $this->assertFileExists($this->base . '/outside/keep.txt');
        $this->assertDirectoryDoesNotExist($dir->path());
    }

    public function testRemoveRefusesWhenThePathHasBeenSwappedForASymlink(): void
    {
        $dir = PrivateStateDir::create($this->base);
        $path = $dir->path();
        rmdir($path);
        mkdir($this->base . '/target');
        file_put_contents($this->base . '/target/keep.txt', 'keep');
        symlink($this->base . '/target', $path);

        $dir->remove();

        $this->assertFileExists($this->base . '/target/keep.txt');
        $this->assertTrue(is_link($path));
    }

    public function testPreexistingSymlinkAtOldPredictablePathIsNeverFollowedOrWritten(): void
    {
        $old = sys_get_temp_dir() . '/staticforge-devserver-router-' . getmypid() . '.php';
        if (file_exists($old) || is_link($old)) {
            $this->markTestSkipped('Old predictable path already exists: ' . $old);
        }
        $victim = $this->base . '/victim.txt';
        file_put_contents($victim, 'ORIGINAL');
        symlink($victim, $old);

        try {
            $dir = PrivateStateDir::create();
            $dir->write('router.php', '<?php // generated');
            $dir->write('state.json', '{}');
            $dir->remove();

            $this->assertSame('ORIGINAL', file_get_contents($victim));
            $this->assertTrue(is_link($old));
            $this->assertSame($victim, readlink($old));
        } finally {
            @unlink($old);
        }
    }
}
