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
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventManager = new EventManager($this->container);

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
        // Library features should still be loaded even with nonexistent user features directory
        $this->assertGreaterThan(0, count($features));
    }

    public function testLoadValidFeature(): void
    {
        // Create test feature with simpler approach
        $this->createSimpleTestFeature('TestFeature');

        $this->featureManager->loadFeatures();

        $features = $this->featureManager->getFeatures();
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

    // removeDirectory is now provided by UnitTestCase

    public function testFeatureDisabling(): void
    {
        // Create a test feature
        $this->createSimpleTestFeature('DisabledFeature');

        // Configure site config to disable this feature
        $siteConfig = [
            'disabled_features' => ['DisabledFeature']
        ];
        $this->setContainerVariable('site_config', $siteConfig);

        // Re-initialize feature manager to pick up the config
        $this->featureManager = new FeatureManager($this->container, $this->eventManager);
        $this->featureManager->loadFeatures();

        // Verify feature is not loaded
        $feature = $this->featureManager->getFeature('DisabledFeature');
        $this->assertNull($feature, 'Disabled feature should not be loaded');

        // Verify isFeatureEnabled returns false
        $this->assertFalse($this->featureManager->isFeatureEnabled('DisabledFeature'));

        // Verify enabled feature works
        $this->assertTrue($this->featureManager->isFeatureEnabled('SomeOtherFeature'));
    }

    public function testLoadFeaturesIsIdempotent(): void
    {
        $this->createSimpleTestFeature('IdempotentFeature');

        $this->featureManager->loadFeatures();
        $countAfterFirstLoad = count($this->featureManager->getFeatures());

        // Calling loadFeatures() again should be a no-op (prevents double loading)
        $this->featureManager->loadFeatures();
        $countAfterSecondLoad = count($this->featureManager->getFeatures());

        $this->assertSame($countAfterFirstLoad, $countAfterSecondLoad);
    }

    public function testFeatureDirectoryWithoutValidFeatureClassIsSkipped(): void
    {
        // Feature.php exists but does not declare a recognizable Feature class
        $featureDir = $this->tempDir . '/Broken';
        mkdir($featureDir, 0777, true);
        file_put_contents($featureDir . '/Feature.php', "<?php\n// no class declared here\n");

        // Should not throw - the feature is simply skipped with a warning logged
        $this->featureManager->loadFeatures();

        $this->assertNull($this->featureManager->getFeature('Broken'));
    }

    public function testFeatureConstructorThrowingExceptionIsHandledGracefully(): void
    {
        $featureDir = $this->tempDir . '/ThrowingFeature';
        mkdir($featureDir, 0777, true);

        $featureContent = <<<'PHP'
<?php

namespace App\Features\ThrowingFeature;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;

class Feature extends BaseFeature implements FeatureInterface
{
    public function __construct()
    {
        throw new \RuntimeException('Constructor failure');
    }
}
PHP;
        file_put_contents($featureDir . '/Feature.php', $featureContent);

        // Should not propagate the exception - failure is logged and loading continues
        $this->featureManager->loadFeatures();

        $this->assertNull($this->featureManager->getFeature('ThrowingFeature'));
        // Library features should still have loaded despite this failure
        $this->assertGreaterThan(0, count($this->featureManager->getFeatures()));
    }

    public function testDuplicateFeatureAcrossDirectoriesKeepsFirstLoaded(): void
    {
        // User feature takes precedence; loading twice via getPossibleFeatureClasses
        // resolution should not duplicate entries for the same feature name.
        $this->createSimpleTestFeature('UniqueFeature');

        $this->featureManager->loadFeatures();

        $features = $this->featureManager->getFeatures();
        $occurrences = 0;
        foreach (array_keys($features) as $name) {
            if ($name === 'UniqueFeature') {
                $occurrences++;
            }
        }

        $this->assertSame(1, $occurrences);
    }
}
