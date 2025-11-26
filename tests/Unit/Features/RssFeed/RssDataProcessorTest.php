<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\RssFeed;

use EICC\StaticForge\Features\RssFeed\RssDataProcessor;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class RssDataProcessorTest extends UnitTestCase
{
    private RssDataProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new RssDataProcessor();
    }

    public function testSanitizeCategoryName(): void
    {
        $this->assertEquals('tech', $this->processor->sanitizeCategoryName('Tech'));
        $this->assertEquals('web-development', $this->processor->sanitizeCategoryName('Web Development'));
        $this->assertEquals('c', $this->processor->sanitizeCategoryName('C#'));
        $this->assertEquals('category', $this->processor->sanitizeCategoryName(''));
    }

    public function testExtractDescriptionFromMetadata(): void
    {
        $metadata = ['description' => 'Metadata description'];
        $html = '<p>Content description</p>';

        $this->assertEquals('Metadata description', $this->processor->extractDescription($html, $metadata));
    }

    public function testExtractDescriptionFromContent(): void
    {
        $metadata = [];
        $html = '<p>Content description</p>';

        $this->assertEquals('Content description', $this->processor->extractDescription($html, $metadata));
    }

    public function testExtractDescriptionTruncatesLongContent(): void
    {
        $metadata = [];
        $longContent = str_repeat('word ', 50); // > 200 chars
        $html = "<p>$longContent</p>";

        $description = $this->processor->extractDescription($html, $metadata);

        $this->assertLessThanOrEqual(203, strlen($description)); // 200 + '...'
        $this->assertStringEndsWith('...', $description);
    }

    public function testGetFileDateFromPublishedDate(): void
    {
        $metadata = ['published_date' => '2023-01-01'];
        $this->assertEquals('2023-01-01', $this->processor->getFileDate($metadata, ''));
    }

    public function testGetFileDateFromDate(): void
    {
        $metadata = ['date' => '2023-02-01'];
        $this->assertEquals('2023-02-01', $this->processor->getFileDate($metadata, ''));
    }

    public function testGetFileUrl(): void
    {
        $outputDir = '/var/www/html/output';
        $outputPath = '/var/www/html/output/blog/post.html';

        $this->assertEquals('/blog/post.html', $this->processor->getFileUrl($outputPath, $outputDir));
    }
}
