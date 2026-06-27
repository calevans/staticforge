<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Search;

use EICC\StaticForge\Features\Search\Services\SearchAssetService;
use EICC\Utils\Container;
use EICC\Utils\Log;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SearchAssetServiceTest extends TestCase
{
    private SearchAssetService $service;
    private Log&MockObject $logger;
    private Container&MockObject $container;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(Log::class);
        $this->container = $this->createMock(Container::class);
        $this->service = new SearchAssetService($this->logger);

        $this->tempDir = sys_get_temp_dir() . '/staticforge_search_asset_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->recursiveRemove($this->tempDir);
        }
    }

    private function recursiveRemove(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRemove("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function testCopyAssetsLogsErrorWhenOutputDirNotSet(): void
    {
        $this->container->method('getVariable')->willReturn(null);

        $this->logger->expects($this->once())
            ->method('log')
            ->with('ERROR', $this->stringContains('OUTPUT_DIR not set'));

        $this->service->copyAssets($this->container);
    }

    public function testCopyAssetsCreatesDestinationDirectoryAndCopiesFiles(): void
    {
        $this->container->method('getVariable')
            ->willReturnMap([
                ['OUTPUT_DIR', $this->tempDir],
                ['site_config', []],
            ]);

        $this->service->copyAssets($this->container);

        $this->assertDirectoryExists($this->tempDir . '/assets/js');
        $this->assertFileExists($this->tempDir . '/assets/js/minisearch.min.js');
        $this->assertFileExists($this->tempDir . '/assets/js/search.js');
    }

    public function testCopyAssetsLogsErrorWhenDestinationCannotBeCreated(): void
    {
        // Destination directory cannot be created because OUTPUT_DIR points at a plain file
        // (mkdir() against a non-directory parent raises a native PHP warning, which we
        // suppress here since the production code already detects and logs the failure).
        $blockedPath = $this->tempDir . '/blocked-file';
        file_put_contents($blockedPath, 'not a directory');

        $this->container->method('getVariable')
            ->willReturnMap([
                ['OUTPUT_DIR', $blockedPath],
                ['site_config', []],
            ]);

        $this->logger->expects($this->once())
            ->method('log')
            ->with('ERROR', $this->stringContains('Failed to create directory'));

        @$this->service->copyAssets($this->container);
    }

    public function testCopyAssetsUsesMiniSearchEngineByDefault(): void
    {
        $this->container->method('getVariable')
            ->willReturnMap([
                ['OUTPUT_DIR', $this->tempDir],
                ['site_config', []],
            ]);

        $this->service->copyAssets($this->container);

        $this->assertFileExists($this->tempDir . '/assets/js/minisearch.min.js');
        $this->assertFileDoesNotExist($this->tempDir . '/assets/js/fuse.basic.min.js');
    }

    public function testCopyAssetsUsesFuseEngineWhenConfigured(): void
    {
        $this->container->method('getVariable')
            ->willReturnMap([
                ['OUTPUT_DIR', $this->tempDir],
                ['site_config', ['search' => ['engine' => 'fuse']]],
            ]);

        $this->service->copyAssets($this->container);

        $this->assertFileExists($this->tempDir . '/assets/js/fuse.basic.min.js');

        $reflection = new \ReflectionClass(SearchAssetService::class);
        $classFile = $reflection->getFileName();
        $this->assertNotFalse($classFile, 'Expected to resolve the SearchAssetService source file');
        $sourceDir = dirname($classFile) . '/../assets/js';

        $copiedSearchJs = file_get_contents($this->tempDir . '/assets/js/search.js');
        $fuseSearchJs = file_get_contents($sourceDir . '/search-fuse.js');
        $this->assertNotFalse($copiedSearchJs);
        $this->assertNotFalse($fuseSearchJs);
        $this->assertSame($fuseSearchJs, $copiedSearchJs);
    }
}
