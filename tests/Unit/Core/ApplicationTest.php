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
            $this->fail('Generate returned false - check for errors in the implementation');
        }

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
        $eventManager = $this->application->getEventManager();

        $testListener = new class {
            /** @var array<string> */
            public array $firedEvents = [];

            /**
             * @param array<string, mixed> $parameters
             * @return array<string, mixed>
             */
            public function handleEvent(Container $container, array $parameters): array
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
        $this->assertCount(6, $testListener->firedEvents); // All 6 events should have fired
    }

    public function testEventErrorHandling(): void
    {
        // Register an event listener that throws an exception
        $eventManager = $this->application->getEventManager();
        $errorListener = new class {
            /**
             * @param array<string, mixed> $parameters
             * @return array<string, mixed>
             */
            public function handleEvent(Container $container, array $parameters): array
            {
                throw new \Exception('Test feature error');
            }
        };

        $eventManager->registerListener('CREATE', [$errorListener, 'handleEvent']);

        // Generation should still succeed despite feature error
        $result = $this->application->generate();

        $this->assertTrue($result); // Should not fail due to feature error
    }

    public function testConstructorThrowsForInvalidTemplateOverride(): void
    {
        // A fresh container is needed because Application::__construct() registers itself
        // as a service, and the existing $this->container already has one bound.
        $freshContainer = include __DIR__ . '/../../../src/bootstrap.php';
        $freshContainer->updateVariable('SOURCE_DIR', $this->tempSourceDir);
        $freshContainer->updateVariable('OUTPUT_DIR', $this->tempOutputDir);
        $freshContainer->updateVariable('TEMPLATE_DIR', $this->tempFeaturesDir . '/templates');
        $freshContainer->updateVariable('FEATURES_DIR', $this->tempFeaturesDir);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not found in/');

        new Application($freshContainer, 'nonexistent-template');
    }

    public function testConstructorAcceptsValidTemplateOverride(): void
    {
        mkdir($this->tempFeaturesDir . '/templates/custom-template', 0777, true);

        $freshContainer = include __DIR__ . '/../../../src/bootstrap.php';
        $freshContainer->updateVariable('SOURCE_DIR', $this->tempSourceDir);
        $freshContainer->updateVariable('OUTPUT_DIR', $this->tempOutputDir);
        $freshContainer->updateVariable('TEMPLATE_DIR', $this->tempFeaturesDir . '/templates');
        $freshContainer->updateVariable('FEATURES_DIR', $this->tempFeaturesDir);

        $app = new Application($freshContainer, 'custom-template');

        $this->assertEquals('custom-template', $freshContainer->getVariable('TEMPLATE'));
        $this->assertInstanceOf(Application::class, $app);
    }

    public function testConstructorThrowsWhenLoggerMissingFromContainer(): void
    {
        $freshContainer = include __DIR__ . '/../../../src/bootstrap.php';

        // 'logger' is registered as a service via add(), not a variable; we must replace the
        // service slot directly to simulate a bootstrap that never initialized the logger.
        $reflection = new \ReflectionClass($freshContainer);
        $property = $reflection->getProperty('data');
        $property->setAccessible(true);
        $services = $property->getValue($freshContainer);
        unset($services['logger']);
        $property->setValue($freshContainer, $services);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Logger not initialized in container');

        new Application($freshContainer);
    }

    public function testGenerateReturnsFalseWhenSourceDirMissing(): void
    {
        $this->container->removeVariable('SOURCE_DIR');

        $result = $this->application->generate();

        $this->assertFalse($result);
    }

    public function testRenderSingleFileWritesOutputAndReturnsContext(): void
    {
        $renderListener = new class {
            /**
             * @param array<string, mixed> $parameters
             * @return array<string, mixed>
             */
            public function handlePreRender(Container $container, array $parameters): array
            {
                return $parameters;
            }

            /**
             * @param array<string, mixed> $parameters
             * @return array<string, mixed>
             */
            public function handleRender(Container $container, array $parameters): array
            {
                $parameters['rendered_content'] = '<h1>Rendered</h1>';
                $parameters['output_path'] = $parameters['additional'] ?? '/tmp/should-not-happen.html';
                return $parameters;
            }

            /**
             * @param array<string, mixed> $parameters
             * @return array<string, mixed>
             */
            public function handlePostRender(Container $container, array $parameters): array
            {
                return $parameters;
            }
        };

        $outputPath = $this->tempOutputDir . '/single-file.html';
        $eventManager = $this->application->getEventManager();
        $eventManager->registerListener('PRE_RENDER', [$renderListener, 'handlePreRender'], 100);
        $eventManager->registerListener('RENDER', [$renderListener, 'handleRender'], 100);
        $eventManager->registerListener('POST_RENDER', [$renderListener, 'handlePostRender'], 100);

        $context = $this->application->renderSingleFile('/tmp/input.html', ['additional' => $outputPath]);

        $this->assertEquals('<h1>Rendered</h1>', $context['rendered_content']);
        $this->assertFileExists($outputPath);
        $this->assertStringContainsString('Rendered', (string)file_get_contents($outputPath));
    }

    public function testRenderSingleFileReturnsEarlyWhenSkipped(): void
    {
        $skipListener = new class {
            /**
             * @param array<string, mixed> $parameters
             * @return array<string, mixed>
             */
            public function handlePreRender(Container $container, array $parameters): array
            {
                $parameters['skip_file'] = true;
                return $parameters;
            }
        };

        $eventManager = $this->application->getEventManager();
        $eventManager->registerListener('PRE_RENDER', [$skipListener, 'handlePreRender'], 100);

        $context = $this->application->renderSingleFile('/tmp/skip-me.html');

        $this->assertTrue($context['skip_file']);
        $this->assertNull($context['rendered_content']);
    }

    public function testRenderSingleFilePropagatesExceptionFromListener(): void
    {
        $throwingListener = new class {
            /**
             * @param array<string, mixed> $parameters
             * @return array<string, mixed>
             */
            public function handlePreRender(Container $container, array $parameters): array
            {
                throw new \RuntimeException('Listener failure during render');
            }
        };

        $eventManager = $this->application->getEventManager();
        $eventManager->registerListener('PRE_RENDER', [$throwingListener, 'handlePreRender'], 100);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Listener failure during render');

        $this->application->renderSingleFile('/tmp/throws.html');
    }

    // removeDirectory is now provided by UnitTestCase
}
