<?php

namespace EICC\StaticForge\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use EICC\StaticForge\Core\FeatureManager;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\BaseFeature;
use EICC\Utils\Container;
use EICC\Utils\Log;

class FeatureManagerTest extends TestCase
{
    private FeatureManager $featureManager;
    private Container $container;
    private EventManager $eventManager;
    private Log $logger;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->eventManager = new EventManager($this->container);

        // Create a temporary log file for testing
        $logFile = sys_get_temp_dir() . '/test.log';
        $this->logger = new Log('test', $logFile, 'INFO');

        $this->container->setVariable('logger', $this->logger);

        // Create temporary directory for test features
        $this->tempDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->container->setVariable('FEATURES_DIR', $this->tempDir);

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
        $this->assertEmpty($features);
    }

    public function testLoadFeaturesWithNonexistentDirectory(): void
    {
        // Use a fresh container to avoid variable conflicts
        $freshContainer = new Container();
        $freshEventManager = new EventManager($freshContainer);

        // Create a temporary log file for testing
        $logFile = sys_get_temp_dir() . '/test2.log';
        $logger = new Log('test2', $logFile, 'INFO');
        $freshContainer->setVariable('logger', $logger);
        $freshContainer->setVariable('FEATURES_DIR', '/nonexistent/path');

        $featureManager = new FeatureManager($freshContainer, $freshEventManager);
        $featureManager->loadFeatures();

        $features = $featureManager->getFeatures();
        $this->assertEmpty($features);
    }

    public function testLoadValidFeature(): void
    {
        // Create test feature
        $this->createTestFeature('LoadValidTestFeature');

        $this->featureManager->loadFeatures();

        $features = $this->featureManager->getFeatures();
        $this->assertCount(1, $features);
        $this->assertArrayHasKey('LoadValidTestFeature', $features);
        $this->assertInstanceOf(FeatureInterface::class, $features['LoadValidTestFeature']);
    }

    public function testGetSpecificFeature(): void
    {
        $this->createTestFeature('GetSpecificTestFeature');

        $this->featureManager->loadFeatures();

        $feature = $this->featureManager->getFeature('GetSpecificTestFeature');
        $this->assertInstanceOf(FeatureInterface::class, $feature);

        $nonexistent = $this->featureManager->getFeature('NonexistentFeature');
        $this->assertNull($nonexistent);
    }

    public function testLoadMultipleFeatures(): void
    {
        $this->createTestFeature('FeatureOne');
        $this->createTestFeature('FeatureTwo');

        $this->featureManager->loadFeatures();

        $features = $this->featureManager->getFeatures();
        $this->assertCount(2, $features);
        $this->assertArrayHasKey('FeatureOne', $features);
        $this->assertArrayHasKey('FeatureTwo', $features);
    }

    public function testFeaturesArrayInitialization(): void
    {
        $this->featureManager->loadFeatures();

        $featuresArray = $this->container->getVariable('features');
        $this->assertIsArray($featuresArray);
        $this->assertEmpty($featuresArray);
    }

    private function createTestFeature(string $featureName): void
    {
        $featureDir = $this->tempDir . '/' . $featureName;
        mkdir($featureDir, 0777, true);

        // Use unique class names to avoid redeclaration issues
        $uniqueId = substr(md5($featureName . microtime()), 0, 8);

        $featureContent = <<<PHP
<?php

namespace EICC\\StaticForge\\Features\\{$featureName};

use EICC\\StaticForge\\Core\\BaseFeature;
use EICC\\Utils\\Container;

class Feature_{$uniqueId} extends BaseFeature implements \\EICC\\StaticForge\\Core\\FeatureInterface
{
    protected array \$eventListeners = [
        'TEST_EVENT' => ['method' => 'handleTestEvent', 'priority' => 100]
    ];

    public function handleTestEvent(Container \$container, array \$parameters): array
    {
        return \$parameters;
    }
}

// Create alias for consistent naming
class_alias(Feature_{$uniqueId}::class, 'EICC\\StaticForge\\Features\\{$featureName}\\Feature');
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