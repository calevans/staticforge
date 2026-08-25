<?php

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\FileProcessor;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\ErrorHandler;
use EICC\StaticForge\Core\OutputWriter;
use EICC\Utils\Container;
use EICC\Utils\Log;

class FileProcessorTest extends UnitTestCase
{
    private FileProcessor $fileProcessor;
    private EventManager $eventManager;
    private ErrorHandler $errorHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventManager = new EventManager();
        // FileProcessor resolves ErrorHandler from the container internally, so we must
        // use the same bootstrapped instance here to observe its error statistics.
        $this->errorHandler = $this->container->get(ErrorHandler::class);

        $this->fileProcessor = new FileProcessor(
            $this->container,
            $this->eventManager,
            $this->container->get(OutputWriter::class)
        );
    }

    public function testProcessFilesWithNoFiles(): void
    {
        // No discovered_files in container
        $this->fileProcessor->processFiles();

        // Should complete without error and without firing any listeners
        $this->assertCount(0, $this->eventManager->getListeners('PRE_RENDER'));
    }

    public function testProcessFilesWithEmptyArray(): void
    {
        $this->setContainerVariable('discovered_files', []);

        $this->fileProcessor->processFiles();

        // Should complete without error and without firing any listeners
        $this->assertCount(0, $this->eventManager->getListeners('PRE_RENDER'));
    }

    public function testProcessFilesWithFiles(): void
    {
        $testFiles = [
            ['path' => '/tmp/test1.html', 'url' => 'test1.html', 'metadata' => []],
            ['path' => '/tmp/test2.html', 'url' => 'test2.html', 'metadata' => []]
        ];

        $this->setContainerVariable('discovered_files', $testFiles);

        // Track events fired
        $tracker = new EventTrackingListener($this->container);

        $this->eventManager->registerListener('PRE_RENDER', [$tracker, 'handlePreRender'], 100);
        $this->eventManager->registerListener('RENDER', [$tracker, 'handleRender'], 100);
        $this->eventManager->registerListener('POST_RENDER', [$tracker, 'handlePostRender'], 100);

        $this->fileProcessor->processFiles();

        // Should have fired 6 events (3 per file)
        $eventsTracked = $tracker->eventsTracked;
        $this->assertCount(6, $eventsTracked);
        $this->assertEquals('PRE_RENDER', $eventsTracked[0]);
        $this->assertEquals('RENDER', $eventsTracked[1]);
        $this->assertEquals('POST_RENDER', $eventsTracked[2]);
        $this->assertEquals('PRE_RENDER', $eventsTracked[3]);
        $this->assertEquals('RENDER', $eventsTracked[4]);
        $this->assertEquals('POST_RENDER', $eventsTracked[5]);
    }

    public function testProcessFileWithSkipFlag(): void
    {
        $testFiles = [['path' => '/tmp/test.html', 'url' => 'test.html', 'metadata' => []]];
        $this->setContainerVariable('discovered_files', $testFiles);

        $tracker = new EventTrackingListener($this->container);

        // Listener that sets skip_file flag in PRE_RENDER
        $this->eventManager->registerListener('PRE_RENDER', [$tracker, 'handlePreRenderAndSkip'], 100);
        $this->eventManager->registerListener('RENDER', [$tracker, 'handleRender'], 100);
        $this->eventManager->registerListener('POST_RENDER', [$tracker, 'handlePostRender'], 100);

        $this->fileProcessor->processFiles();

        // Should only have PRE_RENDER event, not RENDER or POST_RENDER
        $eventsTracked = $tracker->eventsTracked;
        $this->assertCount(1, $eventsTracked);
        $this->assertEquals('PRE_RENDER', $eventsTracked[0]);
    }

    public function testProcessFilesThrowsWhenOutputDirNotSet(): void
    {
        $this->container->removeVariable('OUTPUT_DIR');
        $this->setContainerVariable('discovered_files', [
            ['path' => '/tmp/test.html', 'url' => 'test.html', 'metadata' => []],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OUTPUT_DIR not set in container');

        $this->fileProcessor->processFiles();
    }

    public function testProcessFilesThrowsWhenSourceDirNotSet(): void
    {
        $this->container->removeVariable('SOURCE_DIR');
        $this->setContainerVariable('discovered_files', [
            ['path' => '/tmp/test.html', 'url' => 'test.html', 'metadata' => []],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SOURCE_DIR not set in container');

        $this->fileProcessor->processFiles();
    }

    public function testProcessFileWithNoRenderedOutputIsRecordedAsFileError(): void
    {
        $testFiles = [['path' => '/tmp/test.html', 'url' => 'test.html', 'metadata' => []]];
        $this->setContainerVariable('discovered_files', $testFiles);

        // No listeners registered at all, so RENDER never populates rendered_content/output_path.
        // processFile() should throw FileProcessingException internally, which processFiles()
        // catches and routes through ErrorHandler rather than propagating.
        $this->fileProcessor->processFiles();

        $stats = $this->errorHandler->getErrorStats();
        $this->assertEquals(1, $stats['file_errors']);
        $this->assertEquals(0, $stats['files_processed']);
        $this->assertContains('/tmp/test.html', $stats['files_failed']);
    }

    public function testProcessFilesWithOutputPathConflictSkipsSecondFile(): void
    {
        // Two distinct source files that, after extension normalization, map to the
        // same output path - the second one must be skipped as a conflict, not overwritten.
        $testFiles = [
            ['path' => '/tmp/source/test.html', 'url' => 'test.html', 'metadata' => []],
            ['path' => '/tmp/source/test.md', 'url' => 'test.html', 'metadata' => []],
        ];
        $this->setContainerVariable('SOURCE_DIR', '/tmp/source');
        $this->setContainerVariable('discovered_files', $testFiles);

        $tracker = new EventTrackingListener($this->container);
        $this->eventManager->registerListener('PRE_RENDER', [$tracker, 'handlePreRender'], 100);
        $this->eventManager->registerListener('RENDER', [$tracker, 'handleRender'], 100);
        $this->eventManager->registerListener('POST_RENDER', [$tracker, 'handlePostRender'], 100);

        $this->fileProcessor->processFiles();

        $stats = $this->errorHandler->getErrorStats();
        // The first file processes successfully; the second is a conflict and recorded as a file error
        $this->assertEquals(1, $stats['files_processed']);
        $this->assertEquals(1, $stats['file_errors']);
        $this->assertContains('/tmp/source/test.md', $stats['files_failed']);
    }

    public function testRenderContextStructure(): void
    {
        $testFiles = [['path' => '/tmp/test.html', 'url' => 'test.html', 'metadata' => []]];
        $this->setContainerVariable('discovered_files', $testFiles);

        $tracker = new EventTrackingListener($this->container);

        $this->eventManager->registerListener('PRE_RENDER', [$tracker, 'handlePreRender'], 100);

        $this->fileProcessor->processFiles();

        $event = $tracker->lastEvent;
        $this->assertNotNull($event);

        $this->assertEquals('/tmp/test.html', $event->filePath);
        $this->assertNull($event->renderedContent);
        $this->assertSame([], $event->metadata);
        $this->assertNull($event->outputPath);
        $this->assertFalse($event->skipFile);
    }
}

/**
 * Test double that records fired events and can simulate render output.
 */
class EventTrackingListener
{
    /**
     * @var array<int, string>
     */
    public array $eventsTracked = [];

    public ?RenderEvent $lastEvent = null;

    public function __construct(private readonly Container $container)
    {
    }

    public function handlePreRender(RenderEvent $event): void
    {
        $this->record('PRE_RENDER', $event);
    }

    public function handlePreRenderAndSkip(RenderEvent $event): void
    {
        $this->record('PRE_RENDER', $event);
        $event->skipFile = true;
    }

    public function handleRender(RenderEvent $event): void
    {
        $this->record('RENDER', $event);
        $event->renderedContent = 'mock content';
        $event->outputPath = rtrim((string) $this->container->getVariable('OUTPUT_DIR'), '/') . '/output.html';
    }

    public function handlePostRender(RenderEvent $event): void
    {
        $this->record('POST_RENDER', $event);
    }

    private function record(string $eventName, RenderEvent $event): void
    {
        $this->eventsTracked[] = $eventName;
        $this->lastEvent = $event;
    }
}
