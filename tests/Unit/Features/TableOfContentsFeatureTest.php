<?php

namespace EICC\StaticForge\Tests\Unit\Features;

use EICC\StaticForge\Features\TableOfContents\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\EventManager;

class TableOfContentsFeatureTest extends UnitTestCase
{
    private Feature $feature;
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventManager = new EventManager($this->container);
        $this->feature = new Feature();
        $this->feature->register($this->eventManager, $this->container);
    }

    public function testRegisterRegistersEvent(): void
    {
        $listeners = $this->eventManager->getListeners('MARKDOWN_CONVERTED');
        $this->assertNotEmpty($listeners);
        $this->assertCount(1, $listeners);
        $this->assertEquals([$this->feature, 'handleMarkdownConverted'], $listeners[0]['callback']);
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

        $parameters = [
            'html_content' => $htmlContent,
            'metadata' => [],
            'file_path' => 'test.md'
        ];

        $result = $this->feature->handleMarkdownConverted($this->container, $parameters);

        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('toc', $result['metadata']);

        $toc = $result['metadata']['toc'];

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

        $parameters = [
            'html_content' => $htmlContent,
            'metadata' => [],
            'file_path' => 'test.md'
        ];

        $result = $this->feature->handleMarkdownConverted($this->container, $parameters);
        $toc = $result['metadata']['toc'];

        // Should use the permalink ID
        $this->assertStringContainsString('href="#content-section-1"', $toc);
        $this->assertStringContainsString('href="#content-subsection-1-1"', $toc);

        // Should strip the anchor text from the link text
        $this->assertStringContainsString('>Section 1<', $toc);
        $this->assertStringNotContainsString('Permalink', $toc);
    }

    public function testHandleMarkdownConvertedNoHeadings(): void
    {
        $htmlContent = '<p>Just text</p>';

        $parameters = [
            'html_content' => $htmlContent,
            'metadata' => [],
            'file_path' => 'test.md'
        ];

        $result = $this->feature->handleMarkdownConverted($this->container, $parameters);

        $this->assertEmpty($result['metadata']['toc']);
    }

    public function testHandleMarkdownConvertedEmptyContent(): void
    {
        $parameters = [
            'html_content' => '',
            'metadata' => [],
            'file_path' => 'test.md'
        ];

        $result = $this->feature->handleMarkdownConverted($this->container, $parameters);

        $this->assertEquals($parameters, $result);
    }
}
