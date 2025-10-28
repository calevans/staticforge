<?php

namespace EICC\StaticForge\Core;

use EICC\StaticForge\Core\Bootstrap;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureManager;
use EICC\StaticForge\Core\ExtensionRegistry;
use EICC\StaticForge\Core\FileDiscovery;
use EICC\StaticForge\Core\FileProcessor;
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
    private Log $logger;

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

        // Get logger from container
        $this->logger = $this->container->getVariable('logger') ?? $this->createDefaultLogger();
        $this->container->setVariable('logger', $this->logger);
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
        $this->fileProcessor = new FileProcessor($this->container, $this->eventManager);

        // Register core services in container
        $this->container->add('event_manager', $this->eventManager);
        $this->container->add('feature_manager', $this->featureManager);
        $this->container->add('extension_registry', $this->extensionRegistry);
        $this->container->add('file_discovery', $this->fileDiscovery);
        $this->container->add('file_processor', $this->fileProcessor);
    }

    /**
     * Execute the complete static site generation process
     */
    public function generate(): bool
    {
        try {
            $this->logger->log('INFO', 'Starting static site generation');

            // Step 1: Load and register features
            $this->featureManager->loadFeatures();

            // Step 2: Execute 9-step event pipeline
            $this->executeEventPipeline();

            $this->logger->log('INFO', 'Static site generation completed successfully');
            return true;

        } catch (Exception $e) {
            $this->logger->log('ERROR', 'Core system failure during generation: ' . $e->getMessage());
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
            $this->logger->log('INFO', "Firing event: {$eventName}");
            $this->eventManager->fire($eventName, $parameters);
        } catch (Exception $e) {
            // Feature failures should not stop generation
            $this->logger->log('ERROR', "Feature error during {$eventName} event: " . $e->getMessage());
            // Continue execution - feature failures are not fatal
        }
    }

    /**
     * Discover content files using FileDiscovery
     */
    private function discoverFiles(): void
    {
        try {
            $this->logger->log('INFO', 'Discovering content files');
            $this->fileDiscovery->discoverFiles();

            // FileDiscovery sets discovered_files in container, get count for logging
            $discoveredFiles = $this->container->getVariable('discovered_files');
            $fileCount = is_array($discoveredFiles) ? count($discoveredFiles) : 0;
            $this->logger->log('INFO', 'Discovered ' . $fileCount . ' content files');
        } catch (Exception $e) {
            $this->logger->log('ERROR', 'File discovery failed: ' . $e->getMessage());
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
            $this->logger->log('INFO', 'Processing content files');
            $this->fileProcessor->processFiles();
        } catch (Exception $e) {
            $this->logger->log('ERROR', 'File processing failed: ' . $e->getMessage());
            // File processing failures are not fatal, but logged
        }
    }

    /**
     * Create default logger if none provided
     */
    private function createDefaultLogger(): Log
    {
        $logFile = sys_get_temp_dir() . '/staticforge.log';
        return new Log('staticforge', $logFile, 'INFO');
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
}