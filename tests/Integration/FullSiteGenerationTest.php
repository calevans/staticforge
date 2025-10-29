<?php

namespace EICC\StaticForge\Tests\Integration;

use EICC\StaticForge\Core\Application;

/**
 * Full site generation integration tests
 * Tests complete workflow from source content to generated site
 */
class FullSiteGenerationTest extends IntegrationTestCase
{
  private string $testOutputDir;
  private string $testContentDir;
  private string $testTemplateDir;
  private string $envPath;

  protected function setUp(): void
  {
    parent::setUp();

    // Create temporary directories
    $this->testOutputDir = sys_get_temp_dir() . '/staticforge_fullsite_output_' . uniqid();
    $this->testContentDir = sys_get_temp_dir() . '/staticforge_fullsite_content_' . uniqid();
    $this->testTemplateDir = sys_get_temp_dir() . '/staticforge_fullsite_templates_' . uniqid();

    mkdir($this->testOutputDir, 0755, true);
    mkdir($this->testContentDir, 0755, true);
    mkdir($this->testContentDir . '/blog', 0755, true);
    mkdir($this->testTemplateDir . '/sample', 0755, true);

    // Create test environment
    $this->envPath = $this->createTestEnv([
      'SITE_NAME' => 'Integration Test Site',
      'SITE_BASE_URL' => 'https://test.example.com',
      'TEMPLATE' => 'sample',
      'SOURCE_DIR' => $this->testContentDir,
      'OUTPUT_DIR' => $this->testOutputDir,
      'TEMPLATE_DIR' => $this->testTemplateDir,
      'FEATURES_DIR' => 'src/Features',
      'LOG_LEVEL' => 'ERROR',
      'LOG_FILE' => sys_get_temp_dir() . '/staticforge_test_' . uniqid() . '.log',
    ]);

    // Create base template
    $this->createBaseTemplate();
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->removeDirectory($this->testOutputDir);
    $this->removeDirectory($this->testContentDir);
    $this->removeDirectory($this->testTemplateDir);
    if (file_exists($this->envPath)) {
      unlink($this->envPath);
    }
  }

  private function createBaseTemplate(): void
  {
    $template = <<<'TWIG'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ title | default('Untitled') }} | {{ site_name }}</title>
</head>
<body>
    <h1>{{ title | default('Untitled') }}</h1>
    {% if category %}<p>Category: {{ category }}</p>{% endif %}
    {% if tags %}<p>Tags: {{ tags|join(', ') }}</p>{% endif %}
    <div class="content">
        {{ content | raw }}
    </div>
