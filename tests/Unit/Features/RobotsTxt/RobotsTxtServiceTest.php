<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\RobotsTxt;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Features\RobotsTxt\Services\RobotsTxtService;
use EICC\StaticForge\Features\RobotsTxt\Services\RobotsTxtGenerator;
use EICC\Utils\Container;
use EICC\Utils\Log;
use org\bovigo\vfs\vfsStream;

class RobotsTxtServiceTest extends UnitTestCase
{
    private RobotsTxtService $service;
    private RobotsTxtGenerator $generator;
    private $root;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up virtual filesystem
        $this->root = vfsStream::setup('test');

        $logger = $this->container->get('logger');
        $this->generator = new RobotsTxtGenerator();
        $this->service = new RobotsTxtService($logger, $this->generator);
    }

    public function testScanForRobotsMetadataWithNoFiles(): void
    {
        $this->setContainerVariable('discovered_files', []);
        $this->setContainerVariable('SOURCE_DIR', 'content');

        $parameters = [];
        $result = $this->service->scanForRobotsMetadata($this->container, $parameters);

        $this->assertSame($parameters, $result);
    }

    public function testScanMarkdownFileWithRobotsNo(): void
    {
        // Create a markdown file with robots=no
        $content = "---\ntitle=\"Test Page\"\nrobots=\"no\"\n---\n\n# Test Content";
        $filePath = vfsStream::url('test/content/test.md');

        // Create directory
        vfsStream::create(['content' => []], $this->root);
        file_put_contents($filePath, $content);

        $this->setContainerVariable('discovered_files', [
            ['path' => $filePath, 'url' => 'test.md', 'metadata' => ['robots' => 'no']]
        ]);
        $this->setContainerVariable('SOURCE_DIR', vfsStream::url('test/content'));

        $parameters = [];
        $this->service->scanForRobotsMetadata($this->container, $parameters);
        
        // We can't easily inspect private property disallowedPaths, 
        // but we can verify generateRobotsTxt produces expected output
        
        $this->setContainerVariable('OUTPUT_DIR', vfsStream::url('test/output'));
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');
        
        $this->service->generateRobotsTxt($this->container, []);
        
        $this->assertTrue($this->root->hasChild('output/robots.txt'));
        $content = file_get_contents(vfsStream::url('test/output/robots.txt'));
        $this->assertStringContainsString('Disallow: /test.html', $content);
    }

    public function testScanMarkdownFileWithRobotsYes(): void
    {
        // Create a markdown file with robots=yes
        $content = "---\ntitle=\"Test Page\"\nrobots=\"yes\"\n---\n\n# Test Content";
        $filePath = vfsStream::url('test/content/allowed.md');

        // Create directory
        vfsStream::create(['content' => []], $this->root);
        file_put_contents($filePath, $content);

        $this->setContainerVariable('discovered_files', [
            ['path' => $filePath, 'url' => 'allowed.md', 'metadata' => ['robots' => 'yes']]
        ]);
        $this->setContainerVariable('SOURCE_DIR', vfsStream::url('test/content'));

        $this->service->scanForRobotsMetadata($this->container, []);
        
        $this->setContainerVariable('OUTPUT_DIR', vfsStream::url('test/output'));
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');
        
        $this->service->generateRobotsTxt($this->container, []);
        
        $content = file_get_contents(vfsStream::url('test/output/robots.txt'));
        $this->assertStringNotContainsString('Disallow: /allowed.html', $content);
    }

    public function testScanCategoryFileWithRobotsNo(): void
    {
        // Create a category file with robots=no
        $content = "---\ntitle=\"Secret Category\"\ntype=\"category\"\nrobots=\"no\"\n---\n\n# Secret Category";
        $filePath = vfsStream::url('test/content/secret-category.md');

        // Create directory
        vfsStream::create(['content' => []], $this->root);
        file_put_contents($filePath, $content);

        $this->setContainerVariable('discovered_files', [
            ['path' => $filePath, 'url' => 'secret-category.md', 'metadata' => ['type' => 'category', 'robots' => 'no', 'category' => 'Secret Category']]
        ]);
        $this->setContainerVariable('SOURCE_DIR', vfsStream::url('test/content'));

        $this->service->scanForRobotsMetadata($this->container, []);
        
        $this->setContainerVariable('OUTPUT_DIR', vfsStream::url('test/output'));
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');
        
        $this->service->generateRobotsTxt($this->container, []);
        
        $content = file_get_contents(vfsStream::url('test/output/robots.txt'));
        $this->assertStringContainsString('Disallow: /secret-category/', $content);
    }
}
