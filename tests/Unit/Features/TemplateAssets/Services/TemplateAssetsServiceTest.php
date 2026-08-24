<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\TemplateAssets\Services;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Features\TemplateAssets\Services\TemplateAssetsService;
use EICC\Utils\Container;
use EICC\Utils\Log;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

class TemplateAssetsServiceTest extends UnitTestCase
{
    private TemplateAssetsService $service;
    private Log $logger;
    private vfsStreamDirectory $root;

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
        $this->assertNotFalse($bundledContent, 'Expected bundled main.css to be readable');

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

    public function testHandlePostLoopRejectsCssImportPathTraversal(): void
    {
        // vfsStream's URL scheme bypasses PathGuard's jail check entirely (by
        // design, same as the rest of the codebase's vfs:// escape hatch), so
        // this needs a real filesystem to actually exercise the boundary check.
        $baseDir = sys_get_temp_dir() . '/staticforge_css_jail_' . uniqid();
        $templateDir = $baseDir . '/templates';
        $cssDir = $templateDir . '/sample/assets/css';
        $outputDir = $baseDir . '/output';
        $secretDir = $baseDir . '/secret';

        mkdir($cssDir, 0755, true);
        mkdir($secretDir, 0755, true);
        file_put_contents($secretDir . '/leaked.css', 'body { background: url(leaked-data); }');
        file_put_contents(
            $cssDir . '/main.css',
            "@import '../../../../secret/leaked.css';\nbody { color: red; }"
        );

        $this->setContainerVariable('TEMPLATE_DIR', $templateDir);
        $this->setContainerVariable('TEMPLATE', 'sample');
        $this->setContainerVariable('OUTPUT_DIR', $outputDir);
        $this->setContainerVariable('SOURCE_DIR', $baseDir . '/content');

        $this->service->handlePostLoop($this->container, []);

        $bundledContent = file_get_contents($outputDir . '/assets/css/main.css');
        $this->assertNotFalse($bundledContent);
        $this->assertStringNotContainsString('leaked-data', $bundledContent);
        $this->assertStringContainsString("@import '../../../../secret/leaked.css';", $bundledContent);

        $this->recursiveRemove($baseDir);
    }

    private function recursiveRemove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRemove("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }
}
