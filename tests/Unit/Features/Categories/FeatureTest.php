<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Categories;

use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureFactory;
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

        $this->eventManager = new EventManager();
        $feature = (new FeatureFactory($this->container))->make(Feature::class);
        $this->assertInstanceOf(Feature::class, $feature);
        $this->feature = $feature;
        $this->feature->register($this->eventManager);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function makeEvent(?string $outputPath, array $metadata = []): RenderEvent
    {
        return new RenderEvent(
            name: 'POST_RENDER',
            filePath: '',
            fileUrl: '',
            metadata: $metadata,
            outputPath: $outputPath,
        );
    }

    public function testRegisterAddsExpectedListeners(): void
    {
        $this->assertNotEmpty($this->eventManager->getListeners('POST_GLOB'));
        $this->assertNotEmpty($this->eventManager->getListeners('POST_RENDER'));
    }

    public function testHandlePostRenderSkipsWhenNoCategory(): void
    {
        $event = $this->makeEvent('/out/file.html', ['title' => 'No category']);

        $this->feature->handlePostRender($event);

        $this->assertEquals('/out/file.html', $event->outputPath);
    }

    public function testHandlePostRenderSkipsWhenNoOutputPath(): void
    {
        $event = $this->makeEvent(null, ['category' => 'Tech']);

        $this->feature->handlePostRender($event);

        $this->assertNull($event->outputPath);
    }

    public function testHandlePostRenderCategorizesOutputPath(): void
    {
        $event = $this->makeEvent('/out/article.html', ['category' => 'Tech']);

        $this->feature->handlePostRender($event);

        $this->assertEquals('/out/tech/article.html', $event->outputPath);
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

        $this->feature->handlePostGlob(new Event('POST_GLOB'));

        $updated = $this->container->getVariable('discovered_files');
        $this->assertEquals('tech-layout', $updated[1]['metadata']['template']);
    }
}
