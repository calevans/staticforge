<?php

namespace EICC\StaticForge\Core;

use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Manages feature discovery, instantiation, and registration
 */
class FeatureManager
{
    private Container $container;
    private EventManager $eventManager;
    private Log $logger;
    private array $features = [];

    public function __construct(Container $container, EventManager $eventManager)
    {
        $this->container = $container;
        $this->eventManager = $eventManager;
        $this->logger = $container->getVariable('logger');
    }

    /**
     * Discover and instantiate all features from the features directory
     */
    public function loadFeatures(): void
    {
        $featuresDir = $this->container->getVariable('FEATURES_DIR') ?? 'src/Features';

        if (!is_dir($featuresDir)) {
            $this->logger->log('INFO', "Features directory not found: {$featuresDir}");
            return;
        }

        $featureDirectories = $this->discoverFeatureDirectories($featuresDir);

        foreach ($featureDirectories as $featureDir) {
            $this->loadFeature($featureDir);
        }

        // Initialize features array in container
        $this->container->setVariable('features', []);

        $this->logger->log('INFO', "Loaded " . count($this->features) . " features");
    }

    /**
     * Get all loaded features
     */
    public function getFeatures(): array
    {
        return $this->features;
    }

    /**
     * Get a specific feature by name
     */
    public function getFeature(string $name): ?FeatureInterface
    {
        return $this->features[$name] ?? null;
    }

    /**
     * Discover feature directories
     */
    protected function discoverFeatureDirectories(string $featuresDir): array
    {
        $directories = [];

        if (!is_dir($featuresDir)) {
            return $directories;
        }

        $iterator = new \DirectoryIterator($featuresDir);

        foreach ($iterator as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }

            $featureFile = $item->getPathname() . '/Feature.php';
            if (file_exists($featureFile)) {
                $directories[] = $item->getPathname();
            }
        }

        return $directories;
    }

    /**
     * Load a single feature from directory
     */
    protected function loadFeature(string $featureDir): void
    {
        $featureName = basename($featureDir);
        $featureFile = $featureDir . '/Feature.php';

        if (!file_exists($featureFile)) {
            $this->logger->log('WARNING', "Feature file not found: {$featureFile}");
            return;
        }

        try {
            // Include the feature file
            require_once $featureFile;

            // Build class name
            $className = "EICC\\StaticForge\\Features\\{$featureName}\\Feature";

            if (!class_exists($className)) {
                $this->logger->log('WARNING', "Feature class not found: {$className}");
                return;
            }

            // Instantiate feature
            $feature = new $className();

            if (!$feature instanceof FeatureInterface) {
                $this->logger->log('WARNING', "Feature does not implement FeatureInterface: {$className}");
                return;
            }

            // Register feature
            $feature->register($this->eventManager, $this->container);
            $this->features[$featureName] = $feature;

            $this->logger->log('INFO', "Loaded feature: {$featureName}");

        } catch (\Exception $e) {
            $this->logger->log('ERROR', "Failed to load feature {$featureName}: " . $e->getMessage());
        }
    }
}