<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features;

use EICC\StaticForge\Features\Sitemap\Feature;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class SitemapFeatureTest extends UnitTestCase
{
    private Feature $feature;
    private EventManager $eventManager;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temp directory for tests
        $this->tempDir = sys_get_temp_dir() . '/staticforge_sitemap_test_' . uniqid('', true) . '_' . getmypid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }

        // Setup container
        $this->setContainerVariable('OUTPUT_DIR', $this->tempDir);
        $this->setContainerVariable('SITE_URL', 'https://example.com');

        $this->eventManager = new EventManager($this->container);

        $this->feature = new Feature();
        $this->feature->register($this->eventManager, $this->container);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->recursiveRemoveDirectory($this->tempDir);
        }
    }

    public function testRegisterRegistersEvent(): void
    {
        $listeners = $this->eventManager->getListeners('POST_RENDER');
        $this->assertNotEmpty($listeners);
        $this->assertEquals([$this->feature, 'collectUrl'], $listeners[0]['callback']);

        $listeners = $this->eventManager->getListeners('POST_LOOP');
        $this->assertNotEmpty($listeners);
        $this->assertEquals([$this->feature, 'generateSitemap'], $listeners[0]['callback']);
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

    public function testFeatureRegistration(): void
    {
        $this->assertInstanceOf(Feature::class, $this->feature);
        $this->assertEquals('Sitemap', $this->feature->getName());

        $listeners = $this->feature->getEventListeners();
        $this->assertArrayHasKey('POST_RENDER', $listeners);
        $this->assertArrayHasKey('POST_LOOP', $listeners);
    }

    public function testCollectUrl(): void
    {
        $parameters = [
            'output_path' => $this->tempDir . '/foo/bar.html',
            'metadata' => [
                'date' => '2023-01-01'
            ]
        ];

        $result = $this->feature->collectUrl($this->container, $parameters);

        // Should return parameters unchanged
        $this->assertEquals($parameters, $result);
    }

    public function testCollectUrlSkipsIfNoOutputPath(): void
    {
        $parameters = [
            'metadata' => []
        ];

        $result = $this->feature->collectUrl($this->container, $parameters);
        $this->assertEquals($parameters, $result);
    }

    public function testGenerateSitemap(): void
    {
        // Collect a URL
        $this->feature->collectUrl($this->container, [
            'output_path' => $this->tempDir . '/page1.html',
            'metadata' => ['date' => '2023-01-01']
        ]);

        // Collect another URL with no date (should use today)
        $this->feature->collectUrl($this->container, [
            'output_path' => $this->tempDir . '/page2.html',
            'metadata' => []
        ]);

        // Generate sitemap
        $this->feature->generateSitemap($this->container, []);

        $sitemapPath = $this->tempDir . '/sitemap.xml';
        $this->assertFileExists($sitemapPath);

        $content = file_get_contents($sitemapPath);

        // Check XML structure
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $content);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $content);

        // Check URLs
        $this->assertStringContainsString('<loc>https://example.com/page1.html</loc>', $content);
        $this->assertStringContainsString('<lastmod>2023-01-01</lastmod>', $content);

        $this->assertStringContainsString('<loc>https://example.com/page2.html</loc>', $content);
        // Check that page2 has a date (today's date)
        $this->assertStringContainsString('<lastmod>' . date('Y-m-d') . '</lastmod>', $content);
    }
}
