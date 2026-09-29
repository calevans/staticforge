<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Services;

use EICC\StaticForge\Features\DevServer\Services\BuildRequest;
use EICC\StaticForge\Features\DevServer\Services\FileSignatureProvider;
use EICC\StaticForge\Features\DevServer\Services\WatchLoop;
use EICC\StaticForge\Tests\Mocks\FakeClock;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;

class FileSignatureProviderTest extends UnitTestCase
{
    use SymlinkSafeCleanup;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = (string) realpath(sys_get_temp_dir()) . '/sf_sig_' . bin2hex(random_bytes(4));
        mkdir($this->base . '/content/sub', 0755, true);
        mkdir($this->base . '/templates', 0755);
        file_put_contents($this->base . '/content/a.md', 'A');
        file_put_contents($this->base . '/content/sub/b.md', 'BB');
        file_put_contents($this->base . '/templates/base.twig', 'T');
        file_put_contents($this->base . '/.env', 'X=1');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->base);
        parent::tearDown();
    }

    public function testSignatureListsEveryRegularFileWithMtimeAndSize(): void
    {
        touch($this->base . '/content/sub/b.md', 1700000000);

        $signature = (new FileSignatureProvider(
            [$this->base . '/content', $this->base . '/.env'],
            []
        ))->signature();

        $this->assertSame(
            [
                $this->base . '/content/a.md',
                $this->base . '/content/sub/b.md',
                $this->base . '/.env',
            ],
            array_keys($signature)
        );
        $this->assertSame('1700000000:2', $signature[$this->base . '/content/sub/b.md']);
    }

    public function testSameSizeEditWithNewMtimeChangesTheSignature(): void
    {
        touch($this->base . '/content/a.md', 1700000000);
        $provider = new FileSignatureProvider([$this->base . '/content'], []);
        $before = $provider->signature();

        file_put_contents($this->base . '/content/a.md', 'Z');
        touch($this->base . '/content/a.md', 1700000005);

        $this->assertNotSame($before, $provider->signature());
    }

    public function testMissingRootsAreIgnored(): void
    {
        $provider = new FileSignatureProvider(
            [$this->base . '/siteconfig.d', $this->base . '/siteconfig.yaml', $this->base . '/content'],
            []
        );

        $this->assertCount(2, $provider->signature());
    }

    public function testEveryRootMissingYieldsEmptySignature(): void
    {
        $this->assertSame([], (new FileSignatureProvider([$this->base . '/nope'], []))->signature());
    }

    public function testExcludedNamedDirectoryIsSkippedAtAnyDepth(): void
    {
        mkdir($this->base . '/content/sub/.staticforge-build', 0755);
        file_put_contents($this->base . '/content/sub/.staticforge-build/cache.json', '{}');

        $signature = (new FileSignatureProvider([$this->base . '/content'], []))->signature();

        foreach (array_keys($signature) as $path) {
            $this->assertStringNotContainsString('.staticforge-build', $path);
        }
    }

    public function testExcludedDirectoriesAreSkippedEvenWhenInsideAWatchedRoot(): void
    {
        mkdir($this->base . '/content/out', 0755);
        file_put_contents($this->base . '/content/out/index.html', 'x');
        mkdir($this->base . '/content/private', 0755);
        file_put_contents($this->base . '/content/private/state.json', '{}');

        $signature = (new FileSignatureProvider(
            [$this->base . '/content'],
            [$this->base . '/content/out', $this->base . '/content/private/']
        ))->signature();

        $this->assertArrayNotHasKey($this->base . '/content/out/index.html', $signature);
        $this->assertArrayNotHasKey($this->base . '/content/private/state.json', $signature);
        $this->assertArrayHasKey($this->base . '/content/a.md', $signature);
    }

    public function testExcludedPathsGivenThroughASymlinkAreResolvedBeforeComparing(): void
    {
        mkdir($this->base . '/content/out', 0755);
        file_put_contents($this->base . '/content/out/index.html', 'x');
        symlink($this->base . '/content/out', $this->base . '/outlink');

        $signature = (new FileSignatureProvider([$this->base . '/content'], [$this->base . '/outlink']))->signature();

        $this->assertArrayNotHasKey($this->base . '/content/out/index.html', $signature);
    }

    public function testWritesInsideExcludedPathsNeverTriggerABuild(): void
    {
        mkdir($this->base . '/content/out', 0755);
        mkdir($this->base . '/content/.staticforge-build', 0755);
        mkdir($this->base . '/privdir', 0755);
        $provider = new FileSignatureProvider(
            [$this->base . '/content', $this->base . '/privdir'],
            [$this->base . '/content/out', $this->base . '/privdir']
        );
        $clock = new FakeClock(1000);
        $loop = new WatchLoop($clock);
        $loop->tick($provider->signature(), false);

        file_put_contents($this->base . '/content/out/index.html', 'built');
        file_put_contents($this->base . '/content/.staticforge-build/manifest.json', '{}');
        file_put_contents($this->base . '/privdir/state.json', '{"v":2}');
        $clock->advance(5000);

        $this->assertNull($loop->tick($provider->signature(), false));
    }

    public function testSymlinksAreNeverFollowedOrReported(): void
    {
        $outside = $this->base . '_outside';
        mkdir($outside, 0755);
        file_put_contents($outside . '/secret.txt', 'S');
        symlink($outside, $this->base . '/content/dirlink');
        symlink($outside . '/secret.txt', $this->base . '/content/filelink.md');

        try {
            $signature = (new FileSignatureProvider([$this->base . '/content'], []))->signature();
        } finally {
            $this->removeDirectory($outside);
        }

        foreach (array_keys($signature) as $path) {
            $this->assertStringNotContainsString('dirlink', $path);
            $this->assertStringNotContainsString('filelink', $path);
        }
    }

    public function testSymlinkLoopDoesNotHang(): void
    {
        symlink($this->base . '/content', $this->base . '/content/sub/loop');
        symlink('../..', $this->base . '/content/sub/up');

        $signature = (new FileSignatureProvider([$this->base . '/content'], []))->signature();

        $this->assertCount(2, $signature);
    }

    public function testUnreadableDirectoryIsSkippedWithoutError(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root ignores directory permissions');
        }

        mkdir($this->base . '/content/locked', 0755);
        file_put_contents($this->base . '/content/locked/x.md', 'x');
        chmod($this->base . '/content/locked', 0000);

        try {
            $signature = (new FileSignatureProvider([$this->base . '/content'], []))->signature();
        } finally {
            chmod($this->base . '/content/locked', 0755);
        }

        $this->assertArrayHasKey($this->base . '/content/a.md', $signature);
        $this->assertArrayNotHasKey($this->base . '/content/locked/x.md', $signature);
    }

    public function testTemplateOnlyEditChangesTheSignature(): void
    {
        touch($this->base . '/templates/base.twig', 1700000000);
        $provider = new FileSignatureProvider([$this->base . '/content', $this->base . '/templates'], []);
        $before = $provider->signature();

        file_put_contents($this->base . '/templates/base.twig', 'T2');

        $this->assertNotSame($before, $provider->signature());
    }

    public function testIntervalIsTheMinimumBeforeAnyScan(): void
    {
        $this->assertSame(500, (new FileSignatureProvider([$this->base . '/content'], []))->intervalMs());
    }

    public function testIntervalStaysAtTheMinimumForAFastScan(): void
    {
        $provider = new FileSignatureProvider([$this->base . '/content'], []);

        $provider->signature();

        $this->assertSame(FileSignatureProvider::MIN_INTERVAL_MS, $provider->intervalMs());
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function scanDurationProvider(): array
    {
        return [
            'instant' => [0, 500],
            'just under the crossover' => [99, 500],
            'crossover' => [100, 500],
            'just over the crossover' => [101, 505],
            'slow mount' => [400, 2000],
            'very slow mount' => [3000, 15000],
        ];
    }

    #[DataProvider('scanDurationProvider')]
    public function testIntervalIsFiveTimesTheLastScanButNeverBelowTheMinimum(int $scanMs, int $expected): void
    {
        $provider = new FileSignatureProvider([$this->base . '/content'], []);
        (new ReflectionProperty($provider, 'lastScanMs'))->setValue($provider, $scanMs);

        $this->assertSame($expected, $provider->intervalMs());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function editorTempNameProvider(): array
    {
        return [
            'vim swap' => ['.a.md.swp'],
            'vim swap overflow' => ['.a.md.swo'],
            'backup tilde' => ['a.md~'],
            'emacs lock' => ['.#a.md'],
            'vim write probe' => ['4913'],
            'macOS metadata' => ['.DS_Store'],
            'generic temp' => ['a.md.tmp'],
            'temp only' => ['.tmp'],
        ];
    }

    #[DataProvider('editorTempNameProvider')]
    public function testEditorSwapAndTempFilesAreIgnored(string $name): void
    {
        file_put_contents($this->base . '/content/' . $name, 'junk');
        file_put_contents($this->base . '/content/sub/' . $name, 'junk');

        $signature = (new FileSignatureProvider([$this->base . '/content'], []))->signature();

        $this->assertSame(
            [$this->base . '/content/a.md', $this->base . '/content/sub/b.md'],
            array_keys($signature)
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function realFileNameProvider(): array
    {
        return [
            'markdown' => ['page.md'],
            'similar to a swap name' => ['swp.md'],
            'contains tmp in the middle' => ['tmp.notes.md'],
            'number close to vim probe' => ['4914'],
            'dot file' => ['.htaccess'],
            'hash inside name' => ['a#b.md'],
            'tilde inside name' => ['a~b.md'],
        ];
    }

    #[DataProvider('realFileNameProvider')]
    public function testRealFilesWithSimilarNamesAreStillSeen(string $name): void
    {
        file_put_contents($this->base . '/content/' . $name, 'real');

        $signature = (new FileSignatureProvider([$this->base . '/content'], []))->signature();

        $this->assertArrayHasKey($this->base . '/content/' . $name, $signature);
    }

    private function scan(WatchLoop $loop, FileSignatureProvider $provider): ?BuildRequest
    {
        return $loop->tick($provider->signature(), false);
    }

    public function testEditorSaveDanceDoesNotTriggerABuildUntilTheRealFileChanges(): void
    {
        $provider = new FileSignatureProvider([$this->base . '/content'], []);
        $clock = new FakeClock(1000);
        $loop = new WatchLoop($clock);
        $loop->tick($provider->signature(), false);

        file_put_contents($this->base . '/content/.a.md.swp', 'swap');
        file_put_contents($this->base . '/content/a.md~', 'backup');
        $clock->advance(5000);
        $this->assertNull($loop->tick($provider->signature(), false));

        file_put_contents($this->base . '/content/a.md', 'A saved with more text');
        $clock->advance(100);
        $loop->tick($provider->signature(), false);
        $clock->advance(400);
        $request = $this->scan($loop, $provider);

        $this->assertNotNull($request);
        $this->assertSame([$this->base . '/content/a.md'], $request->changed);
    }
}
