<?php

namespace EICC\StaticForge\Tests\Unit\Features\TableOfContents;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\TableOfContents\Services\TableOfContentsService;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;

class TableOfContentsServiceTest extends UnitTestCase
{
    private TableOfContentsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $logger = $this->createMock(Log::class);
        $this->service = new TableOfContentsService($logger);
    }

    private function makeEvent(string $htmlContent, string $filePath = 'test.md'): RenderEvent
    {
        return new RenderEvent(
            name: 'MARKDOWN_CONVERTED',
            filePath: $filePath,
            fileUrl: '',
            metadata: [],
            renderedContent: $htmlContent,
        );
    }

    public function testHandleMarkdownConvertedGeneratesToc(): void
    {
        $htmlContent = <<<HTML
<h1>Main Title</h1>
<p>Intro</p>
<h2 id="section-1">Section 1</h2>
<p>Content 1</p>
<h3 id="subsection-1-1">Subsection 1.1</h3>
<p>Content 1.1</p>
<h2 id="section-2">Section 2</h2>
<p>Content 2</p>
HTML;

        $event = $this->makeEvent($htmlContent);
        $this->service->handleMarkdownConverted($event);

        $this->assertArrayHasKey('toc', $event->metadata);

        $toc = $event->metadata['toc'];

        // Check structure
        $this->assertStringContainsString('<ul class="toc-list">', $toc);
        $this->assertStringContainsString('<li><a href="#section-1">Section 1</a></li>', $toc);
        $this->assertStringContainsString('<li><a href="#subsection-1-1">Subsection 1.1</a></li>', $toc);
        $this->assertStringContainsString('<li><a href="#section-2">Section 2</a></li>', $toc);

        // Check nesting
        $this->assertStringContainsString('<ul>', $toc); // Nested list for h3
    }

    public function testHandleMarkdownConvertedWithPermalinks(): void
    {
        // Simulate output from HeadingPermalinkExtension
        $htmlContent = <<<HTML
<h1>Main Title</h1>
<h2>Section 1<a id="content-section-1" href="#content-section-1" class="heading-permalink" aria-hidden="true" title="Permalink"></a></h2>
<h3>Subsection 1.1<a id="content-subsection-1-1" href="#content-subsection-1-1" class="heading-permalink" aria-hidden="true" title="Permalink"></a></h3>
HTML;

        $event = $this->makeEvent($htmlContent);
        $this->service->handleMarkdownConverted($event);
        $toc = $event->metadata['toc'];

        // Should use the permalink ID
        $this->assertStringContainsString('href="#content-section-1"', $toc);
        $this->assertStringContainsString('href="#content-subsection-1-1"', $toc);

        // Should strip the anchor text from the link text
        $this->assertStringContainsString('>Section 1<', $toc);
        $this->assertStringNotContainsString('Permalink', $toc);
    }

    public function testHandleMarkdownConvertedNoHeadings(): void
    {
        $event = $this->makeEvent('<p>Just text</p>');
        $this->service->handleMarkdownConverted($event);
        $this->assertEmpty($event->metadata['toc']);
    }
}
