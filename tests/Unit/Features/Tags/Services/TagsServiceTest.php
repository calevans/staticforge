<?php

namespace EICC\StaticForge\Tests\Unit\Features\Tags\Services;

use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Features\Tags\Services\TagsService;
use EICC\Utils\Container;
use EICC\Utils\Log;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

class TagsServiceTest extends UnitTestCase
{
    private vfsStreamDirectory $root;
    private Log $logger;
    private TagsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create virtual filesystem
        $this->root = vfsStream::setup('test');
        $this->logger = $this->container->get('logger');
        $this->service = new TagsService($this->logger, $this->container);
    }

    public function testExtractsTagsFromMarkdownFiles(): void
    {
        // Create test Markdown file with tags
        $mdContent = <<<'MD'
---
title = Test Post
tags = [php, testing, unit-tests]
---

# Test Post

This is a test post.
MD;

        $mdFile = vfsStream::newFile('test.md')->at($this->root)->setContent($mdContent);

        // Set up discovered files
        $this->setContainerVariable('discovered_files', [
            ['path' => $mdFile->url(), 'url' => 'test.md', 'metadata' => ['tags' => ['php', 'testing', 'unit-tests']]]
        ]);

        $this->service->handlePostGlob(new Event('POST_GLOB'));

        $allTags = $this->service->getAllTagsSorted();
        $this->assertContains('php', $allTags);
        $this->assertContains('testing', $allTags);
        $this->assertContains('unit-tests', $allTags);
        $this->assertCount(3, $allTags);
    }

    public function testExtractsTagsFromHtmlFiles(): void
    {
        // Create test HTML file with INI frontmatter
        $htmlContent = <<<'HTML'
<!-- INI
title = Test Page
tags = web, html, frontend
-->

<!DOCTYPE html>
<html>
<head>
  <meta name="keywords" content="design, ui">
  <title>Test Page</title>
</head>
<body>
  <h1>Test Page</h1>
</body>
</html>
HTML;
        $htmlFile = vfsStream::newFile('test.html')->at($this->root)->setContent($htmlContent);

        // Set up discovered files
        $this->setContainerVariable('discovered_files', [
            ['path' => $htmlFile->url(), 'url' => 'test.html', 'metadata' => ['tags' => ['web', 'html', 'frontend']]]
        ]);

        $this->service->handlePostGlob(new Event('POST_GLOB'));

        $allTags = $this->service->getAllTagsSorted();
        $this->assertContains('web', $allTags);
        $this->assertContains('html', $allTags);
        $this->assertContains('frontend', $allTags);
        $this->assertCount(3, $allTags);
    }

    public function testHandlesCommaSeparatedTags(): void
    {
        // Create test file with comma-separated tags string
        $mdContent = <<<'MD'
---
title = Test Post
tags = php, testing, unit-tests
---
MD;
        $mdFile = vfsStream::newFile('test.md')->at($this->root)->setContent($mdContent);

        // Set up discovered files
        $this->setContainerVariable('discovered_files', [
            ['path' => $mdFile->url(), 'url' => 'test.md', 'metadata' => ['tags' => 'php, testing, unit-tests']]
        ]);

        $this->service->handlePostGlob(new Event('POST_GLOB'));

        $allTags = $this->service->getAllTagsSorted();
        $this->assertContains('php', $allTags);
        $this->assertContains('testing', $allTags);
        $this->assertContains('unit-tests', $allTags);
    }

    public function testNormalizesTags(): void
    {
        // Create test file with mixed case and whitespace tags
        $mdContent = <<<'MD'
---
title = Test Post
tags = [PHP,  Testing , Unit-Tests]
---
MD;
        $mdFile = vfsStream::newFile('test.md')->at($this->root)->setContent($mdContent);

        // Set up discovered files
        $this->setContainerVariable('discovered_files', [
            ['path' => $mdFile->url(), 'url' => 'test.md', 'metadata' => ['tags' => ['PHP', ' Testing ', 'Unit-Tests']]]
        ]);

        $this->service->handlePostGlob(new Event('POST_GLOB'));

        $allTags = $this->service->getAllTagsSorted();
        $this->assertContains('php', $allTags);
        $this->assertContains('testing', $allTags);
        $this->assertContains('unit-tests', $allTags);
    }

    public function testGeneratesTagIndexAndCounts(): void
    {
        // Create multiple files with overlapping tags
        $file1 = vfsStream::newFile('post1.md')->at($this->root)->setContent('');
        $file2 = vfsStream::newFile('post2.md')->at($this->root)->setContent('');
        $file3 = vfsStream::newFile('post3.md')->at($this->root)->setContent('');

        $files = [
            ['path' => $file1->url(), 'url' => 'post1.md', 'metadata' => ['tags' => ['php', 'web']]],
            ['path' => $file2->url(), 'url' => 'post2.md', 'metadata' => ['tags' => ['php', 'testing']]],
            ['path' => $file3->url(), 'url' => 'post3.md', 'metadata' => ['tags' => ['web', 'design']]]
        ];

        $this->setContainerVariable('discovered_files', $files);

        $this->service->handlePostGlob(new Event('POST_GLOB'));

        // Check tag index
        $index = $this->service->getTagIndex();

        $this->assertCount(2, $index['php']);
        $this->assertContains($file1->url(), $index['php']);
        $this->assertContains($file2->url(), $index['php']);

        $this->assertCount(2, $index['web']);
        $this->assertContains($file1->url(), $index['web']);
        $this->assertContains($file3->url(), $index['web']);

        $this->assertCount(1, $index['testing']);
        $this->assertContains($file2->url(), $index['testing']);

        // Check tag counts
        $counts = $this->service->getTagCounts();

        $this->assertEquals(2, $counts['php']);
        $this->assertEquals(2, $counts['web']);
        $this->assertEquals(1, $counts['testing']);
        $this->assertEquals(1, $counts['design']);
    }

    public function testInjectsRelatedFiles(): void
    {
        // Create files with shared tags
        $file1 = vfsStream::newFile('post1.md')->at($this->root)->setContent('');
        $file2 = vfsStream::newFile('post2.md')->at($this->root)->setContent('');
        $file3 = vfsStream::newFile('post3.md')->at($this->root)->setContent('');

        $files = [
            ['path' => $file1->url(), 'url' => 'post1.md', 'metadata' => ['tags' => ['php', 'web']]],
            ['path' => $file2->url(), 'url' => 'post2.md', 'metadata' => ['tags' => ['php', 'testing']]],
            ['path' => $file3->url(), 'url' => 'post3.md', 'metadata' => ['tags' => ['web', 'design']]]
        ];

        $this->setContainerVariable('discovered_files', $files);

        // Run POST_GLOB first to build index
        $this->service->handlePostGlob(new Event('POST_GLOB'));

        // Test PRE_RENDER for post1.md (should be related to post2 via 'php' and post3 via 'web')
        $event = new RenderEvent(
            name: 'PRE_RENDER',
            filePath: $file1->url(),
            fileUrl: '',
            metadata: ['tags' => ['php', 'web']],
        );

        $this->service->handlePreRender($event);

        $this->assertArrayHasKey('tag_data', $event->extra);
        $tagData = $event->extra['tag_data'];

        $this->assertArrayHasKey('related_files', $tagData);
        $related = $tagData['related_files'];

        $this->assertContains($file2->url(), $related);
        $this->assertContains($file3->url(), $related);
        $this->assertNotContains($file1->url(), $related); // Should not contain self
    }
}
