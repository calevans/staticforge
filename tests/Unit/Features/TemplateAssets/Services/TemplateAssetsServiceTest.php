<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\TemplateAssets\Services;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Features\TemplateAssets\Services\TemplateAssetsService;
use EICC\Utils\Container;
use EICC\Utils\Log;
use org\bovigo\vfs\vfsStream;

class TemplateAssetsServiceTest extends UnitTestCase
{
    private TemplateAssetsService $service;
    private Log $logger;
    private $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = vfsStream::setup('root');
        $this->logger = $this->container->get('logger');
        $this->service = new TemplateAssetsService($this->logger);
    }

    public function testHandlePostLoopCopiesAssets(): void
    {
        // Setup directories
        $templateDir = vfsStream::newDirectory('templates')->at($this->root);
        $sampleTemplate = vfsStream::newDirectory('sample')->at($templateDir);
        $templateAssets = vfsStream::newDirectory('assets')->at($sampleTemplate);
        vfsStream::newFile('style.css')->at($templateAssets)->setContent('body { color: red; }');

        $contentDir = vfsStream::newDirectory('content')->at($this->root);
        $contentAssets = vfsStream::newDirectory('assets')->at($contentDir);
        vfsStream::newFile('custom.js')->at($contentAssets)->setContent('console.log("hello");');

        $outputDir = vfsStream::newDirectory('output')->at($this->root);

        // Configure container
        $this->setContainerVariable('TEMPLATE_DIR', $templateDir->url());
        $this->setContainerVariable('TEMPLATE', 'sample');
        $this->setContainerVariable('OUTPUT_DIR', $outputDir->url());
        $this->setContainerVariable('SOURCE_DIR', $contentDir->url());

        // Run service
        $this->service->handlePostLoop($this->container, []);

        // Verify assets copied
        $this->assertTrue($this->root->hasChild('output/assets/style.css'));
        $this->assertTrue($this->root->hasChild('output/assets/custom.js'));
    }

    public function testHandlePostLoopBundlesCss(): void
    {
        // Setup directories
        $templateDir = vfsStream::newDirectory('templates')->at($this->root);
        $sampleTemplate = vfsStream::newDirectory('sample')->at($templateDir);
        $templateAssets = vfsStream::newDirectory('assets')->at($sampleTemplate);
        $cssDir = vfsStream::newDirectory('css')->at($templateAssets);

        vfsStream::newFile('variables.css')->at($cssDir)->setContent(':root { --main-color: blue; }');
        vfsStream::newFile('main.css')->at($cssDir)->setContent("@import 'variables.css';\nbody { color: var(--main-color); }");

        $outputDir = vfsStream::newDirectory('output')->at($this->root);

        // Configure container
        $this->setContainerVariable('TEMPLATE_DIR', $templateDir->url());
        $this->setContainerVariable('TEMPLATE', 'sample');
        $this->setContainerVariable('OUTPUT_DIR', $outputDir->url());
        $this->setContainerVariable('SOURCE_DIR', $this->root->url() . '/content'); // Empty content dir

        // Run service
        $this->service->handlePostLoop($this->container, []);

        // Verify CSS bundled
        $this->assertTrue($this->root->hasChild('output/assets/css/main.css'));
        $bundledContent = file_get_contents($outputDir->url() . '/assets/css/main.css');

        $this->assertStringContainsString('/* Import: variables.css */', $bundledContent);
        $this->assertStringContainsString(':root { --main-color: blue; }', $bundledContent);
        $this->assertStringContainsString('body { color: var(--main-color); }', $bundledContent);
    }

    public function testHandlePostLoopHandlesMissingDirs(): void
    {
        // Setup minimal directories (no assets)
        $templateDir = vfsStream::newDirectory('templates')->at($this->root);
        $outputDir = vfsStream::newDirectory('output')->at($this->root);

        // Configure container
        $this->setContainerVariable('TEMPLATE_DIR', $templateDir->url());
        $this->setContainerVariable('TEMPLATE', 'sample');
        $this->setContainerVariable('OUTPUT_DIR', $outputDir->url());
        $this->setContainerVariable('SOURCE_DIR', $this->root->url() . '/content');

        // Run service - should not throw exception
        $this->service->handlePostLoop($this->container, []);

        // Verify output dir exists but no assets
        $this->assertFalse($this->root->hasChild('output/assets'));
    }
}
