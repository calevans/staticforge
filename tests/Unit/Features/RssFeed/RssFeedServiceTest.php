<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\RssFeed;

use EICC\StaticForge\Features\RssFeed\Services\RssFeedService;
use EICC\StaticForge\Features\RssFeed\Services\PodcastMediaService;
use EICC\StaticForge\Services\MediaInspector;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use ReflectionMethod;

class RssFeedServiceTest extends UnitTestCase
{
    private RssFeedService $service;
    private PodcastMediaService $mediaService;

    protected function setUp(): void
    {
        parent::setUp();
        $logger = $this->createMock(Log::class);
        $this->mediaService = $this->createMock(PodcastMediaService::class);
        $this->service = new RssFeedService($logger, $this->mediaService);
    }

    public function testSanitizeCategoryName(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'sanitizeCategoryName');
        $method->setAccessible(true);

        $this->assertEquals('tech', $method->invoke($this->service, 'Tech'));
        $this->assertEquals('web-development', $method->invoke($this->service, 'Web Development'));
        $this->assertEquals('c', $method->invoke($this->service, 'C#'));
        $this->assertEquals('category', $method->invoke($this->service, ''));
    }

    public function testExtractDescriptionFromMetadata(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'extractDescription');
        $method->setAccessible(true);

        $metadata = ['description' => 'Metadata description'];
        $html = '<p>Content description</p>';

        $this->assertEquals('Metadata description', $method->invoke($this->service, $html, $metadata));
    }

    public function testExtractDescriptionFromContent(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'extractDescription');
        $method->setAccessible(true);

        $metadata = [];
        $html = '<p>Content description</p>';

        $this->assertEquals('Content description', $method->invoke($this->service, $html, $metadata));
    }

    public function testExtractDescriptionTruncatesLongContent(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'extractDescription');
        $method->setAccessible(true);

        $metadata = [];
        $longContent = str_repeat('word ', 50); // > 200 chars
        $html = "<p>$longContent</p>";

        $description = $method->invoke($this->service, $html, $metadata);

        $this->assertLessThanOrEqual(203, strlen($description)); // 200 + '...'
        $this->assertStringEndsWith('...', $description);
    }

    public function testGetFileDateFromPublishedDate(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'getFileDate');
        $method->setAccessible(true);

        $metadata = ['published_date' => '2023-01-01'];
        $this->assertEquals('2023-01-01', $method->invoke($this->service, $metadata, ''));
    }

    public function testGetFileDateFromDate(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'getFileDate');
        $method->setAccessible(true);

        $metadata = ['date' => '2023-02-01'];
        $this->assertEquals('2023-02-01', $method->invoke($this->service, $metadata, ''));
    }

    public function testGetFileUrl(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'getFileUrl');
        $method->setAccessible(true);

        $outputDir = '/var/www/html/output';
        $outputPath = '/var/www/html/output/blog/post.html';

        $this->assertEquals('/blog/post.html', $method->invoke($this->service, $outputPath, $outputDir));
    }
}
