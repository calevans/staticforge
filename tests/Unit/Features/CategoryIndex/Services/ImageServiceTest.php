<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\CategoryIndex\Services;

use EICC\StaticForge\Features\CategoryIndex\Services\ImageService;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;

class ImageServiceTest extends UnitTestCase
{
    private ImageService $service;
    private Log $logger;
    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(Log::class);
        $this->service = new ImageService($this->logger);

        $this->outputDir = sys_get_temp_dir() . '/staticforge_imageservice_' . uniqid();
        mkdir($this->outputDir, 0755, true);
        $this->setContainerVariable('OUTPUT_DIR', $this->outputDir);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->outputDir);
    }

    public function testExtractHeroImageFromHeroMetadata(): void
    {
        $metadata = ['hero' => '/images/hero.jpg'];

        $result = $this->service->extractHeroImage('<p>no image here</p>', $metadata, '/src/post.md', $this->container);

        $this->assertEquals('/images/hero.jpg', $result);
    }

    public function testExtractHeroImageFromSocialImageMetadata(): void
    {
        $metadata = ['social' => ['image' => '/images/social.jpg']];

        $result = $this->service->extractHeroImage('<p>none</p>', $metadata, '/src/post.md', $this->container);

        $this->assertEquals('/images/social.jpg', $result);
    }

    public function testExtractHeroImageFromGenericImageMetadata(): void
    {
        $metadata = ['image' => '/images/generic.jpg'];

        $result = $this->service->extractHeroImage('<p>none</p>', $metadata, '/src/post.md', $this->container);

        $this->assertEquals('/images/generic.jpg', $result);
    }

    public function testExtractHeroImagePrefersHeroOverSocialAndGeneric(): void
    {
        $metadata = [
            'hero' => '/images/hero.jpg',
            'social' => ['image' => '/images/social.jpg'],
            'image' => '/images/generic.jpg',
        ];

        $result = $this->service->extractHeroImage('<p>none</p>', $metadata, '/src/post.md', $this->container);

        $this->assertEquals('/images/hero.jpg', $result);
    }

    public function testExtractHeroImageReturnsRemoteUrlFromHtml(): void
    {
        $html = '<p>Text <img src="https://cdn.example.com/pic.jpg" alt="x"></p>';

        $result = $this->service->extractHeroImage($html, [], '/src/post.md', $this->container);

        $this->assertEquals('https://cdn.example.com/pic.jpg', $result);
    }

    public function testExtractHeroImageReturnsLocalImageSrcWhenFileMissing(): void
    {
        $html = '<p><img src="/images/missing.jpg"></p>';

        $result = $this->service->extractHeroImage($html, [], '/src/post.md', $this->container);

        // File doesn't exist on disk under OUTPUT_DIR, so falls back to returning src as-is
        $this->assertEquals('/images/missing.jpg', $result);
    }

    public function testExtractHeroImageGeneratesThumbnailWhenLocalImageExists(): void
    {
        mkdir($this->outputDir . '/images', 0755, true);
        $imagePath = $this->outputDir . '/images/photo.jpg';

        // Create a minimal valid JPEG using GD so Imagick can read it
        $im = imagecreatetruecolor(10, 10);
        imagejpeg($im, $imagePath);
        imagedestroy($im);

        $html = '<p><img src="/images/photo.jpg"></p>';

        $result = $this->service->extractHeroImage($html, [], '/src/post.md', $this->container);

        $this->assertNotNull($result);
        $this->assertStringStartsWith('/images/', $result);
        $this->assertFileExists($this->outputDir . $result);
    }

    public function testExtractHeroImageReturnsNullWhenNoImageFound(): void
    {
        $result = $this->service->extractHeroImage('<p>No images here at all.</p>', [], '/src/post.md', $this->container);

        $this->assertNull($result);
    }

    public function testExtractHeroImageIgnoresNonStringHeroValue(): void
    {
        // hero is not a string -> should be skipped, falls through to no-image case
        $metadata = ['hero' => ['not', 'a', 'string']];

        $result = $this->service->extractHeroImage('<p>no img</p>', $metadata, '/src/post.md', $this->container);

        $this->assertNull($result);
    }
}
