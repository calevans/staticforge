<?php

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\FeatureManager;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\ExtensionRegistry;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\BaseFeature;
use EICC\Utils\Container;
use EICC\Utils\Log;

class FeatureManagerTest extends UnitTestCase
{
    private FeatureManager $featureManager;

    private EventManager $eventManager;
    private Log $logger;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventManager = new EventManager($this->container);

        // Create a temporary log file for testing
        $logFile = sys_get_temp_dir() . '/test.log';
        $this->logger = $this->container->get('logger');

        // Create temporary directory for test features
        $this->tempDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->setContainerVariable('FEATURES_DIR', $this->tempDir);

        $this->featureManager = new FeatureManager($this->container, $this->eventManager);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testLoadFeaturesWithEmptyDirectory(): void
    {
        $this->featureManager->loadFeatures();

        $features = $this->featureManager->getFeatures();
        $this->assertIsArray($features);
        // Library features should still be loaded even with empty user features directory
        $this->assertGreaterThan(0, count($features));
    }

    public function testLoadFeaturesWithNonexistentDirectory(): void
    {
        // Test with nonexistent directory
        $this->setContainerVariable('FEATURES_DIR', '/nonexistent/path');

        $freshFeatureManager = new FeatureManager($this->container, $this->eventManager);
        $freshFeatureManager->loadFeatures();

        $features = $freshFeatureManager->getFeatures();
        $this->assertIsArray($features);
        // Library features should still be loaded even with nonexistent user features directory
        $this->assertGreaterThan(0, count($features));
    }

    public function testLoadValidFeature(): void
    {
        // Create test feature with simpler approach
        $this->createSimpleTestFeature('TestFeature');

        $this->featureManager->loadFeatures();

        $features = $this->featureManager->getFeatures();
        $this->assertIsArray($features);
        // Should load library features + 1 user feature
        $this->assertGreaterThan(1, count($features));
        $this->assertArrayHasKey('TestFeature', $features);
    }

    public function testGetSpecificFeature(): void
    {
        $this->createSimpleTestFeature('SpecificFeature');

        $this->featureManager->loadFeatures();

        $feature = $this->featureManager->getFeature('SpecificFeature');
        $this->assertInstanceOf(FeatureInterface::class, $feature);

        $nonexistent = $this->featureManager->getFeature('NonexistentFeature');
        $this->assertNull($nonexistent);
    }

    public function testFeaturesArrayInitialization(): void
    {
        $this->featureManager->loadFeatures();

        $featuresArray = $this->container->getVariable('features');
        $this->assertIsArray($featuresArray);
        // Features array should contain keys for loaded features
        $this->assertNotEmpty($featuresArray);

        // Check structure of feature data
        $firstFeature = reset($featuresArray);
        $this->assertIsArray($firstFeature);
        $this->assertArrayHasKey('type', $firstFeature);
    }

    /**
     * Create a simple test feature without dynamic class creation complexity
     */
    private function createSimpleTestFeature(string $featureName): void
    {
        $featureDir = $this->tempDir . '/' . $featureName;
        mkdir($featureDir, 0777, true);

        $featureContent = <<<PHP
<?php

namespace App\\Features\\{$featureName};

use EICC\\StaticForge\\Core\\BaseFeature;
use EICC\\StaticForge\\Core\\FeatureInterface;
use EICC\\Utils\\Container;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string \$name = '{$featureName}';

    protected array \$eventListeners = [
        'TEST_EVENT' => ['method' => 'handleTestEvent', 'priority' => 100]
    ];

    public function handleTestEvent(Container \$container, array \$parameters): array
    {
        return \$parameters;
    }
}
PHP;

        file_put_contents($featureDir . '/Feature.php', $featureContent);
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