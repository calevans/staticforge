<?php

namespace EICC\StaticForge\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use EICC\StaticForge\Core\FileProcessor;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;

class FileProcessorTest extends TestCase
{
    private FileProcessor $fileProcessor;
    private EventManager $eventManager;
    private Container $container;
    private Log $logger;

    protected function setUp(): void
    {
        $this->container = new Container();

        // Create a temporary log file for testing
        $logFile = sys_get_temp_dir() . '/file_processor_test.log';
        $this->logger = new Log('test', $logFile, 'INFO');
        $this->container->setVariable('logger', $this->logger);

        $this->eventManager = new EventManager($this->container);
        $this->fileProcessor = new FileProcessor($this->container, $this->eventManager);
    }

    public function testProcessFilesWithNoFiles(): void
    {
        // No discovered_files in container
        $this->fileProcessor->processFiles();

        // Should complete without error
        $this->assertTrue(true);
    }

    public function testProcessFilesWithEmptyArray(): void
    {
        $this->container->setVariable('discovered_files', []);

        $this->fileProcessor->processFiles();

        // Should complete without error
        $this->assertTrue(true);
    }

    public function testProcessFilesWithFiles(): void
    {
        $testFiles = [
            '/tmp/test1.html',
            '/tmp/test2.html'
        ];

        $this->container->setVariable('discovered_files', $testFiles);

        // Track events fired
        $eventsTracked = [];

        $this->eventManager->registerListener('PRE_RENDER', [new TestEventListener($eventsTracked), 'handleEvent'], 100);
        $this->eventManager->registerListener('RENDER', [new TestEventListener($eventsTracked), 'handleEvent'], 100);
        $this->eventManager->registerListener('POST_RENDER', [new TestEventListener($eventsTracked), 'handleEvent'], 100);

        $this->fileProcessor->processFiles();

        // Should have fired 6 events (3 per file)
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
        $testFiles = ['/tmp/test.html'];
        $this->container->setVariable('discovered_files', $testFiles);

        $eventsTracked = [];

        // Listener that sets skip_file flag in PRE_RENDER
        $this->eventManager->registerListener('PRE_RENDER', [new SkipFileListener($eventsTracked), 'handleEvent'], 100);
        $this->eventManager->registerListener('RENDER', [new TestEventListener($eventsTracked), 'handleEvent'], 100);
        $this->eventManager->registerListener('POST_RENDER', [new TestEventListener($eventsTracked), 'handleEvent'], 100);

        $this->fileProcessor->processFiles();

        // Should only have PRE_RENDER event, not RENDER or POST_RENDER
        $this->assertCount(1, $eventsTracked);
        $this->assertEquals('PRE_RENDER', $eventsTracked[0]['event']);
    }

    public function testRenderContextStructure(): void
    {
        $testFiles = ['/tmp/test.html'];
        $this->container->setVariable('discovered_files', $testFiles);

        $contextData = [];

        $this->eventManager->registerListener('PRE_RENDER', [new ContextCapturingListener($contextData), 'handleEvent'], 100);

        $this->fileProcessor->processFiles();

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

class TestEventListener
{
    private array $eventsTracked;

    public function __construct(array &$eventsTracked)
    {
        $this->eventsTracked = &$eventsTracked;
    }

    public function handleEvent(Container $container, array $parameters): array
    {
        // Determine which event this is based on call stack
        $trace = debug_backtrace();
        $eventName = null;

        foreach ($trace as $frame) {
            if (isset($frame['function']) && $frame['function'] === 'fire') {
                // Get the event name from the previous frame
                $eventName = $frame['args'][0] ?? 'UNKNOWN';
                break;
            }
        }

        $this->eventsTracked[] = [
            'event' => $eventName,
            'parameters' => $parameters
        ];

        return $parameters;
    }
}

class SkipFileListener
{
    private array $eventsTracked;

    public function __construct(array &$eventsTracked)
    {
        $this->eventsTracked = &$eventsTracked;
    }

    public function handleEvent(Container $container, array $parameters): array
    {
        $this->eventsTracked[] = [
            'event' => 'PRE_RENDER',
            'parameters' => $parameters
        ];

        $parameters['skip_file'] = true;
        return $parameters;
    }
}

class ContextCapturingListener
{
    private array $contextData;

    public function __construct(array &$contextData)
    {
        $this->contextData = &$contextData;
    }

    public function handleEvent(Container $container, array $parameters): array
    {
        $this->contextData = $parameters;
        return $parameters;
    }
}