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

    public function testMissingInstalledJsonIsHandledGracefully(): void
    {
        // Point at a path that doesn't exist - should not throw, simply skip composer discovery
        $this->setContainerVariable('COMPOSER_INSTALLED_JSON_PATH', $this->tempDir . '/does-not-exist.json');

        $this->featureManager = new FeatureManager($this->container, $this->eventManager);
        $this->featureManager->loadFeatures();

        $features = $this->featureManager->getFeatures();
        $this->assertArrayNotHasKey('MockComposerFeature', $features);
    }

    public function testMalformedInstalledJsonIsHandledGracefully(): void
    {
        file_put_contents($this->installedJsonPath, '{not valid json');
        $this->setContainerVariable('COMPOSER_INSTALLED_JSON_PATH', $this->installedJsonPath);

        $this->featureManager = new FeatureManager($this->container, $this->eventManager);

        // Should not throw - malformed JSON decodes to null and is skipped
        $this->featureManager->loadFeatures();

        $this->assertGreaterThanOrEqual(0, count($this->featureManager->getFeatures()));
    }

    public function testComposerFeatureClassNotFoundIsSkipped(): void
    {
        $data = [
            'packages' => [
                [
                    'name' => 'vendor/missing-feature',
                    'extra' => [
                        'staticforge' => [
                            'feature' => 'Nonexistent\\Class\\Feature',
                        ],
                    ],
                ],
            ],
        ];
        file_put_contents($this->installedJsonPath, json_encode($data));
        $this->setContainerVariable('COMPOSER_INSTALLED_JSON_PATH', $this->installedJsonPath);

        $this->featureManager = new FeatureManager($this->container, $this->eventManager);

        // Should not throw - logged as a warning and skipped
        $this->featureManager->loadFeatures();

        $this->assertNull($this->featureManager->getFeature('Nonexistent\\Class\\Feature'));
    }

    public function testComposerFeatureNotImplementingInterfaceIsSkipped(): void
    {
        $data = [
            'packages' => [
                [
                    'name' => 'vendor/invalid-feature',
                    'extra' => [
                        'staticforge' => [
                            'feature' => NotAFeature::class,
                        ],
                    ],
                ],
            ],
        ];
        file_put_contents($this->installedJsonPath, json_encode($data));
        $this->setContainerVariable('COMPOSER_INSTALLED_JSON_PATH', $this->installedJsonPath);

        $this->featureManager = new FeatureManager($this->container, $this->eventManager);
        $this->featureManager->loadFeatures();

        $features = $this->featureManager->getFeatures();
        $this->assertArrayNotHasKey('NotAFeature', $features);
    }

    public function testDisabledComposerFeatureIsNotLoaded(): void
    {
        $data = [
            'packages' => [
                [
                    'name' => 'vendor/test-feature',
                    'extra' => [
                        'staticforge' => [
                            'feature' => MockComposerFeature::class,
                        ],
                    ],
                ],
            ],
        ];
        file_put_contents($this->installedJsonPath, json_encode($data));
        $this->setContainerVariable('COMPOSER_INSTALLED_JSON_PATH', $this->installedJsonPath);
        $this->setContainerVariable('site_config', ['disabled_features' => ['MockComposerFeature']]);

        $this->featureManager = new FeatureManager($this->container, $this->eventManager);
        $this->featureManager->loadFeatures();

        $this->assertNull($this->featureManager->getFeature('MockComposerFeature'));
        $this->assertFalse($this->featureManager->isFeatureEnabled('MockComposerFeature'));
    }
}

class MockComposerFeature implements FeatureInterface
{
    public function getName(): string
    {
        return 'MockComposerFeature';
    }

    public function register(EventManager $eventManager): void
    {
        // Do nothing
    }
}

class NotAFeature
{
    // Intentionally does not implement FeatureInterface
}
