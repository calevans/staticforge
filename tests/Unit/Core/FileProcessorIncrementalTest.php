<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\FileProcessor;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\ErrorHandler;
use EICC\StaticForge\Core\OutputWriter;
use EICC\Utils\Container;

class FileProcessorIncrementalTest extends UnitTestCase
{
    private FileProcessor $fileProcessor;
    private EventManager $eventManager;
    private ErrorHandler $errorHandler;
    private string $sourceDir;
    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceDir = sys_get_temp_dir() . '/staticforge_inc_source_' . uniqid();
        $this->outputDir = sys_get_temp_dir() . '/staticforge_inc_output_' . uniqid();
        mkdir($this->sourceDir, 0755, true);
        mkdir($this->outputDir, 0755, true);

        $this->setContainerVariable('SOURCE_DIR', $this->sourceDir);
        $this->setContainerVariable('OUTPUT_DIR', $this->outputDir);

        $this->eventManager = new EventManager();
        $this->errorHandler = $this->container->get(ErrorHandler::class);

        $this->fileProcessor = new FileProcessor(
            $this->container,
            $this->eventManager,
            $this->container->get(OutputWriter::class)
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->sourceDir);
        $this->removeDirectory($this->outputDir);

        parent::tearDown();
    }

    public function testSkipsRenderWhenOutputNewerThanSource(): void
    {
        $sourcePath = $this->sourceDir . '/test.html';
        $outputPath = $this->outputDir . '/test.html';

        file_put_contents($sourcePath, 'source content');
        touch($sourcePath, time() - 100);

        file_put_contents($outputPath, 'cached output content');
        touch($outputPath, time());

        $this->setContainerVariable('INCREMENTAL_BUILD', true);

        $tracker = new IncrementalEventTrackingListener($this->container);
        $this->eventManager->registerListener('PRE_RENDER', [$tracker, 'handlePreRender'], 100);
        $this->eventManager->registerListener('RENDER', [$tracker, 'handleRender'], 100);
        $this->eventManager->registerListener('POST_RENDER', [$tracker, 'handlePostRender'], 100);

        $this->setContainerVariable('discovered_files', [
            ['path' => $sourcePath, 'url' => 'test.html', 'metadata' => []],
        ]);

        $this->fileProcessor->processFiles();

        $this->assertSame(0, $tracker->renderCount, 'RENDER should not fire on a cache hit');
        $this->assertSame(1, $tracker->postRenderCount, 'POST_RENDER must always fire');
        $this->assertNotNull($tracker->lastPostRenderEvent);
        $this->assertSame('cached output content', $tracker->lastPostRenderEvent->renderedContent);
        $this->assertTrue($tracker->lastPostRenderEvent->cacheHit);

        $stats = $this->errorHandler->getErrorStats();
        $this->assertSame(1, $stats['files_processed']);
    }

    public function testFullRendersWhenSourceNewerThanOutput(): void
    {
        $sourcePath = $this->sourceDir . '/test.html';
        $outputPath = $this->outputDir . '/test.html';

        file_put_contents($outputPath, 'stale cached content');
        touch($outputPath, time() - 100);

        file_put_contents($sourcePath, 'fresh source content');
        touch($sourcePath, time());

        $this->setContainerVariable('INCREMENTAL_BUILD', true);

        $tracker = new IncrementalEventTrackingListener($this->container);
        $this->eventManager->registerListener('PRE_RENDER', [$tracker, 'handlePreRender'], 100);
        $this->eventManager->registerListener('RENDER', [$tracker, 'handleRender'], 100);
        $this->eventManager->registerListener('POST_RENDER', [$tracker, 'handlePostRender'], 100);

        $this->setContainerVariable('discovered_files', [
            ['path' => $sourcePath, 'url' => 'test.html', 'metadata' => []],
        ]);

        $this->fileProcessor->processFiles();

        $this->assertSame(1, $tracker->renderCount, 'RENDER must fire when source is newer than output');
        $this->assertSame(1, $tracker->postRenderCount);
        $this->assertNotNull($tracker->lastPostRenderEvent);
        $this->assertSame('freshly rendered content', $tracker->lastPostRenderEvent->renderedContent);

        $this->assertSame('freshly rendered content', file_get_contents($outputPath));
    }

    public function testFullRendersWhenOutputMissing(): void
    {
        $sourcePath = $this->sourceDir . '/test.html';
        file_put_contents($sourcePath, 'source content');

        $this->setContainerVariable('INCREMENTAL_BUILD', true);

        $tracker = new IncrementalEventTrackingListener($this->container);
        $this->eventManager->registerListener('PRE_RENDER', [$tracker, 'handlePreRender'], 100);
        $this->eventManager->registerListener('RENDER', [$tracker, 'handleRender'], 100);
        $this->eventManager->registerListener('POST_RENDER', [$tracker, 'handlePostRender'], 100);

        $this->setContainerVariable('discovered_files', [
            ['path' => $sourcePath, 'url' => 'test.html', 'metadata' => []],
        ]);

        $this->fileProcessor->processFiles();

        $this->assertSame(1, $tracker->renderCount, 'RENDER must fire when no output file exists yet');
        $this->assertSame(1, $tracker->postRenderCount);
    }

    public function testFallsBackToFullRenderWhenCachedFileUnreadable(): void
    {
        // file_get_contents() emits an E_WARNING when permission-denied; that's the exact
        // condition under test (an unreadable cache file), so it is expected, not a failure.
        set_error_handler(static fn (): bool => true, E_WARNING);

        $sourcePath = $this->sourceDir . '/test.html';
        $outputPath = $this->outputDir . '/test.html';

        file_put_contents($sourcePath, 'source content');
        touch($sourcePath, time() - 100);

        file_put_contents($outputPath, 'cached content');
        touch($outputPath, time());
        chmod($outputPath, 0000);

        $this->setContainerVariable('INCREMENTAL_BUILD', true);

        $tracker = new IncrementalEventTrackingListener($this->container);
        $this->eventManager->registerListener('PRE_RENDER', [$tracker, 'handlePreRender'], 100);
        $this->eventManager->registerListener('RENDER', [$tracker, 'handleRender'], 100);
        $this->eventManager->registerListener('POST_RENDER', [$tracker, 'handlePostRender'], 100);

        $this->setContainerVariable('discovered_files', [
            ['path' => $sourcePath, 'url' => 'test.html', 'metadata' => []],
        ]);

        try {
            $this->fileProcessor->processFiles();

            $this->assertSame(1, $tracker->renderCount, 'Must fall back to a full RENDER when cache is unreadable');
            $this->assertSame(1, $tracker->postRenderCount);
        } finally {
            // Restore permissions so tearDown can clean up the directory.
            chmod($outputPath, 0644);
            restore_error_handler();
        }
    }

    public function testIncrementalDisabledByDefault(): void
    {
        $sourcePath = $this->sourceDir . '/test.html';
        $outputPath = $this->outputDir . '/test.html';

        file_put_contents($sourcePath, 'source content');
        touch($sourcePath, time() - 100);

        file_put_contents($outputPath, 'cached content');
        touch($outputPath, time());

        // INCREMENTAL_BUILD is intentionally left unset.

        $tracker = new IncrementalEventTrackingListener($this->container);
        $this->eventManager->registerListener('PRE_RENDER', [$tracker, 'handlePreRender'], 100);
        $this->eventManager->registerListener('RENDER', [$tracker, 'handleRender'], 100);
        $this->eventManager->registerListener('POST_RENDER', [$tracker, 'handlePostRender'], 100);

        $this->setContainerVariable('discovered_files', [
            ['path' => $sourcePath, 'url' => 'test.html', 'metadata' => []],
        ]);

        $this->fileProcessor->processFiles();

        $this->assertSame(1, $tracker->renderCount, 'RENDER must always fire when incremental mode is off');
        $this->assertSame(1, $tracker->postRenderCount);
    }

    public function testAtomicWriteSurvivesPartialFailure(): void
    {
        // file_put_contents() emits an E_WARNING when permission-denied; that's the exact
        // condition under test (a failed temp-file write), so it is expected, not a failure.
        set_error_handler(static fn (): bool => true, E_WARNING);

        $sourcePath = $this->sourceDir . '/test.html';
        $outputPath = $this->outputDir . '/test.html';

        file_put_contents($outputPath, 'previously good content');

        // Make the output directory read-only so the temp-file write fails before rename
        // can occur, simulating an interrupted/failed write. The original file must survive.
        chmod($this->outputDir, 0555);

        file_put_contents($sourcePath, 'new source content');

        $tracker = new IncrementalEventTrackingListener($this->container);
        $this->eventManager->registerListener('PRE_RENDER', [$tracker, 'handlePreRender'], 100);
        $this->eventManager->registerListener('RENDER', [$tracker, 'handleRender'], 100);
        $this->eventManager->registerListener('POST_RENDER', [$tracker, 'handlePostRender'], 100);

        $this->setContainerVariable('discovered_files', [
            ['path' => $sourcePath, 'url' => 'test.html', 'metadata' => []],
        ]);

        try {
            $this->fileProcessor->processFiles();

            // The write should have failed (root in CI containers can still write despite 0555,
            // so only assert the invariant that matters: if it failed, the original is intact).
            $this->assertSame('previously good content', file_get_contents($outputPath));

            $stats = $this->errorHandler->getErrorStats();
            $this->assertSame(1, $stats['file_errors']);
        } finally {
            chmod($this->outputDir, 0755);
            restore_error_handler();
        }
    }
}

/**
 * Test double that records fired events for incremental-build scenarios and
 * simulates a real RENDER step writing distinguishable content.
 */
class IncrementalEventTrackingListener
{
    public int $renderCount = 0;
    public int $postRenderCount = 0;

    public ?RenderEvent $lastPostRenderEvent = null;

    public function __construct(private readonly Container $container)
    {
    }

    public function handlePreRender(RenderEvent $event): void
    {
    }

    public function handleRender(RenderEvent $event): void
    {
        $this->renderCount++;
        $outputDir = $this->container->getVariable('OUTPUT_DIR');
        $event->renderedContent = 'freshly rendered content';
        $event->outputPath = $outputDir . '/test.html';
    }

    public function handlePostRender(RenderEvent $event): void
    {
        $this->postRenderCount++;
        $this->lastPostRenderEvent = $event;
    }
}
