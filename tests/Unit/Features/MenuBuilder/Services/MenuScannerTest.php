<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\MenuBuilder\Services;

use EICC\StaticForge\Features\MenuBuilder\Services\MenuScanner;
use EICC\StaticForge\Features\MenuBuilder\Services\MenuStructureBuilder;
use PHPUnit\Framework\TestCase;

class MenuScannerTest extends TestCase
{
    private MenuScanner $scanner;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->scanner = new MenuScanner(new MenuStructureBuilder());
        $this->tmpDir = sys_get_temp_dir() . '/staticforge_menuscanner_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir . '/*') ?: [];
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
    }

    public function testScanFilesForMenusSkipsFilesWithoutMenuMetadata(): void
    {
        $discoveredFiles = [
            ['path' => '/content/page.md', 'url' => '/page/', 'metadata' => ['title' => 'Page']],
        ];

        $result = $this->scanner->scanFilesForMenus($discoveredFiles);

        $this->assertEquals([], $result);
    }

    public function testScanFilesForMenusUsesMetadataTitle(): void
    {
        $discoveredFiles = [
            ['path' => '/content/page.md', 'url' => '/page/', 'metadata' => ['title' => 'My Page', 'menu' => '1']],
        ];

        $result = $this->scanner->scanFilesForMenus($discoveredFiles);

        $this->assertEquals('My Page', $result[1]['direct'][0]['title']);
        $this->assertEquals('/page/', $result[1]['direct'][0]['url']);
    }

    public function testScanFilesForMenusHandlesMultiplePositions(): void
    {
        $discoveredFiles = [
            ['path' => '/content/page.md', 'url' => '/page/', 'metadata' => ['title' => 'Multi', 'menu' => '1, 2']],
        ];

        $result = $this->scanner->scanFilesForMenus($discoveredFiles);

        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
    }

    public function testExtractTitleFromFileFallsBackToFilenameWhenUnreadable(): void
    {
        $filePath = $this->tmpDir . '/does-not-exist.md';

        $discoveredFiles = [
            ['path' => $filePath, 'url' => '/x/', 'metadata' => ['menu' => '1']],
        ];

        $result = $this->scanner->scanFilesForMenus($discoveredFiles);

        $this->assertEquals('Does-not-exist', $result[1]['direct'][0]['title']);
    }

    public function testExtractTitleFromMarkdownFrontmatter(): void
    {
        $filePath = $this->tmpDir . '/article.md';
        file_put_contents($filePath, "---\ntitle: Frontmatter Title\n---\n# Heading\n");

        $discoveredFiles = [
            ['path' => $filePath, 'url' => '/article/', 'metadata' => ['menu' => '1']],
        ];

        $result = $this->scanner->scanFilesForMenus($discoveredFiles);

        $this->assertEquals('Frontmatter Title', $result[1]['direct'][0]['title']);
    }

    public function testExtractTitleFromMarkdownFirstHeadingWhenNoFrontmatterTitle(): void
    {
        $filePath = $this->tmpDir . '/article2.md';
        file_put_contents($filePath, "# Heading Title\n\nBody text.\n");

        $discoveredFiles = [
            ['path' => $filePath, 'url' => '/article2/', 'metadata' => ['menu' => '1']],
        ];

        $result = $this->scanner->scanFilesForMenus($discoveredFiles);

        $this->assertEquals('Heading Title', $result[1]['direct'][0]['title']);
    }

    public function testExtractTitleFromHtmlIniBlock(): void
    {
        $filePath = $this->tmpDir . '/page.html';
        file_put_contents($filePath, "<!-- INI\ntitle: INI Title\n-->\n<h1>Hi</h1>");

        $discoveredFiles = [
            ['path' => $filePath, 'url' => '/page/', 'metadata' => ['menu' => '1']],
        ];

        $result = $this->scanner->scanFilesForMenus($discoveredFiles);

        $this->assertEquals('INI Title', $result[1]['direct'][0]['title']);
    }

    public function testExtractTitleFromHtmlTitleTag(): void
    {
        $filePath = $this->tmpDir . '/page2.html';
        file_put_contents($filePath, "<html><head><title>Tag Title</title></head><body></body></html>");

        $discoveredFiles = [
            ['path' => $filePath, 'url' => '/page2/', 'metadata' => ['menu' => '1']],
        ];

        $result = $this->scanner->scanFilesForMenus($discoveredFiles);

        $this->assertEquals('Tag Title', $result[1]['direct'][0]['title']);
    }

    public function testExtractTitleFromHtmlH1WhenNoTitleTag(): void
    {
        $filePath = $this->tmpDir . '/page3.html';
        file_put_contents($filePath, "<html><body><h1>H1 Title</h1></body></html>");

        $discoveredFiles = [
            ['path' => $filePath, 'url' => '/page3/', 'metadata' => ['menu' => '1']],
        ];

        $result = $this->scanner->scanFilesForMenus($discoveredFiles);

        $this->assertEquals('H1 Title', $result[1]['direct'][0]['title']);
    }

    public function testExtractTitleFallsBackToFilenameForUnknownExtension(): void
    {
        $filePath = $this->tmpDir . '/data.txt';
        file_put_contents($filePath, "irrelevant content");

        $discoveredFiles = [
            ['path' => $filePath, 'url' => '/data/', 'metadata' => ['menu' => '1']],
        ];

        $result = $this->scanner->scanFilesForMenus($discoveredFiles);

        $this->assertEquals('Data', $result[1]['direct'][0]['title']);
    }
}
