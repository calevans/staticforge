<?php

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\FeatureManager;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\Utils\Container;

class FeatureManagerComposerTest extends UnitTestCase
{
    private FeatureManager $featureManager;
    private EventManager $eventManager;
    private string $tempDir;
    private string $installedJsonPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventManager = new EventManager($this->container);
        $this->tempDir = sys_get_temp_dir() . '/staticforge_composer_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->installedJsonPath = $this->tempDir . '/installed.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->installedJsonPath)) {
            unlink($this->installedJsonPath);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function testDiscoverComposerFeatures(): void
    {
        // Create dummy installed.json
        $data = [
            'packages' => [
                [
                    'name' => 'vendor/test-feature',
                    'extra' => [
                        'staticforge' => [
                            'feature' => MockComposerFeature::class
                        ]
                    ]
                ]
            ]
        ];
        file_put_contents($this->installedJsonPath, json_encode($data));

        // Configure container
        $this->setContainerVariable('COMPOSER_INSTALLED_JSON_PATH', $this->installedJsonPath);

        // Initialize manager
        $this->featureManager = new FeatureManager($this->container, $this->eventManager);
        $this->featureManager->loadFeatures();

        // Assert
        $features = $this->featureManager->getFeatures();
        $this->assertArrayHasKey('MockComposerFeature', $features);
        $this->assertInstanceOf(MockComposerFeature::class, $features['MockComposerFeature']);
    }
}

class MockComposerFeature implements FeatureInterface
{
    public function getName(): string
    {
        return 'MockComposerFeature';
    }

    public function register(EventManager $eventManager, Container $container): void
    {
        // Do nothing
    }
}
