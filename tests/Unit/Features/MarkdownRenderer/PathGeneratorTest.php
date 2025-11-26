<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\MarkdownRenderer;

use EICC\StaticForge\Features\MarkdownRenderer\PathGenerator;
use EICC\Utils\Container;
use PHPUnit\Framework\TestCase;

class PathGeneratorTest extends TestCase
{
    private PathGenerator $generator;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new PathGenerator();
        $this->container = new Container();
    }

    public function testGenerateOutputPathRelative(): void
    {
        $this->container->setVariable('SOURCE_DIR', '/src');
        $this->container->setVariable('OUTPUT_DIR', '/out');

        $path = $this->generator->generateOutputPath('/src/folder/file.md', $this->container);
        $this->assertEquals('/out/folder/file.html', $path);
    }

    public function testGenerateOutputPathFallback(): void
    {
        $this->container->setVariable('SOURCE_DIR', '/src');
        $this->container->setVariable('OUTPUT_DIR', '/out');

        $path = $this->generator->generateOutputPath('/other/file.md', $this->container);
        $this->assertEquals('/out/file.html', $path);
    }

    public function testGenerateOutputPathMissingSourceDir(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->generator->generateOutputPath('file.md', $this->container);
    }
}
