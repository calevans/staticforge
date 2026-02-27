<?php

declare(strict_types=1);

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

    /**
     * Map of feature names to their status (enabled/disabled)
     * @var array<string, string>
     */
    private array $featureStatuses = [];

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
        // Prevent double loading
        if (!empty($this->features)) {
            return;
        }

        // Load disabled features from site config
        $siteConfig = $this->container->getVariable('site_config') ?? [];
        if (isset($siteConfig['disabled_features']) && is_array($siteConfig['disabled_features'])) {
            $this->disabledFeatures = array_fill_keys($siteConfig['disabled_features'], true);
        }

        // Load user features first (higher priority - can disable library features)
        $userFeaturesDir = $this->container->getVariable('FEATURES_DIR') ?? 'src/Features';
        if (is_dir($userFeaturesDir)) {
            $this->loadFeaturesFromDirectory($userFeaturesDir, 'Custom');
        }

        // Load Composer features (medium priority)
        $this->discoverComposerFeatures();

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
     * Get all feature statuses
     * @return array<string, string>
     */
    public function getFeatureStatuses(): array
    {
        return $this->featureStatuses;
    }

    /**
     * Get a specific feature by name
     */
    public function getFeature(string $name): ?FeatureInterface
    {
        return $this->features[$name] ?? null;
    }



    /**
     * Check if a feature is enabled
     */
    public function isFeatureEnabled(string $featureName): bool
    {
        return !$this->isFeatureDisabled($featureName);
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
                $this->featureStatuses[$feature->getName()] = 'disabled';
                $this->logger->log('INFO', "Skipping disabled feature: {$feature->getName()}");
                return;
            }

            // Check if feature is already loaded (Custom features take precedence over Standard)
            if (isset($this->features[$feature->getName()])) {
                $this->logger->log('INFO', "Skipping duplicate feature (already loaded): {$feature->getName()}");
                return;
            }

            // Inject container if the feature supports it
            if (method_exists($feature, 'setContainer')) {
                $feature->setContainer($this->container);
            }

            // Register and store the feature
            $feature->register($this->eventManager);
            $this->features[$feature->getName()] = $feature;
            $this->featureTypes[$feature->getName()] = $type;
            $this->featureStatuses[$feature->getName()] = 'enabled';
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
                    // Try to resolve from container first (for DI)
                    if ($this->container->has($className)) {
                        $feature = $this->container->get($className);
                    } else {
                        // Fallback to new for features without dependencies
                        $feature = new $className();
                    }

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
     *
     * @return array<string>
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
     * Discover features from Composer packages
     */
    private function discoverComposerFeatures(): void
    {
        // Allow override for testing
        $installedJsonPath = $this->container->getVariable('COMPOSER_INSTALLED_JSON_PATH');

        if (!$installedJsonPath) {
            $installedJsonPath = getcwd() . '/vendor/composer/installed.json';

            // Fallback for when running inside vendor/bin or different structure
            if (!file_exists($installedJsonPath)) {
                 $installedJsonPath = __DIR__ . '/../../../../composer/installed.json';
            }
        }

        if (!file_exists($installedJsonPath)) {
            // Silent fail or debug log - it's possible (though unlikely) to run without composer install?
            // But we check for autoloader in bin/staticforge.php so this should exist.
            $this->logger->log('DEBUG', "Composer installed.json not found at {$installedJsonPath}");
            return;
        }

        $content = file_get_contents($installedJsonPath);
        if (!$content) {
            return;
        }

        $installedData = json_decode($content, true);
        if (!is_array($installedData)) {
            return;
        }

        // Handle Composer 2.0 structure
        $packages = $installedData['packages'] ?? $installedData;

        foreach ($packages as $package) {
            if (isset($package['extra']['staticforge']['feature'])) {
                $featureClass = $package['extra']['staticforge']['feature'];
                $this->loadComposerFeature($featureClass, $package['name']);
            }
        }
    }

    /**
     * Load a single feature from a Composer package
     */
    private function loadComposerFeature(string $className, string $packageName): void
    {
        if (!class_exists($className)) {
            $this->logger->log('WARNING', "Feature class {$className} from package {$packageName} not found.");
            return;
        }

        try {
            $feature = new $className();
            if (!$feature instanceof FeatureInterface) {
                 $this->logger->log('WARNING', "Class {$className} from package {$packageName} does not implement FeatureInterface.");
                 return;
            }

            // Check disabled
            if ($this->isFeatureDisabled($feature->getName())) {
                $this->featureStatuses[$feature->getName()] = 'disabled';
                $this->logger->log('INFO', "Skipping disabled composer feature: {$feature->getName()}");
                return;
            }

            // Check duplicate (Local wins)
            if (isset($this->features[$feature->getName()])) {
                $this->logger->log('INFO', "Skipping duplicate feature (already loaded): {$feature->getName()}");
                return;
            }

            // Register
            if (method_exists($feature, 'setContainer')) {
                $feature->setContainer($this->container);
            }
            $feature->register($this->eventManager);
            $this->features[$feature->getName()] = $feature;
            $this->featureTypes[$feature->getName()] = 'Composer';
            $this->featureStatuses[$feature->getName()] = 'enabled';
            $this->logger->log('INFO', "Loaded composer feature: {$feature->getName()} from {$packageName}");

        } catch (\Exception $e) {
            $this->logger->log('ERROR', "Failed to load composer feature {$className}: " . $e->getMessage());
        }
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
