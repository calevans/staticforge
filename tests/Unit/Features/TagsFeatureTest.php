<?php

namespace EICC\StaticForge\Tests\Unit\Features;

use PHPUnit\Framework\TestCase;
use EICC\StaticForge\Features\Tags\Feature;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;
use org\bovigo\vfs\vfsStream;

class TagsFeatureTest extends TestCase
{
  private $root;
  private Container $container;
  private EventManager $eventManager;
  private Log $logger;

  protected function setUp(): void
  {
    // Create virtual filesystem
    $this->root = vfsStream::setup('test');

    // Create container
    $this->container = new Container();

    // Initialize logger and add to container (like EnvironmentLoader does)
    $logFile = vfsStream::url('test/test.log');
    $this->logger = new Log('tags_test', $logFile, 'INFO');
    $this->container->setVariable('logger', $this->logger);

    $this->eventManager = new EventManager($this->container);
  }

  public function testFeatureRegistration(): void
  {
    $feature = new Feature();
    $feature->register($this->eventManager, $this->container);

    $this->assertEquals('Tags', $feature->getName());

    // Check event listeners are registered
    $listeners = $this->eventManager->getListeners('POST_GLOB');
    $this->assertNotEmpty($listeners);

    $listeners = $this->eventManager->getListeners('PRE_RENDER');
    $this->assertNotEmpty($listeners);
  }

  public function testExtractsTagsFromMarkdownFiles(): void
  {
    // Create test Markdown file with tags
    $mdContent = <<<'MD'
---
title: Test Post
tags: [php, testing, unit-tests]
---

# Test Post

This is a test post.
MD;

    $mdFile = vfsStream::newFile('test.md')->at($this->root)->setContent($mdContent);

    // Set up discovered files
    $this->container->setVariable('discovered_files', [
      $mdFile->url()
    ]);

    $feature = new Feature();
    $feature->register($this->eventManager, $this->container);

    $parameters = ['features' => []];
    $result = $feature->handlePostGlob($this->container, $parameters);

    $this->assertArrayHasKey('Tags', $result['features']);
    $this->assertArrayHasKey('all_tags', $result['features']['Tags']);

    $allTags = $result['features']['Tags']['all_tags'];
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
title: Test Page
tags: web, html, frontend
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

    $this->container->setVariable('discovered_files', [
      $htmlFile->url()
    ]);

    $feature = new Feature();
    $feature->register($this->eventManager, $this->container);

    $parameters = ['features' => []];
    $result = $feature->handlePostGlob($this->container, $parameters);

    $allTags = $result['features']['Tags']['all_tags'];

    // Should contain tags from both INI frontmatter and meta keywords
    $this->assertContains('web', $allTags);
    $this->assertContains('html', $allTags);
    $this->assertContains('frontend', $allTags);
    $this->assertContains('design', $allTags);
    $this->assertContains('ui', $allTags);
  }

  public function testNormalizesTags(): void
  {
    // Create files with tags in different cases
    $md1 = <<<'MD'
---
title: Post 1
tags: [PHP, Testing]
---
Content
MD;

    $md2 = <<<'MD'
---
title: Post 2
tags: [php, TESTING, Web]
---
Content
MD;

    $file1 = vfsStream::newFile('post1.md')->at($this->root)->setContent($md1);
    $file2 = vfsStream::newFile('post2.md')->at($this->root)->setContent($md2);

    $this->container->setVariable('discovered_files', [
      $file1->url(),
      $file2->url()
    ]);

    $feature = new Feature();
    $feature->register($this->eventManager, $this->container);

    $parameters = ['features' => []];
    $result = $feature->handlePostGlob($this->container, $parameters);

    $allTags = $result['features']['Tags']['all_tags'];

    // All tags should be lowercase and unique
    $this->assertContains('php', $allTags);
    $this->assertContains('testing', $allTags);
    $this->assertContains('web', $allTags);
    $this->assertCount(3, $allTags); // Only 3 unique tags
  }

  public function testBuildsTagIndex(): void
  {
    $md1 = <<<'MD'
---
title: Post 1
tags: [php, testing]
---
Content
MD;

    $md2 = <<<'MD'
---
title: Post 2
tags: [php, web]
---
Content
MD;

    $file1 = vfsStream::newFile('post1.md')->at($this->root)->setContent($md1);
    $file2 = vfsStream::newFile('post2.md')->at($this->root)->setContent($md2);

    $this->container->setVariable('discovered_files', [
      $file1->url(),
      $file2->url()
    ]);

    $feature = new Feature();
    $feature->register($this->eventManager, $this->container);

    $parameters = ['features' => []];
    $result = $feature->handlePostGlob($this->container, $parameters);

    $tagIndex = $result['features']['Tags']['tag_index'];

    // PHP tag should have 2 files
    $this->assertArrayHasKey('php', $tagIndex);
    $this->assertCount(2, $tagIndex['php']);

    // Testing tag should have 1 file
    $this->assertArrayHasKey('testing', $tagIndex);
    $this->assertCount(1, $tagIndex['testing']);

    // Web tag should have 1 file
    $this->assertArrayHasKey('web', $tagIndex);
    $this->assertCount(1, $tagIndex['web']);
  }

