<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Core\OutputWriter;
use EICC\StaticForge\Exceptions\FileProcessingException;
use EICC\Utils\Container;
use EICC\Utils\Log;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OutputWriterTest extends TestCase
{
    private OutputWriter $writer;
    private Container $container;
    private Log&MockObject $logger;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/staticforge_outputwriter_' . uniqid();
        mkdir($this->outputDir, 0755, true);

        $this->container = new Container();
        $this->container->setVariable('OUTPUT_DIR', $this->outputDir);

        $this->logger = $this->createMock(Log::class);
        $this->writer = new OutputWriter($this->container, $this->logger);
    }

    protected function tearDown(): void
    {
        $this->recursiveRemove($this->outputDir);
        $this->recursiveRemove($this->outputDir . '-evil');
    }

    private function recursiveRemove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRemove("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function testWriteCreatesFileWithContent(): void
    {
        $path = $this->outputDir . '/page.html';

        $this->writer->write($path, '<h1>Hello</h1>');

        $this->assertFileExists($path);
        $this->assertSame('<h1>Hello</h1>', file_get_contents($path));
    }

    public function testWriteCreatesNestedDirectories(): void
    {
        $path = $this->outputDir . '/blog/2026/post.html';

        $this->writer->write($path, 'content');

        $this->assertFileExists($path);
    }

    public function testWriteAllowsRewritingExistingFile(): void
    {
        $path = $this->outputDir . '/main.css';

        $this->writer->write($path, 'body { color: red; }');
        $this->writer->write($path, 'body { color: blue; }');

        $this->assertSame('body { color: blue; }', file_get_contents($path));
    }

    public function testWriteLeavesNoTempFileBehind(): void
    {
        $path = $this->outputDir . '/page.html';

        $this->writer->write($path, 'content');

        $this->assertFileDoesNotExist($path . '.tmp');
    }

    public function testWriteRejectsPathOutsideOutputDir(): void
    {
        mkdir($this->outputDir . '-evil', 0755, true);

        $this->expectException(\RuntimeException::class);
        $this->writer->write($this->outputDir . '-evil/secret.html', 'leaked');
    }

    public function testWriteThrowsWhenOutputDirNotSet(): void
    {
        $container = new Container();
        $writer = new OutputWriter($container, $this->logger);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OUTPUT_DIR not set in container');

        $writer->write('/tmp/whatever.html', 'content');
    }
}
