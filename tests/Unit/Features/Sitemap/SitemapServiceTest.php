<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Sitemap;

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
        $this->service = new SitemapService($logger);
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

    public function testCollectUrl(): void
    {
        $parameters = [
            'output_path' => $this->tempDir . '/foo/bar.html',
            'metadata' => [
                'date' => '2023-01-01'
            ]
        ];

        $result = $this->service->collectUrl($this->container, $parameters);

        // Should return parameters unchanged
        $this->assertEquals($parameters, $result);
    }

    public function testCollectUrlSkipsIfNoOutputPath(): void
    {
        $parameters = [
            'metadata' => []
        ];

        $result = $this->service->collectUrl($this->container, $parameters);
        $this->assertEquals($parameters, $result);
    }

    public function testGenerateSitemap(): void
    {
        // Collect a URL first
        $parameters = [
            'output_path' => $this->tempDir . '/foo/bar.html',
            'metadata' => [
                'date' => '2023-01-01'
            ]
        ];
        $this->service->collectUrl($this->container, $parameters);

        // Generate sitemap
        $this->service->generateSitemap($this->container, []);

        $sitemapPath = $this->tempDir . '/sitemap.xml';
        $this->assertFileExists($sitemapPath);

        $content = file_get_contents($sitemapPath);
        $this->assertStringContainsString('<loc>https://example.com/foo/bar.html</loc>', $content);
        $this->assertStringContainsString('<lastmod>2023-01-01</lastmod>', $content);
    }

    public function testGenerateSitemapSkipsIfNoUrls(): void
    {
        $this->service->generateSitemap($this->container, []);
        $sitemapPath = $this->tempDir . '/sitemap.xml';
        $this->assertFileDoesNotExist($sitemapPath);
    }
}
