<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Categories;

use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\Categories\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

/**
 * @covers \EICC\StaticForge\Features\Categories\Feature
 */
class FeatureTest extends UnitTestCase
{
    private Feature $feature;
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventManager = new EventManager($this->container);
        $this->feature = new Feature();
        $this->feature->setContainer($this->container);
        $this->feature->register($this->eventManager);
    }

    public function testRegisterAddsExpectedListeners(): void
    {
        $this->assertNotEmpty($this->eventManager->getListeners('POST_GLOB'));
        $this->assertNotEmpty($this->eventManager->getListeners('POST_RENDER'));
    }

    public function testHandlePostRenderSkipsWhenNoCategory(): void
    {
        $parameters = ['metadata' => ['title' => 'No category'], 'output_path' => '/out/file.html'];

        $result = $this->feature->handlePostRender($this->container, $parameters);

        $this->assertEquals($parameters, $result);
    }

    public function testHandlePostRenderSkipsWhenNoOutputPath(): void
    {
        $parameters = ['metadata' => ['category' => 'Tech']];

        $result = $this->feature->handlePostRender($this->container, $parameters);

        $this->assertEquals($parameters, $result);
    }

    public function testHandlePostRenderCategorizesOutputPath(): void
    {
        $parameters = [
            'metadata' => ['category' => 'Tech'],
            'output_path' => '/out/article.html',
        ];

        $result = $this->feature->handlePostRender($this->container, $parameters);

        $this->assertEquals('/out/tech/article.html', $result['output_path']);
    }

    public function testHandlePostGlobProcessesDiscoveredFiles(): void
    {
        $this->setContainerVariable('discovered_files', [
            [
                'path' => '/content/categories/tech.md',
                'metadata' => ['type' => 'category', 'template' => 'tech-layout'],
            ],
            [
                'path' => '/content/posts/article.md',
                'metadata' => ['category' => 'Tech', 'template' => 'base'],
            ],
        ]);

        $parameters = [];
        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $this->assertEquals($parameters, $result);

        $updated = $this->container->getVariable('discovered_files');
        $this->assertEquals('tech-layout', $updated[1]['metadata']['template']);
    }
}
