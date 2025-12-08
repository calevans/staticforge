<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\MarkdownRenderer\Services;

use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\MarkdownRenderer\ContentExtractor;
use EICC\StaticForge\Features\MarkdownRenderer\MarkdownProcessor;
use EICC\StaticForge\Features\MarkdownRenderer\Services\MarkdownRendererService;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Container;
use EICC\Utils\Log;

class MarkdownRendererServiceTest extends UnitTestCase
{
    private MarkdownRendererService $service;
    private MarkdownProcessor $markdownProcessor;
    private ContentExtractor $contentExtractor;
    private TemplateRenderer $templateRenderer;
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a fresh container to avoid conflicts with bootstrapped services
        $this->container = new Container();
        $this->container->add('logger', $this->createMock(Log::class));

        $this->markdownProcessor = $this->createMock(MarkdownProcessor::class);
        $this->contentExtractor = $this->createMock(ContentExtractor::class);
        $this->templateRenderer = $this->createMock(TemplateRenderer::class);
        $this->eventManager = $this->createMock(EventManager::class);

        $this->container->add(EventManager::class, $this->eventManager);

        $this->service = new MarkdownRendererService(
            $this->container->get('logger'),
            $this->markdownProcessor,
            $this->contentExtractor,
            $this->templateRenderer
        );
    }

    public function testProcessMarkdownFile(): void
    {
        $filePath = '/tmp/test.md';
        $content = '# Hello';
        $htmlContent = '<h1>Hello</h1>';
        $metadata = ['title' => 'Hello'];
        $renderedContent = '<html><h1>Hello</h1></html>';
        $expectedContent = "<html>\n    <h1>\n        Hello\n    </h1>\n</html>";

        // Mock file reading (using parameters to bypass file_get_contents)
        $parameters = [
            'file_path' => $filePath,
            'file_content' => $content,
            'file_metadata' => []
        ];

        // Mock ContentExtractor
        $this->contentExtractor->expects($this->once())
            ->method('extractMarkdownContent')
            ->with($content)
            ->willReturn($content);

        // Mock MarkdownProcessor
        $this->markdownProcessor->expects($this->once())
            ->method('convert')
            ->with($content)
            ->willReturn($htmlContent);

        // Mock EventManager
        $this->eventManager->expects($this->once())
            ->method('fire')
            ->with('MARKDOWN_CONVERTED', [
                'html_content' => $htmlContent,
                'metadata' => [],
                'file_path' => $filePath
            ])
            ->willReturn([
                'html_content' => $htmlContent,
                'metadata' => $metadata
            ]);

        // Mock TemplateRenderer
        $this->templateRenderer->expects($this->once())
            ->method('render')
            ->willReturn($renderedContent);

        // Mock Container for output path generation
        $this->setContainerVariable('SOURCE_DIR', '/tmp');
        $this->setContainerVariable('OUTPUT_DIR', '/tmp/output');

        $result = $this->service->processMarkdownFile($this->container, $parameters);

        $this->assertArrayHasKey('rendered_content', $result);
        $this->assertEquals($expectedContent, $result['rendered_content']);
        $this->assertArrayHasKey('output_path', $result);
        $this->assertEquals('/tmp/output/test.html', $result['output_path']);
    }
}
