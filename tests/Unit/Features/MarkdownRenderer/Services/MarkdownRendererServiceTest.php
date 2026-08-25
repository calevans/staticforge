<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\MarkdownRenderer\Services;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\MarkdownRenderer\ContentExtractor;
use EICC\StaticForge\Features\MarkdownRenderer\MarkdownProcessor;
use EICC\StaticForge\Features\MarkdownRenderer\Services\MarkdownRendererService;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Container;
use EICC\Utils\Log;
use PHPUnit\Framework\MockObject\MockObject;

class MarkdownRendererServiceTest extends UnitTestCase
{
    private MarkdownRendererService $service;
    private MarkdownProcessor&MockObject $markdownProcessor;
    private ContentExtractor&MockObject $contentExtractor;
    private TemplateRenderer&MockObject $templateRenderer;
    private EventManager&MockObject $eventManager;

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
            $this->templateRenderer,
            $this->eventManager,
            $this->container
        );
    }

    public function testProcessMarkdownFile(): void
    {
        $filePath = '/tmp/test.md';
        $content = '# Hello';
        $htmlContent = '<h1>Hello</h1>';
        $renderedContent = '<html><h1>Hello</h1></html>';
        $expectedContent = "<html>\n    <h1>\n        Hello\n    </h1>\n</html>";

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

        // Mock EventManager: mutate the sub-event's metadata as a real listener would
        $this->eventManager->expects($this->once())
            ->method('fire')
            ->with('MARKDOWN_CONVERTED', $this->isInstanceOf(RenderEvent::class))
            ->willReturnCallback(function (string $name, RenderEvent $event) {
                $event->metadata['title'] = 'Hello';
                return $event;
            });

        // Mock TemplateRenderer
        $this->templateRenderer->expects($this->once())
            ->method('render')
            ->willReturn($renderedContent);

        // Mock Container for output path generation
        $this->setContainerVariable('SOURCE_DIR', '/tmp');
        $this->setContainerVariable('OUTPUT_DIR', '/tmp/output');

        $event = new RenderEvent(
            name: 'RENDER',
            filePath: $filePath,
            fileUrl: '/test/',
            metadata: [],
            extra: ['file_content' => $content],
        );

        $this->service->processMarkdownFile($event);

        $this->assertSame($expectedContent, $event->renderedContent);
        $this->assertSame('/tmp/output/test.html', $event->outputPath);
    }
}
