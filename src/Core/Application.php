<?php

namespace EICC\StaticForge\Core;

use EICC\StaticForge\Core\Bootstrap;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureManager;
use EICC\StaticForge\Core\ExtensionRegistry;
use EICC\StaticForge\Core\FileDiscovery;
use EICC\StaticForge\Core\FileProcessor;
use EICC\StaticForge\Core\ErrorHandler;
use EICC\StaticForge\Exceptions\CoreException;
use EICC\Utils\Container;
use EICC\Utils\Log;
use Exception;
use InvalidArgumentException;

/**
 * Main application class that orchestrates the static site generation process
 */
class Application
{
    private Container $container;
    private EventManager $eventManager;
    private FeatureManager $featureManager;
    private ExtensionRegistry $extensionRegistry;
    private FileDiscovery $fileDiscovery;
    private FileProcessor $fileProcessor;
    private ErrorHandler $errorHandler;
    private Log $logger;
    private bool $featuresLoaded = false;

    public function __construct(string $envPath = '.env', ?string $templateOverride = null)
    {
        $this->bootstrap($envPath);
        $this->applyTemplateOverride($templateOverride);
        $this->initializeComponents();
    }

    /**
     * Bootstrap the application with environment and core services
     */
    private function bootstrap(string $envPath): void
    {
        $bootstrap = new Bootstrap();
        $this->container = $bootstrap->initialize($envPath);

        // Get logger from container (should be set by EnvironmentLoader)
        $this->logger = $this->container->getVariable('logger');
        if (!$this->logger) {
            throw new \RuntimeException('Logger not initialized by EnvironmentLoader');
        }
    }

    /**
     * Apply template override if provided
     */
    private function applyTemplateOverride(?string $templateOverride): void
    {
        if ($templateOverride === null) {
            return;
        }

        // Validate template directory exists
        $templateDir = $this->container->getVariable('TEMPLATE_DIR') ?? 'templates';
        $templatePath = $templateDir . DIRECTORY_SEPARATOR . $templateOverride;

        if (!is_dir($templatePath)) {
            $availableTemplates = $this->getAvailableTemplates($templateDir);
            $availableList = empty($availableTemplates) ? 'No templates found' : implode(', ', $availableTemplates);

            throw new InvalidArgumentException(
                "Template '{$templateOverride}' not found in {$templateDir}/. Available templates: {$availableList}"
            );
        }

        // Override the TEMPLATE variable using updateVariable
        $this->container->updateVariable('TEMPLATE', $templateOverride);
        $this->logger->log('INFO', "Template overridden to: {$templateOverride}");
    }

    /**
     * Get list of available templates
     */
    private function getAvailableTemplates(string $templateDir): array
    {
        if (!is_dir($templateDir)) {
            return [];
        }

        $templates = [];
        $directories = scandir($templateDir);

        foreach ($directories as $dir) {
            if ($dir !== '.' && $dir !== '..' && is_dir($templateDir . DIRECTORY_SEPARATOR . $dir)) {
                $templates[] = $dir;
            }
        }

        return $templates;
    }

    /**
     * Initialize core components
     */
    private function initializeComponents(): void
    {
        $this->eventManager = new EventManager($this->container);
        $this->featureManager = new FeatureManager($this->container, $this->eventManager);
        $this->extensionRegistry = new ExtensionRegistry($this->container);
        $this->fileDiscovery = new FileDiscovery($this->container, $this->extensionRegistry);

        // Create and register ErrorHandler BEFORE FileProcessor (FileProcessor depends on it)
        $this->errorHandler = new ErrorHandler($this->container);
        $this->container->add('error_handler', $this->errorHandler);

        $this->fileProcessor = new FileProcessor($this->container, $this->eventManager);

        // Register remaining core services in container
        $this->container->add('event_manager', $this->eventManager);
        $this->container->add('feature_manager', $this->featureManager);
        $this->container->add('extension_registry', $this->extensionRegistry);
        $this->container->add('file_discovery', $this->fileDiscovery);
        $this->container->add('file_processor', $this->fileProcessor);
        $this->container->add('application', $this);  // Add application instance for features
    }

    /**
     * Execute the complete static site generation process
     */
    public function generate(): bool
    {
        try {
            $this->errorHandler->reset();
            $this->logger->log('INFO', 'Starting static site generation', [
                'pid' => getmypid(),
                'memory_limit' => ini_get('memory_limit'),
            ]);

            // Step 1: Load and register features
            $this->featureManager->loadFeatures();

            // Step 2: Execute 9-step event pipeline
            $this->executeEventPipeline();

            // Log error summary
            $this->errorHandler->logSummary();

            if ($this->errorHandler->hasCriticalErrors()) {
                $this->logger->log('ERROR', 'Generation completed with critical errors');
                return false;
            }

            $this->logger->log('INFO', 'Static site generation completed successfully');
            return true;
        } catch (Exception $e) {
            $this->errorHandler->handleCoreError($e, ['stage' => 'generation']);
            return false;
        }
    }

    /**
     * Execute the 9-step event pipeline
     */
    private function executeEventPipeline(): void
    {
        // Step 1: CREATE event (feature initialization)
        $this->fireEvent('CREATE', []);

        // Step 2: PRE_GLOB event (pre-discovery hooks)
        $this->fireEvent('PRE_GLOB', []);

        // Step 3: Discover content files
        $this->discoverFiles();

        // Step 4: POST_GLOB event (post-discovery processing)
        $this->fireEvent('POST_GLOB', []);

        // Step 5: PRE_LOOP event (pre-processing initialization)
        $this->fireEvent('PRE_LOOP', []);

        // Step 6: Process each content file
        $this->processFiles();

        // Step 7: POST_LOOP event (post-processing cleanup)
        $this->fireEvent('POST_LOOP', []);

        // Step 8: DESTROY event (final cleanup)
        $this->fireEvent('DESTROY', []);
    }

