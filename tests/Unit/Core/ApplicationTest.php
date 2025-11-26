<?php

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\Application;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureManager;
use EICC\StaticForge\Core\ExtensionRegistry;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * @backupGlobals enabled
 */
class ApplicationTest extends UnitTestCase
{
    private Application $application;
    private string $testEnvFile;
    private string $tempSourceDir;
    private string $tempOutputDir;
    private string $tempFeaturesDir;
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp(); // Bootstrap with default env

        // Create temporary directories and files for Application-specific tests
        $this->tempSourceDir = sys_get_temp_dir() . '/staticforge_app_source_' . uniqid();
        $this->tempOutputDir = sys_get_temp_dir() . '/staticforge_app_output_' . uniqid();
        $this->tempFeaturesDir = sys_get_temp_dir() . '/staticforge_app_features_' . uniqid();
        $this->logFile = sys_get_temp_dir() . '/staticforge_app_test.log';

        mkdir($this->tempSourceDir, 0777, true);
        mkdir($this->tempOutputDir, 0777, true);
        mkdir($this->tempFeaturesDir, 0777, true);
        mkdir($this->tempFeaturesDir . '/templates', 0777, true);

        // Override container variables for this test
        $this->setContainerVariable('SITE_NAME', 'Test Application Site');
        $this->setContainerVariable('SITE_BASE_URL', 'https://testapp.com');
        $this->setContainerVariable('SOURCE_DIR', $this->tempSourceDir);
        $this->setContainerVariable('OUTPUT_DIR', $this->tempOutputDir);
        $this->setContainerVariable('TEMPLATE_DIR', $this->tempFeaturesDir . '/templates');
        $this->setContainerVariable('FEATURES_DIR', $this->tempFeaturesDir);
        $this->setContainerVariable('LOG_FILE', $this->logFile);
        $this->setContainerVariable('LOG_LEVEL', 'ERROR');

        $this->application = new Application($this->container);
    }

    protected function tearDown(): void
    {
        // Clean up temporary files and directories
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
        $this->removeDirectory($this->tempSourceDir);
        $this->removeDirectory($this->tempOutputDir);
        $this->removeDirectory($this->tempFeaturesDir);

        parent::tearDown();
    }

    public function testApplicationInitialization(): void
    {
        $container = $this->application->getContainer();

        $this->assertInstanceOf(Container::class, $container);
        $this->assertEquals('Test Application Site', $container->getVariable('SITE_NAME'));

        // Verify core services are registered
        $this->assertInstanceOf(EventManager::class, $container->get(EventManager::class));
        $this->assertInstanceOf(FeatureManager::class, $container->get(FeatureManager::class));
    }

    public function testGetEventManager(): void
    {
        $eventManager = $this->application->getEventManager();

        $this->assertInstanceOf(EventManager::class, $eventManager);
        $this->assertSame($eventManager, $this->application->getContainer()->get(EventManager::class));
    }

    public function testGetFeatureManager(): void
    {
        $featureManager = $this->application->getFeatureManager();

        $this->assertInstanceOf(FeatureManager::class, $featureManager);
        $this->assertSame($featureManager, $this->application->getContainer()->get(FeatureManager::class));
    }

    public function testGenerateWithEmptyContent(): void
    {
        // Debug: check if application was constructed properly
        $this->assertInstanceOf(Application::class, $this->application);

        // No content files in source directory
        $result = $this->application->generate();

        if (!$result) {
            // Check if there are any logged errors
            $container = $this->application->getContainer();
            $this->fail('Generate returned false - check for errors in the implementation');
        }

        $this->assertTrue($result);

        // Verify discovered_files was set (even if empty)
        $discoveredFiles = $this->application->getContainer()->getVariable('discovered_files');
        $this->assertIsArray($discoveredFiles);
    }

    public function testGenerateWithContentFiles(): void
    {
        // Register .html extension so files are discovered
        $extensionRegistry = $this->application->getContainer()->get(ExtensionRegistry::class);
        $extensionRegistry->registerExtension('.html');

        // Create test content files
        file_put_contents($this->tempSourceDir . '/test1.html', '<h1>Test 1</h1>');
        file_put_contents($this->tempSourceDir . '/test2.html', '<h1>Test 2</h1>');

        $result = $this->application->generate();

        $this->assertTrue($result);

        // Verify files were discovered
        $discoveredFiles = $this->application->getContainer()->getVariable('discovered_files');
        $this->assertIsArray($discoveredFiles);
        $this->assertCount(2, $discoveredFiles);
    }

    public function testEventPipelineExecution(): void
    {
        // Create a test event listener to track event firing
        $firedEvents = [];
        $eventManager = $this->application->getEventManager();

        $testListener = new class($firedEvents) {
            private array $firedEvents;

            public function __construct(array &$firedEvents)
            {
                $this->firedEvents = &$firedEvents;
            }

            public function handleEvent($container, $parameters)
            {
                $this->firedEvents[] = 'EVENT_FIRED';
                return $parameters;
            }
        };

        // Register listeners for all events in the pipeline
        $events = ['CREATE', 'PRE_GLOB', 'POST_GLOB', 'PRE_LOOP', 'POST_LOOP', 'DESTROY'];
        foreach ($events as $event) {
            $eventManager->registerListener($event, [$testListener, 'handleEvent']);
        }

        $result = $this->application->generate();

        $this->assertTrue($result);
        $this->assertCount(6, $firedEvents); // All 6 events should have fired
    }

    public function testEventErrorHandling(): void
    {
        // Register an event listener that throws an exception
        $eventManager = $this->application->getEventManager();
        $errorListener = new class {
            public function handleEvent($container, $parameters)
            {
                throw new \Exception('Test feature error');
            }
        };

        $eventManager->registerListener('CREATE', [$errorListener, 'handleEvent']);

        // Generation should still succeed despite feature error
        $result = $this->application->generate();

        $this->assertTrue($result); // Should not fail due to feature error
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}