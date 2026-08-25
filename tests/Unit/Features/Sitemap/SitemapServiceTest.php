<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Sitemap;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\OutputWriter;
use EICC\StaticForge\Features\Sitemap\Services\SitemapService;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;

class SitemapServiceTest extends UnitTestCase
{
    private SitemapService $service;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temp directory for tests
        $this->tempDir = sys_get_temp_dir() . '/staticforge_sitemap_service_test_' . uniqid('', true) . '_' . getmypid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }

        // Setup container
        $this->setContainerVariable('OUTPUT_DIR', $this->tempDir);
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');

        $logger = $this->createMock(Log::class);
        $this->service = new SitemapService($logger, $this->container->get(OutputWriter::class), $this->container);
    }

    private function readFile(string $path): string
    {
        $content = file_get_contents($path);
        $this->assertNotFalse($content, "Failed to read file: {$path}");
        return $content;
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->recursiveRemoveDirectory($this->tempDir);
        }
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function makeEvent(?string $outputPath, array $metadata = []): RenderEvent
    {
        return new RenderEvent(
            name: 'POST_RENDER',
            filePath: '',
            fileUrl: '',
            metadata: $metadata,
            outputPath: $outputPath,
        );
    }

    public function testCollectUrl(): void
    {
        $event = $this->makeEvent($this->tempDir . '/foo/bar.html', ['date' => '2023-01-01']);

        $this->service->collectUrl($event);

        $this->service->generateSitemap();
        $content = $this->readFile($this->tempDir . '/sitemap.xml');
        $this->assertStringContainsString('<loc>https://example.com/foo/bar.html</loc>', $content);
    }

    public function testCollectUrlSkipsIfNoOutputPath(): void
    {
        $event = $this->makeEvent(null);

        $this->service->collectUrl($event);
        $this->service->generateSitemap();

        $this->assertFileDoesNotExist($this->tempDir . '/sitemap.xml');
    }

    public function testGenerateSitemap(): void
    {
        // Collect a URL first
        $event = $this->makeEvent($this->tempDir . '/foo/bar.html', ['date' => '2023-01-01']);
        $this->service->collectUrl($event);

        // Generate sitemap
        $this->service->generateSitemap();

        $sitemapPath = $this->tempDir . '/sitemap.xml';
        $this->assertFileExists($sitemapPath);

        $content = $this->readFile($sitemapPath);
        $this->assertStringContainsString('<loc>https://example.com/foo/bar.html</loc>', $content);
        $this->assertStringContainsString('<lastmod>2023-01-01</lastmod>', $content);
    }

    public function testGenerateSitemapSkipsIfNoUrls(): void
    {
        $this->service->generateSitemap();
        $sitemapPath = $this->tempDir . '/sitemap.xml';
        $this->assertFileDoesNotExist($sitemapPath);
    }

    public function testCollectUrlRootIndexHtmlProducesTrailingSlash(): void
    {
        $event = $this->makeEvent($this->tempDir . '/index.html', ['date' => '2024-01-15']);
        $this->service->collectUrl($event);
        $this->service->generateSitemap();

        $content = $this->readFile($this->tempDir . '/sitemap.xml');
        $this->assertStringContainsString('<loc>https://example.com/</loc>', $content);
    }

    public function testCollectUrlSubdirectoryIndexHtmlProducesDirectoryUrl(): void
    {
        $event = $this->makeEvent($this->tempDir . '/podcast/index.html', ['date' => '2024-02-01']);
        $this->service->collectUrl($event);
        $this->service->generateSitemap();

        $content = $this->readFile($this->tempDir . '/sitemap.xml');
        $this->assertStringContainsString('<loc>https://example.com/podcast/</loc>', $content);
    }

    public function testCollectUrlNestedIndexHtmlProducesNestedDirectoryUrl(): void
    {
        $event = $this->makeEvent($this->tempDir . '/a/b/index.html', ['date' => '2024-03-10']);
        $this->service->collectUrl($event);
        $this->service->generateSitemap();

        $content = $this->readFile($this->tempDir . '/sitemap.xml');
        $this->assertStringContainsString('<loc>https://example.com/a/b/</loc>', $content);
    }

    public function testCollectUrlRegularHtmlFileIsUnchanged(): void
    {
        $event = $this->makeEvent($this->tempDir . '/guide/content-creation.html', ['date' => '2024-04-20']);
        $this->service->collectUrl($event);
        $this->service->generateSitemap();

        $content = $this->readFile($this->tempDir . '/sitemap.xml');
        $this->assertStringContainsString(
            '<loc>https://example.com/guide/content-creation.html</loc>',
            $content
        );
    }
}