    /**
     * Fire an event with error handling
     */
    private function fireEvent(string $eventName, array $parameters): void
    {
        try {
            $this->logger->log('INFO', "Firing event: {$eventName}", [
                'event' => $eventName,
                'parameter_keys' => array_keys($parameters),
            ]);
            $this->eventManager->fire($eventName, $parameters);
        } catch (Exception $e) {
            // Feature failures should not stop generation
            $this->errorHandler->handleFeatureError($e, 'Unknown', $eventName);
            // Continue execution - feature failures are not fatal
        }
    }

    /**
     * Discover content files using FileDiscovery
     */
    private function discoverFiles(): void
    {
        try {
            $contentDir = $this->container->getVariable('CONTENT_DIR') ?? 'content';
            $this->logger->log('INFO', 'Discovering content files', ['content_dir' => $contentDir]);

            $this->fileDiscovery->discoverFiles();

            // FileDiscovery sets discovered_files in container, get count for logging
            $discoveredFiles = $this->container->getVariable('discovered_files');
            $fileCount = is_array($discoveredFiles) ? count($discoveredFiles) : 0;
            $this->logger->log('INFO', 'Discovered ' . $fileCount . ' content files', [
                'file_count' => $fileCount,
                'content_dir' => $contentDir,
            ]);
        } catch (Exception $e) {
            $this->errorHandler->handleCoreError(
                new CoreException('File discovery failed', 'FileDiscovery', [], 0, $e),
                ['stage' => 'discovery']
            );
            // Set empty array to allow processing to continue
            if (!$this->container->hasVariable('discovered_files')) {
                $this->container->setVariable('discovered_files', []);
            }
        }
    }

    /**
     * Process files using FileProcessor
     */
    private function processFiles(): void
    {
        try {
            $discoveredFiles = $this->container->getVariable('discovered_files') ?? [];
            $this->logger->log('INFO', 'Processing content files', [
                'file_count' => count($discoveredFiles),
            ]);
            $this->fileProcessor->processFiles();
        } catch (Exception $e) {
            $this->errorHandler->handleCoreError(
                new CoreException('File processing failed', 'FileProcessor', [], 0, $e),
                ['stage' => 'processing']
            );
            // File processing failures are not fatal, but logged
        }
    }

    /**
     * Get the container instance
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get the event manager instance
     */
    public function getEventManager(): EventManager
    {
        return $this->eventManager;
    }

    /**
     * Get the feature manager instance
     */
    public function getFeatureManager(): FeatureManager
    {
        return $this->featureManager;
    }

    /**
     * Render a single file through the PRE_RENDER -> RENDER -> POST_RENDER pipeline
     *
     * @param string $filePath Path to the file to render
     * @param array $additionalContext Additional context to merge into render context
     * @return array The final render context after all events
     * @throws Exception If rendering fails
     */
    public function renderSingleFile(string $filePath, array $additionalContext = []): array
    {
        // Build initial render context
        $renderContext = array_merge([
            'file_path' => $filePath,
            'rendered_content' => null,
            'metadata' => [],
            'output_path' => null,
            'skip_file' => false
        ], $additionalContext);

        try {
            $this->logger->log('DEBUG', "Rendering file: {$filePath}", [
                'file' => $filePath,
                'additional_context_keys' => array_keys($additionalContext),
            ]);

            // Fire PRE_RENDER event
            $renderContext = $this->eventManager->fire('PRE_RENDER', $renderContext);

            if ($renderContext['skip_file'] ?? false) {
                $this->logger->log('INFO', "File skipped by PRE_RENDER: {$filePath}");
                return $renderContext;
            }

            // Fire RENDER event
            $renderContext = $this->eventManager->fire('RENDER', $renderContext);

            // Fire POST_RENDER event
            $renderContext = $this->eventManager->fire('POST_RENDER', $renderContext);

            // Write the rendered output if content and path are available
            if (isset($renderContext['rendered_content']) && isset($renderContext['output_path'])) {
                $this->writeOutputFile($renderContext['output_path'], $renderContext['rendered_content']);
                $this->logger->log('INFO', "File rendered: {$renderContext['output_path']}", [
                    'output' => $renderContext['output_path'],
                    'size' => strlen($renderContext['rendered_content']),
                ]);
            }

            return $renderContext;
        } catch (Exception $e) {
            $this->errorHandler->handleFileError($e, $filePath, 'render');
            throw $e;
        }
    }

    /**
     * Ensure features are loaded before rendering files
     * Call this explicitly before using renderSingleFile() outside of generate()
     */
    public function ensureFeaturesLoaded(): void
    {
        // Don't use static - allow features to reload for each Application instance
        if (!$this->featuresLoaded) {
            $this->featureManager->loadFeatures();
            $this->featuresLoaded = true;
        }
    }

    /**
     * Write output file to disk
     *
     * @param string $outputPath Path to write the file
     * @param string $content Content to write
     */
    private function writeOutputFile(string $outputPath, string $content): void
    {
        $outputDir = dirname($outputPath);

        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
                throw new \RuntimeException("Failed to create output directory: {$outputDir}");
            }
        }

        if (file_put_contents($outputPath, $content) === false) {
            throw new \RuntimeException("Failed to write output file: {$outputPath}");
        }
    }
}