</body>
</html>
TWIG;

    file_put_contents(
      $this->testTemplateDir . '/sample/base.html.twig',
      $template
    );
  }

  public function testGeneratesCompleteStaticSite(): void
  {
    // Create HTML content
    $htmlContent = <<<'HTML'
<!-- INI
title = "Home Page"
description = "Welcome to our site"
menu = 1
-->
<h2>Welcome</h2>
<p>This is the home page.</p>
HTML;
    file_put_contents($this->testContentDir . '/index.html', $htmlContent);

    // Create Markdown content
    $markdownContent = <<<'MD'
---
title = "About Us"
description = "Learn about our company"
menu = 2
---

# About Our Company

We are a **great** company doing amazing things.

- Feature 1
- Feature 2
- Feature 3
MD;
    file_put_contents($this->testContentDir . '/about.md', $markdownContent);

    // Generate site
    // Generate site

    $app = new Application($this->envPath);

    $result = $app->generate();

    // Verify generation succeeded
    $this->assertTrue($result, 'Site generation should succeed');

    // Verify output files exist
    $this->assertFileExists($this->testOutputDir . '/index.html');
    $this->assertFileExists($this->testOutputDir . '/about.html');

    // Verify HTML content
    $indexHtml = file_get_contents($this->testOutputDir . '/index.html');
    $this->assertStringContainsString('Home Page', $indexHtml);
    $this->assertStringContainsString('<h2>Welcome</h2>', $indexHtml);
    $this->assertStringContainsString('This is the home page', $indexHtml);

    // Verify Markdown was converted
    $aboutHtml = file_get_contents($this->testOutputDir . '/about.html');
    $this->assertStringContainsString('About Us', $aboutHtml);
    $this->assertStringContainsString('<strong>great</strong>', $aboutHtml);
    $this->assertStringContainsString('<li>Feature 1</li>', $aboutHtml);
  }

  public function testHandlesCategorizedContent(): void
  {
    // Create categorized HTML
    $blogPost = <<<'HTML'
<!-- INI
title = "First Blog Post"
category = "blog"
tags = [php, testing]
-->
<p>This is a blog post.</p>
HTML;
    file_put_contents($this->testContentDir . '/blog-post.html', $blogPost);

    // Create categorized Markdown
    $article = <<<'MD'
---
title = "Technical Article"
category = "blog"
tags = [development, guide]
---

# How to Build Things

This is a technical guide.
MD;
    file_put_contents($this->testContentDir . '/article.md', $article);

    // Generate site
    // Generate site

    $app = new Application($this->envPath);

    $result = $app->generate();

    $this->assertTrue($result);

    // Verify files moved to category directory
    $this->assertFileExists($this->testOutputDir . '/blog/blog-post.html');
    $this->assertFileExists($this->testOutputDir . '/blog/article.html');

    // Verify content preserved
    $blogHtml = file_get_contents($this->testOutputDir . '/blog/blog-post.html');
    $this->assertStringContainsString('First Blog Post', $blogHtml);
    $this->assertStringContainsString('blog post', $blogHtml);
  }

  public function testHandlesNestedDirectories(): void
  {
    // Create nested content structure
    mkdir($this->testContentDir . '/docs', 0755, true);
    mkdir($this->testContentDir . '/docs/api', 0755, true);

    $apiDoc = <<<'MD'
---
title = "API Documentation"
---

# API Reference

Use our API to do things.
MD;
    file_put_contents($this->testContentDir . '/docs/api/reference.md', $apiDoc);

    // Generate site
    // Generate site

    $app = new Application($this->envPath);

    $result = $app->generate();

    $this->assertTrue($result);

    // Verify nested output structure
    $this->assertFileExists($this->testOutputDir . '/docs/api/reference.html');

    $apiHtml = file_get_contents($this->testOutputDir . '/docs/api/reference.html');
    $this->assertStringContainsString('API Documentation', $apiHtml);
  }

  public function testProcessesMultipleFileTypes(): void
  {
    // Mix of HTML and Markdown
    file_put_contents($this->testContentDir . '/page1.html', '<!-- INI
title = "Page 1"
-->
<p>HTML content</p>');

    file_put_contents($this->testContentDir . '/page2.md', '---
title = "Page 2"
---
**Markdown** content');

    file_put_contents($this->testContentDir . '/page3.html', '<!-- INI
title = "Page 3"
-->
<p>More HTML</p>');

    file_put_contents($this->testContentDir . '/page4.md', '---
title = "Page 4"
---
More *Markdown*');

    // Generate site
    // Generate site

    $app = new Application($this->envPath);

    $result = $app->generate();

    $this->assertTrue($result);

    // Verify all files processed
    $this->assertFileExists($this->testOutputDir . '/page1.html');
    $this->assertFileExists($this->testOutputDir . '/page2.html');
    $this->assertFileExists($this->testOutputDir . '/page3.html');
    $this->assertFileExists($this->testOutputDir . '/page4.html');

    // Verify HTML preserved and Markdown converted
    $page1 = file_get_contents($this->testOutputDir . '/page1.html');
    $this->assertStringContainsString('<p>HTML content</p>', $page1);

    $page2 = file_get_contents($this->testOutputDir . '/page2.html');
    $this->assertStringContainsString('<strong>Markdown</strong>', $page2);

    $page4 = file_get_contents($this->testOutputDir . '/page4.html');
    $this->assertStringContainsString('<em>Markdown</em>', $page4);
  }

  public function testEmptySourceDirectory(): void
  {
    // No content files
    // Generate site

    $app = new Application($this->envPath);

    $result = $app->generate();

    // Should succeed even with no files
    $this->assertTrue($result);

    // Output directory should exist but be empty
    $this->assertDirectoryExists($this->testOutputDir);
    $files = glob($this->testOutputDir . '/*');
    $this->assertEmpty($files, 'Output directory should be empty when no content exists');
  }

  public function testPreservesMetadataAcrossRenderers(): void
  {
    // Create content with various metadata
    $content = <<<'MD'
---
title = "Metadata Test"
description = "Testing metadata preservation"
author = "Test Author"
date = "2025-10-29"
custom_field = "custom value"
tags = [test, metadata, integration]
---

# Content

Test content here.
MD;
    file_put_contents($this->testContentDir . '/metadata-test.md', $content);

    // Generate site
    // Generate site

    $app = new Application($this->envPath);

    $result = $app->generate();

    $this->assertTrue($result);

    // Verify metadata in output
    $html = file_get_contents($this->testOutputDir . '/metadata-test.html');
    $this->assertStringContainsString('Metadata Test', $html);
    $this->assertStringContainsString('test, metadata, integration', $html);
  }
}
