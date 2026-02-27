<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\HtmlRenderer\Services;

use EICC\StaticForge\Features\HtmlRenderer\Services\HtmlRendererService;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\Utils\Container;
use EICC\Utils\Log;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class HtmlRendererServiceTest extends TestCase
{
    private HtmlRendererService $service;
    private Log $logger;
    private TemplateRenderer $templateRenderer;
    private Container $container;
    private string $sourceDir;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(Log::class);
        $this->templateRenderer = $this->createMock(TemplateRenderer::class);
        $this->container = $this->createMock(Container::class);

        $this->service = new HtmlRendererService($this->logger, $this->templateRenderer);

        $this->sourceDir = sys_get_temp_dir() . '/staticforge_source_' . uniqid();
        $this->outputDir = sys_get_temp_dir() . '/staticforge_output_' . uniqid();
        mkdir($this->sourceDir, 0755, true);
        mkdir($this->outputDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->sourceDir);
        $this->removeDirectory($this->outputDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testExtractHtmlContentIni(): void
    {
        $content = "<!-- INI\ntitle=Test\n-->\n<h1>Hello</h1>";
        $this->assertEquals("<h1>Hello</h1>", $this->service->extractHtmlContent($content));
    }

    public function testExtractHtmlContentYaml(): void
    {
        $content = "<!-- \n---\ntitle: Test\n---\n-->\n<h1>Hello</h1>";
        $this->assertEquals("<h1>Hello</h1>", $this->service->extractHtmlContent($content));
    }

    public function testExtractHtmlContentNone(): void
    {
        $content = "<h1>Hello</h1>";
        $this->assertEquals("<h1>Hello</h1>", $this->service->extractHtmlContent($content));
    }

    public function testApplyDefaultMetadata(): void
    {
        $metadata = ['custom' => 'value'];
        $result = $this->service->applyDefaultMetadata($metadata);

        $this->assertEquals('base', $result['template']);
        $this->assertEquals('Untitled Page', $result['title']);
        $this->assertEquals('value', $result['custom']);
    }

    public function testGenerateOutputPath(): void
    {
        $this->container->method('getVariable')
            ->willReturnMap([
                ['SOURCE_DIR', '/app/content'],
                ['OUTPUT_DIR', '/app/public']
            ]);

        $inputPath = '/app/content/subdir/page.html';
        $expected = '/app/public/subdir/page.html';

        $this->assertEquals($expected, $this->service->generateOutputPath($inputPath, $this->container));
    }

    public function testProcessHtmlFile(): void
    {
        $filePath = $this->sourceDir . '/test.html';
        file_put_contents($filePath, '<h1>Test</h1>');

        $this->container->method('getVariable')
            ->willReturnMap([
                ['SOURCE_DIR', $this->sourceDir],
                ['OUTPUT_DIR', $this->outputDir]
            ]);

        $this->templateRenderer->expects($this->once())
            ->method('render')
            ->willReturn('<html><body><h1>Test</h1></body></html>');

        $parameters = [
            'file_path' => $filePath,
            'file_metadata' => []
        ];

        $result = $this->service->processHtmlFile($this->container, $parameters);

        $this->assertArrayHasKey('rendered_content', $result);
        $this->assertArrayHasKey('output_path', $result);
        // Check for content presence, ignoring whitespace introduced by beautifier
        $this->assertStringContainsString('Test', $result['rendered_content']);
        $this->assertStringContainsString('<h1>', $result['rendered_content']);
    }

    public function testProcessHtmlFileIgnoresNonHtml(): void
    {
        $parameters = ['file_path' => 'test.md'];
        $result = $this->service->processHtmlFile($this->container, $parameters);

        $this->assertEquals($parameters, $result);
    }
}
