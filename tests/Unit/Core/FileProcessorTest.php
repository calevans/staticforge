<?php

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\FileProcessor;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\ErrorHandler;
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

        $this->eventManager = new EventManager($this->container);
        // FileProcessor resolves ErrorHandler from the container internally, so we must
        // use the same bootstrapped instance here to observe its error statistics.
        $this->errorHandler = $this->container->get(ErrorHandler::class);

        $this->fileProcessor = new FileProcessor($this->container, $this->eventManager);
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
        $tracker = new EventTrackingListener();

        $this->eventManager->registerListener('PRE_RENDER', [$tracker, 'handlePreRender'], 100);
        $this->eventManager->registerListener('RENDER', [$tracker, 'handleRender'], 100);
        $this->eventManager->registerListener('POST_RENDER', [$tracker, 'handlePostRender'], 100);

        $this->fileProcessor->processFiles();

        // Should have fired 6 events (3 per file)
        $eventsTracked = $tracker->eventsTracked;
        $this->assertCount(6, $eventsTracked);
        $this->assertEquals('PRE_RENDER', $eventsTracked[0]['event']);
        $this->assertEquals('RENDER', $eventsTracked[1]['event']);
        $this->assertEquals('POST_RENDER', $eventsTracked[2]['event']);
        $this->assertEquals('PRE_RENDER', $eventsTracked[3]['event']);
        $this->assertEquals('RENDER', $eventsTracked[4]['event']);
        $this->assertEquals('POST_RENDER', $eventsTracked[5]['event']);
    }

    public function testProcessFileWithSkipFlag(): void
    {
        $testFiles = [['path' => '/tmp/test.html', 'url' => 'test.html', 'metadata' => []]];
        $this->setContainerVariable('discovered_files', $testFiles);

        $tracker = new EventTrackingListener();

        // Listener that sets skip_file flag in PRE_RENDER
        $this->eventManager->registerListener('PRE_RENDER', [$tracker, 'handlePreRenderAndSkip'], 100);
        $this->eventManager->registerListener('RENDER', [$tracker, 'handleRender'], 100);
        $this->eventManager->registerListener('POST_RENDER', [$tracker, 'handlePostRender'], 100);

        $this->fileProcessor->processFiles();

        // Should only have PRE_RENDER event, not RENDER or POST_RENDER
        $eventsTracked = $tracker->eventsTracked;
        $this->assertCount(1, $eventsTracked);
        $this->assertEquals('PRE_RENDER', $eventsTracked[0]['event']);
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

        $tracker = new EventTrackingListener();
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

        $tracker = new EventTrackingListener();

        $this->eventManager->registerListener('PRE_RENDER', [$tracker, 'handlePreRender'], 100);

        $this->fileProcessor->processFiles();

        $contextData = $tracker->lastParameters;
        $this->assertNotNull($contextData);
        $this->assertArrayHasKey('file_path', $contextData);
        $this->assertArrayHasKey('rendered_content', $contextData);
        $this->assertArrayHasKey('metadata', $contextData);
        $this->assertArrayHasKey('output_path', $contextData);
        $this->assertArrayHasKey('skip_file', $contextData);

        $this->assertEquals('/tmp/test.html', $contextData['file_path']);
        $this->assertNull($contextData['rendered_content']);
        $this->assertIsArray($contextData['metadata']);
        $this->assertNull($contextData['output_path']);
        $this->assertFalse($contextData['skip_file']);
    }
}

/**
 * Test double that records fired events and can simulate render output.
 */
class EventTrackingListener
{
    /**
     * @var array<int, array{event: string, parameters: array<string, mixed>}>
     */
    public array $eventsTracked = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $lastParameters = null;

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePreRender(Container $container, array $parameters): array
    {
        $this->record('PRE_RENDER', $parameters);
        return $parameters;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePreRenderAndSkip(Container $container, array $parameters): array
    {
        $this->record('PRE_RENDER', $parameters);
        $parameters['skip_file'] = true;
        return $parameters;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handleRender(Container $container, array $parameters): array
    {
        $this->record('RENDER', $parameters);
        $parameters['rendered_content'] = 'mock content';
        $parameters['output_path'] = '/tmp/output.html';
        return $parameters;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostRender(Container $container, array $parameters): array
    {
        $this->record('POST_RENDER', $parameters);
        return $parameters;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function record(string $eventName, array $parameters): void
    {
        $this->eventsTracked[] = [
            'event' => $eventName,
            'parameters' => $parameters,
        ];
        $this->lastParameters = $parameters;
    }
}