  public function testCalculatesTagCounts(): void
  {
    $md1 = <<<'MD'
---
tags: [php]
---
Content 1
MD;
    $md2 = <<<'MD'
---
tags: [php]
---
Content 2
MD;
    $md3 = <<<'MD'
---
tags: [web]
---
Content 3
MD;

    $file1 = vfsStream::newFile('post1.md')->at($this->root)->setContent($md1);
    $file2 = vfsStream::newFile('post2.md')->at($this->root)->setContent($md2);
    $file3 = vfsStream::newFile('post3.md')->at($this->root)->setContent($md3);

    $this->container->setVariable('discovered_files', [
      $file1->url(),
      $file2->url(),
      $file3->url()
    ]);

    $feature = new Feature();
    $feature->register($this->eventManager, $this->container);

    $parameters = ['features' => []];
    $result = $feature->handlePostGlob($this->container, $parameters);

    $tagCounts = $result['features']['Tags']['tag_counts'];

    $this->assertEquals(2, $tagCounts['php']);
    $this->assertEquals(1, $tagCounts['web']);

    // Should be sorted by count descending
    $keys = array_keys($tagCounts);
    $this->assertEquals('php', $keys[0]); // PHP has most counts
  }

  public function testAddsTagDataToPreRender(): void
  {
    // First run POST_GLOB to collect tags
    $md1 = <<<'MD'
---
title: Post 1
tags: [php, testing]
---
MD;
    $md2 = <<<'MD'
---
title: Post 2
tags: [php, web]
---
MD;

    $file1 = vfsStream::newFile('post1.md')->at($this->root)->setContent($md1);
    $file2 = vfsStream::newFile('post2.md')->at($this->root)->setContent($md2);

    $this->container->setVariable('discovered_files', [
      $file1->url(),
      $file2->url()
    ]);

    $feature = new Feature();
    $feature->register($this->eventManager, $this->container);

    $postGlobParams = ['features' => []];
    $feature->handlePostGlob($this->container, $postGlobParams);

    // Now test PRE_RENDER
    $preRenderParams = [
      'file_path' => $file1->url(),
      'metadata' => [
        'title' => 'Post 1',
        'tags' => ['php', 'testing']
      ]
    ];

    $result = $feature->handlePreRender($this->container, $preRenderParams);

    $this->assertArrayHasKey('tag_data', $result);
    $this->assertArrayHasKey('tags', $result['tag_data']);
    $this->assertArrayHasKey('related_files', $result['tag_data']);
    $this->assertArrayHasKey('all_tags', $result['tag_data']);

    // Check that tags are present
    $this->assertContains('php', $result['tag_data']['tags']);
    $this->assertContains('testing', $result['tag_data']['tags']);
  }

  public function testFindsRelatedFilesByTags(): void
  {
    $md1 = <<<'MD'
---
title: Post 1
tags: [php, testing, web]
---
Content 1
MD;
    $md2 = <<<'MD'
---
title: Post 2
tags: [php, testing]
---
Content 2
MD;
    $md3 = <<<'MD'
---
title: Post 3
tags: [python]
---
Content 3
MD;

    $file1 = vfsStream::newFile('post1.md')->at($this->root)->setContent($md1);
    $file2 = vfsStream::newFile('post2.md')->at($this->root)->setContent($md2);
    $file3 = vfsStream::newFile('post3.md')->at($this->root)->setContent($md3);

    $this->container->setVariable('discovered_files', [
      $file1->url(),
      $file2->url(),
      $file3->url()
    ]);

    $feature = new Feature();
    $feature->register($this->eventManager, $this->container);

    // Collect tags
    $postGlobParams = ['features' => []];
    $feature->handlePostGlob($this->container, $postGlobParams);

    // Get related files for post1
    $preRenderParams = [
      'file_path' => $file1->url(),
      'metadata' => [
        'tags' => ['php', 'testing', 'web']
      ]
    ];

    $result = $feature->handlePreRender($this->container, $preRenderParams);

    $relatedFiles = $result['tag_data']['related_files'];

    // Post2 should be related (shares 2 tags: php, testing)
    $this->assertContains($file2->url(), $relatedFiles);

    // Post3 should not be related (shares no tags)
    $this->assertNotContains($file3->url(), $relatedFiles);

    // Should not include itself
    $this->assertNotContains($file1->url(), $relatedFiles);
  }

  public function testHandlesFilesWithoutTags(): void
  {
    $mdContent = <<<'MD'
---
title: Test Post
---

# No Tags Here
MD;

    $mdFile = vfsStream::newFile('test.md')->at($this->root)->setContent($mdContent);

    $this->container->setVariable('discovered_files', [
      $mdFile->url()
    ]);

    $feature = new Feature();
    $feature->register($this->eventManager, $this->container);

    $parameters = ['features' => []];
    $result = $feature->handlePostGlob($this->container, $parameters);

    $this->assertArrayHasKey('Tags', $result['features']);
    $this->assertEmpty($result['features']['Tags']['all_tags']);
    $this->assertEmpty($result['features']['Tags']['tag_index']);
  }

  public function testHandlesStringTagInMetadata(): void
  {
    $feature = new Feature();
    $feature->register($this->eventManager, $this->container);

    // Mock POST_GLOB to set up tags
    $this->container->setVariable('discovered_files', []);
    $feature->handlePostGlob($this->container, ['features' => []]);

    // Test with string tag instead of array
    $preRenderParams = [
      'file_path' => 'test.md',
      'metadata' => [
        'tags' => 'single-tag'  // String instead of array
      ]
    ];

    $result = $feature->handlePreRender($this->container, $preRenderParams);

    $this->assertArrayHasKey('tag_data', $result);
    $this->assertIsArray($result['tag_data']['tags']);
    $this->assertContains('single-tag', $result['tag_data']['tags']);
  }
}
