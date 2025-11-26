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
        $this->feature->register($this->eventManager, $this->container);

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

    public function testHandlePostLoopCopiesAssets(): void
    {
        // Setup directory structure
        $structure = [
            'templates' => [
                'sample' => [
                    'assets' => [
                        'css' => [
                            'style.css' => 'body { color: red; }'
                        ],
                        'js' => [
                            'app.js' => 'console.log("template");'
                        ]
                    ]
                ]
            ],
            'content' => [
                'assets' => [
                    'css' => [
                        'custom.css' => 'body { color: blue; }'
                    ],
                    'js' => [
                        'app.js' => 'console.log("content");' // Should overwrite template
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

        // Assertions
        $this->assertTrue($this->root->hasChild('public/assets/css/style.css'), 'Template asset should be copied');
        $this->assertTrue($this->root->hasChild('public/assets/css/custom.css'), 'Content asset should be copied');
        $this->assertTrue($this->root->hasChild('public/assets/js/app.js'), 'Conflicting asset should exist');

        // Check content
        $this->assertEquals('body { color: red; }', $this->root->getChild('public/assets/css/style.css')->getContent());
        $this->assertEquals('body { color: blue; }', $this->root->getChild('public/assets/css/custom.css')->getContent());

        // Check overwrite
        $this->assertEquals('console.log("content");', $this->root->getChild('public/assets/js/app.js')->getContent(), 'Content asset should overwrite template asset');
    }
}
