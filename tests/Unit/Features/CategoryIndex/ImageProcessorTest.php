<?php

namespace EICC\StaticForge\Tests\Unit\Features\CategoryIndex;

use EICC\StaticForge\Features\CategoryIndex\ImageProcessor;
use EICC\Utils\Container;
use EICC\Utils\Log;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class ImageProcessorTest extends UnitTestCase
{
    private ImageProcessor $processor;
    private Log $logger;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/staticforge_img_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        mkdir($this->tempDir . '/images', 0755, true);
        mkdir($this->tempDir . '/templates/terminal', 0755, true);

        $this->logger = $this->createMock(Log::class);
        $this->processor = new ImageProcessor($this->logger);

        $this->setContainerVariable('OUTPUT_DIR', $this->tempDir);
        $this->setContainerVariable('TEMPLATE_DIR', $this->tempDir . '/templates');
        $this->setContainerVariable('TEMPLATE', 'terminal');
    }

    protected function tearDown(): void
    {
        $this->recursiveRemoveDirectory($this->tempDir);
        parent::tearDown();
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testExtractHeroImageFromHtmlWithLocalImage(): void
    {
        // Create a dummy source image
        $imagePath = $this->tempDir . '/test.jpg';
        $this->createDummyImage($imagePath);

        $html = '<p>Some text</p><img src="/test.jpg" alt="Test"><p>More text</p>';

        $result = $this->processor->extractHeroImageFromHtml($html, 'source.md', $this->container);

        $this->assertEquals('/images/source.jpg', $result);
        $this->assertFileExists($this->tempDir . '/images/source.jpg');
    }

    public function testExtractHeroImageFromHtmlWithNoImage(): void
    {
        $html = '<p>No images here</p>';

        $result = $this->processor->extractHeroImageFromHtml($html, 'source.md', $this->container);

        $this->assertEquals('/templates/terminal/placeholder.jpg', $result);
        // Should generate placeholder
        $this->assertFileExists($this->tempDir . '/templates/terminal/placeholder.jpg');
    }

    public function testExtractHeroImageFromHtmlWithExternalImage(): void
    {
        // We mock file_get_contents by creating a local file and using file:// protocol if possible,
        // but the regex checks for http/https.
        // For unit tests, we might want to avoid actual network calls.
        // However, the class uses @file_get_contents($url).
        // We can skip the actual download test or try to mock it if we refactor,
        // but for now let's test the fallback or valid behavior if we can.

        // Since we can't easily mock the network call without refactoring the class to use a downloader service,
        // we will test that it attempts to download and handles failure gracefully (logging error).

        $html = '<img src="https://example.com/fake-image.jpg">';

        // It will likely fail to download and return placeholder
        $result = $this->processor->extractHeroImageFromHtml($html, 'source.md', $this->container);

        // Expect placeholder on failure
        $this->assertEquals('/templates/terminal/placeholder.jpg', $result);
    }

    private function createDummyImage(string $path): void
    {
        if (class_exists(\Imagick::class)) {
            $imagick = new \Imagick();
            $imagick->newImage(100, 100, new \ImagickPixel('red'));
            $imagick->setImageFormat('jpeg');
            $imagick->writeImage($path);
            $imagick->clear();
        } else {
            // Fallback for environments without Imagick (though project requires it)
            file_put_contents($path, 'fake image content');
        }
    }
}
