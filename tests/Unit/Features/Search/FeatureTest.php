<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Search;

use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\Search\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class FeatureTest extends UnitTestCase
{
    private Feature $feature;
    private EventManager $eventManager;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/staticforge_search_feature_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->setContainerVariable('OUTPUT_DIR', $this->tempDir);
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');
        $this->setContainerVariable('site_config', []);

        $this->eventManager = new EventManager($this->container);
        $this->feature = new Feature();
        $this->feature->setContainer($this->container);
        $this->feature->register($this->eventManager);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->recursiveRemove($this->tempDir);
        }
        parent::tearDown();
    }

    private function recursiveRemove(string $dir): void
    {
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRemove("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function testRegisterRegistersBothEvents(): void
    {
        $postRenderListeners = $this->eventManager->getListeners('POST_RENDER');
        $this->assertCount(1, $postRenderListeners);
        $this->assertEquals([$this->feature, 'handlePostRender'], $postRenderListeners[0]['callback']);

        $postLoopListeners = $this->eventManager->getListeners('POST_LOOP');
        $this->assertCount(1, $postLoopListeners);
        $this->assertEquals([$this->feature, 'handlePostLoop'], $postLoopListeners[0]['callback']);
    }

    public function testGetRequiredConfigReturnsSearch(): void
    {
        $this->assertSame(['search'], $this->feature->getRequiredConfig());
    }

    public function testGetRequiredEnvReturnsEmpty(): void
    {
        $this->assertSame([], $this->feature->getRequiredEnv());
    }

    public function testGetConfigHelpForSearchKey(): void
    {
        $help = $this->feature->getConfigHelp('search');
        $this->assertNotNull($help);
        $this->assertStringContainsString('engine: minisearch', $help);
    }

    public function testGetConfigHelpForUnknownKeyReturnsNull(): void
    {
        $this->assertNull($this->feature->getConfigHelp('not_search'));
    }

    public function testHandlePostRenderCollectsPageIntoIndex(): void
    {
        $parameters = [
            'metadata' => ['title' => 'Feature Test Page'],
            'output_path' => $this->tempDir . '/page.html',
            'rendered_content' => '<p>Some searchable content.</p>',
        ];

        $this->feature->handlePostRender($this->container, $parameters);
        $this->feature->handlePostLoop($this->container, []);

        $this->assertFileExists($this->tempDir . '/search.json');
        $json = json_decode((string) file_get_contents($this->tempDir . '/search.json'), true);
        $this->assertCount(1, $json);
        $this->assertSame('Feature Test Page', $json[0]['title']);
    }

    public function testHandlePostLoopCopiesSearchAssets(): void
    {
        $this->feature->handlePostLoop($this->container, []);

        $this->assertFileExists($this->tempDir . '/assets/js/minisearch.min.js');
        $this->assertFileExists($this->tempDir . '/assets/js/search.js');
    }
}
