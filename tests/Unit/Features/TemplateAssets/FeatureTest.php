<?php

namespace EICC\StaticForge\Tests\Unit\Features\TemplateAssets;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Features\TemplateAssets\Feature;
use EICC\StaticForge\Core\EventManager;
use org\bovigo\vfs\vfsStream;

class FeatureTest extends UnitTestCase
{
    private Feature $feature;
    private EventManager $eventManager;
    private $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventManager = new EventManager($this->container);
        $this->feature = new Feature();
        $this->feature->setContainer($this->container);
        $this->feature->register($this->eventManager);

        // Setup virtual file system
        $this->root = vfsStream::setup('root');
    }

    public function testRegisterRegistersEvent(): void
    {
        $listeners = $this->eventManager->getListeners('POST_LOOP');
        $this->assertNotEmpty($listeners);
        $this->assertCount(1, $listeners);
        $this->assertEquals([$this->feature, 'handlePostLoop'], $listeners[0]['callback']);
    }

    public function testDelegatesToService(): void
    {
        // Setup directory structure
        $structure = [
            'templates' => [
                'sample' => [
                    'assets' => [
                        'css' => [
                            'style.css' => 'body { color: red; }'
                        ]
                    ]
                ]
            ],
            'public' => []
        ];

        vfsStream::create($structure, $this->root);

        // Configure container with vfs paths
        $this->container->updateVariable('TEMPLATE_DIR', $this->root->url() . '/templates');
        $this->container->updateVariable('TEMPLATE', 'sample');
        $this->container->updateVariable('SOURCE_DIR', $this->root->url() . '/content');
        $this->container->updateVariable('OUTPUT_DIR', $this->root->url() . '/public');

        // Run the feature
        $this->feature->handlePostLoop($this->container, []);

        // Assertions - if file exists, delegation worked
        $this->assertTrue($this->root->hasChild('public/assets/css/style.css'), 'Template asset should be copied via service delegation');
    }
}
