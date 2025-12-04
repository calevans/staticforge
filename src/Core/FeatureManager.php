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

    /**
     * Loaded feature instances
     * @var array<string, FeatureInterface>
     */
    private array $features = [];

    /**
     * Registry of disabled features
     * @var array<string, bool>
     */
    private array $disabledFeatures = [];

    /**
     * Map of feature names to their type (Standard/Custom)
     * @var array<string, string>
     */
    private array $featureTypes = [];

    public function __construct(Container $container, EventManager $eventManager)
    {
        $this->container = $container;
        $this->eventManager = $eventManager;
        $this->logger = $container->get('logger');
    }

    /**
     * Load all features from multiple directories
     * User features load first and can disable library features via disableLibraryFeature()
     */
    public function loadFeatures(): void
    {
        // Load user features first (higher priority - can disable library features)
        $userFeaturesDir = $this->container->getVariable('FEATURES_DIR') ?? 'src/Features';
        if (is_dir($userFeaturesDir)) {
            $this->loadFeaturesFromDirectory($userFeaturesDir, 'Custom');
        }

        // Then load library features (lower priority - may be disabled by user features)
        $vendorFeaturesDir = $this->findVendorFeaturesDir();
        if ($vendorFeaturesDir && is_dir($vendorFeaturesDir)) {
            $this->loadFeaturesFromDirectory($vendorFeaturesDir, 'Standard');
        } else {
            $this->logger->log('WARNING', "Vendor features dir not found or not a directory: {$vendorFeaturesDir}");
        }

        if (empty($this->features)) {
            $this->logger->log('WARNING', 'No features loaded');
        }

        // Initialize features array in container with feature names as keys
        $featuresData = [];
        foreach (array_keys($this->features) as $featureName) {
            $featuresData[$featureName] = [
                'type' => $this->featureTypes[$featureName] ?? 'Standard'
            ];
        }
        $this->container->setVariable('features', $featuresData);

        $this->logger->log('INFO', "Loaded " . count($this->features) . " features");
    }

    /**
     * Get all loaded features
     *
     * @return array<string, FeatureInterface>
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
     * Check if a feature is disabled
     */
    private function isFeatureDisabled(string $featureName): bool
    {
        return isset($this->disabledFeatures[$featureName]);
    }

    /**
     * Load features from a specific directory
     */
    private function loadFeaturesFromDirectory(string $baseDir, string $type): void
    {
        $this->logger->log('INFO', "Scanning features directory: {$baseDir}");
        $featureDirectories = $this->discoverFeatureDirectories($baseDir);

        foreach ($featureDirectories as $featureDir) {
            $this->loadFeature($featureDir, $type);
        }
    }

    /**
     * Discover feature directories
     * @return array<string>
     */
    private function discoverFeatureDirectories(string $featuresDir): array
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
    private function loadFeature(string $featureDir, string $type): void
    {
        $directoryName = basename($featureDir);
        $featureFile = $featureDir . '/Feature.php';

        if (!file_exists($featureFile)) {
            $this->logger->log('WARNING', "Feature file not found: {$featureFile}");
            return;
        }

        try {
            // Include the feature file to make classes available
            require_once $featureFile;

            // Find the feature class
            $feature = $this->findFeatureClassInFile($featureFile, $directoryName);

            if (!$feature) {
                $this->logger->log('WARNING', "Could not instantiate feature class in {$featureFile}");
                return;
            }

            // Check if this feature is disabled before registering
            if ($this->isFeatureDisabled($feature->getName())) {
                $this->logger->log('INFO', "Skipping disabled feature: {$feature->getName()}");
                return;
            }

            // Register and store the feature
            $feature->register($this->eventManager, $this->container);
            $this->features[$feature->getName()] = $feature;
            $this->featureTypes[$feature->getName()] = $type;
            $this->logger->log('INFO', "Loaded feature: {$feature->getName()}");
        } catch (\Exception $e) {
            $this->logger->log('ERROR', "Failed to load feature from {$directoryName}: " . $e->getMessage());
        }
    }





    /**
     * Find and instantiate the Feature class from a file
     */
    private function findFeatureClassInFile(string $featureFile, string $directoryName): ?FeatureInterface
    {
        // Try all possible class names (user and library)
        $possibleClasses = $this->getPossibleFeatureClasses($directoryName);

        foreach ($possibleClasses as $className) {
            if (class_exists($className)) {
                try {
                    $feature = new $className();
                    if ($feature instanceof FeatureInterface) {
                        return $feature;
                    }
                } catch (\Exception $e) {
                    $this->logger->log('WARNING', "Failed to instantiate feature {$className}: " . $e->getMessage());
                }
            }
        }

        $this->logger->log('WARNING', "No valid Feature class found in {$featureFile}");
        return null;
    }



    /**
     * Get possible class names for all features (library + user)
     */
    private function getPossibleFeatureClasses(string $directoryName): array
    {
        return [
            // User namespaces FIRST (higher priority)
            "App\\Features\\{$directoryName}\\Feature",
            "App\\StaticForge\\Features\\{$directoryName}\\Feature",
            "Features\\{$directoryName}\\Feature",
            "MyProject\\Features\\{$directoryName}\\Feature",
            // Library feature namespace LAST (lower priority)
            "EICC\\StaticForge\\Features\\{$directoryName}\\Feature",
        ];
    }



    /**
     * Find features directory in vendor when used as library
     */
    private function findVendorFeaturesDir(): ?string
    {
        // Try to find the StaticForge package Features directory
        $paths = [
            __DIR__ . '/../Features/',                                 // When running from development
            getcwd() . '/vendor/eicc/staticforge/src/Features/',       // When installed as dependency
            __DIR__ . '/../../../../../../eicc/staticforge/src/Features/', // Alternative vendor structure
        ];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                return realpath($path);
            }
        }

        return null;
    }
}
