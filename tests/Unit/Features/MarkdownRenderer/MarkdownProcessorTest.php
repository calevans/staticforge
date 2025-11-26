<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\MarkdownRenderer;

use EICC\StaticForge\Features\MarkdownRenderer\MarkdownProcessor;
use PHPUnit\Framework\TestCase;

class MarkdownProcessorTest extends TestCase
{
    private MarkdownProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new MarkdownProcessor();
    }

    public function testConvertBasicMarkdown(): void
    {
        $markdown = '# Hello World';
        $html = $this->processor->convert($markdown);
        $this->assertStringContainsString('<h1>Hello World', $html);
    }

    public function testConvertTable(): void
    {
        $markdown = "| Header 1 | Header 2 |\n| --- | --- |\n| Cell 1 | Cell 2 |";
        $html = $this->processor->convert($markdown);
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<th>Header 1</th>', $html);
    }

    public function testHeadingPermalink(): void
    {
        $markdown = '# My Heading';
        $html = $this->processor->convert($markdown);
        // Check for the anchor link (empty symbol)
        $this->assertStringContainsString('href="#content-my-heading"', $html);
    }
}
