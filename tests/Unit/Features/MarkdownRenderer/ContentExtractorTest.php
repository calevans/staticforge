<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\MarkdownRenderer;

use EICC\StaticForge\Features\MarkdownRenderer\ContentExtractor;
use PHPUnit\Framework\TestCase;

class ContentExtractorTest extends TestCase
{
    private ContentExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new ContentExtractor();
    }

    public function testExtractMarkdownContentWithFrontmatter(): void
    {
        $content = "---\ntitle: Test\n---\n# Content";
        $markdown = $this->extractor->extractMarkdownContent($content);
        $this->assertEquals('# Content', $markdown);
    }

    public function testExtractMarkdownContentWithoutFrontmatter(): void
    {
        $content = "# Content";
        $markdown = $this->extractor->extractMarkdownContent($content);
        $this->assertEquals('# Content', $markdown);
    }

    public function testExtractTitleFromContent(): void
    {
        $html = '<h1>My Title</h1><p>Content</p>';
        $title = $this->extractor->extractTitleFromContent($html);
        $this->assertEquals('My Title', $title);
    }

    public function testExtractTitleFromContentNoHeading(): void
    {
        $html = '<p>Content</p>';
        $title = $this->extractor->extractTitleFromContent($html);
        $this->assertEquals('Untitled', $title);
    }
}
