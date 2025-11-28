<?php

namespace EICC\StaticForge\Tests\Integration;

use EICC\StaticForge\Core\Application;
use EICC\Utils\Container;

/**
 * Feature interaction integration tests
 * Tests how different features work together through the event pipeline
 */
class FeatureInteractionTest extends IntegrationTestCase
{
  private string $testOutputDir;
  private string $testContentDir;
  private string $testTemplateDir;
  private Container $container;

  protected function setUp(): void
  {
    parent::setUp();

    $this->testOutputDir = sys_get_temp_dir() . '/staticforge_interaction_output_' . uniqid();
    $this->testContentDir = sys_get_temp_dir() . '/staticforge_interaction_content_' . uniqid();
    $this->testTemplateDir = sys_get_temp_dir() . '/staticforge_interaction_templates_' . uniqid();

    mkdir($this->testOutputDir, 0755, true);
    mkdir($this->testContentDir, 0755, true);
    mkdir($this->testTemplateDir . '/sample', 0755, true);

    // Override environment variables BEFORE loading bootstrap
    $_ENV['SOURCE_DIR'] = $this->testContentDir;
    $_ENV['OUTPUT_DIR'] = $this->testOutputDir;
    $_ENV['TEMPLATE_DIR'] = $this->testTemplateDir;

    $this->container = $this->createContainer(__DIR__ . '/../.env.integration');

    $this->createTemplateWithAllFeatures();
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->removeDirectory($this->testOutputDir);
    $this->removeDirectory($this->testContentDir);
    $this->removeDirectory($this->testTemplateDir);
  }

  private function createTemplateWithAllFeatures(): void
  {
    $template = <<<'TWIG'
<!DOCTYPE html>
<html>
<head>
    <title>{{ title | default('Untitled') }}</title>
</head>
<body>
    <h1>{{ title }}</h1>
    {% if category %}<div class="category">Category: {{ category }}</div>{% endif %}
    {% if tags is defined and tags %}
        {% if tags is iterable %}
            {% if tags|length > 0 %}
            <div class="tags">Tags:
            {% for tag in tags %}
                <span class="tag">{{ tag }}</span>
            {% endfor %}
            </div>
            {% endif %}
        {% else %}
            <div class="tags">Tags: {{ tags }}</div>
        {% endif %}
    {% endif %}
    {% if features.MenuBuilder.html %}
    <nav class="menu">{{ features.MenuBuilder.html | join('') | raw }}</nav>
    {% endif %}
    <main>{{ content | raw }}</main>
    {% if features.Tags.related_files %}
    <aside class="related">
        <h3>Related Content</h3>
        <ul>
        {% for file in features.Tags.related_files %}
            <li>{{ file.title }}</li>
        {% endfor %}
        </ul>
    </aside>
    {% endif %}
</body>
</html>
TWIG;

    file_put_contents(
      $this->testTemplateDir . '/sample/base.html.twig',
      $template
    );

    // Create index template for category pages
    $indexTemplate = <<<'TWIG'
<!DOCTYPE html>
<html>
<head>
    <title>{{ title | default('Category Index') }}</title>
</head>
<body>
    <h1>{{ title }}</h1>
    <div id="category-files" data-per-page="{{ per_page | default(10) }}">
    {% if features.CategoryIndex.category_files %}
        {% for file in features.CategoryIndex.category_files %}
            <div class="file-item">
                <h3><a href="{{ file.url }}">{{ file.title }}</a></h3>
            </div>
        {% endfor %}
    {% endif %}
    </div>
</body>
</html>
TWIG;

    file_put_contents(
      $this->testTemplateDir . '/sample/index.html.twig',
      $indexTemplate
    );
  }

  public function testMenuAndCategoriesInteraction(): void
  {
    // Create category definition
    $categoryDef = <<<'MD'
---
type: category
title: "Articles"
menu: 1
per_page: 5
---
Article listing page
MD;
    file_put_contents($this->testContentDir . '/articles.md', $categoryDef);

    // Create categorized content
    $article1 = <<<'HTML'
<!--
---
title: "First Article"
category: "articles"
menu: 2
---
-->
<p>Article content 1</p>
HTML;
    file_put_contents($this->testContentDir . '/article1.html', $article1);

    $article2 = <<<'MD'
---
title: "Second Article"
category: "articles"
menu: 3
---
Article content 2
MD;
    file_put_contents($this->testContentDir . '/article2.md', $article2);

    // Generate site
    // Generate site

    $container = $this->container;
    $app = new Application($container);

    $this->expectOutputString('');
    $result = $app->generate();

    $this->assertTrue($result);

    // Verify category directory created
    $this->assertDirectoryExists($this->testOutputDir . '/articles');

    // Verify files moved to category
    $this->assertFileExists($this->testOutputDir . '/articles/article1.html');
    $this->assertFileExists($this->testOutputDir . '/articles/article2.html');
  }

  public function testTagsAndRelatedContent(): void
  {
    // Create content with shared tags
    $post1 = <<<'MD'
---
title: "PHP Tutorial"
tags:
  - php
  - programming
  - tutorial
---
Learn PHP basics
MD;
    file_put_contents($this->testContentDir . '/php-tutorial.md', $post1);

    $post2 = <<<'MD'
---
title: "Advanced PHP"
tags:
  - php
  - programming
  - advanced
---
Advanced PHP concepts
MD;
    file_put_contents($this->testContentDir . '/php-advanced.md', $post2);

    $post3 = <<<'MD'
---
title: "JavaScript Guide"
tags:
  - javascript
  - programming
  - tutorial
---
JavaScript fundamentals
MD;
    file_put_contents($this->testContentDir . '/js-guide.md', $post3);

    // Generate site
    // Generate site

    $container = $this->container;
    $app = new Application($container);

    $this->expectOutputString('');
    $result = $app->generate();

    $this->assertTrue($result);

    // Verify all files generated
    $this->assertFileExists($this->testOutputDir . '/php-tutorial.html');
    $this->assertFileExists($this->testOutputDir . '/php-advanced.html');
    $this->assertFileExists($this->testOutputDir . '/js-guide.html');

    // Check that PHP tutorial has related content
    $tutorialHtml = file_get_contents($this->testOutputDir . '/php-tutorial.html');
    $this->assertStringContainsString('php', $tutorialHtml);
    $this->assertStringContainsString('programming', $tutorialHtml);
    $this->assertStringContainsString('tutorial', $tutorialHtml);
  }

  public function testCategoryIndexWithPagination(): void
  {
    // Create category definition with pagination
    $categoryDef = <<<'MD'
---
type: category
title: "Blog Posts"
menu: 1
per_page: 3
template: index
---
Blog post listing
MD;
    file_put_contents($this->testContentDir . '/blog.md', $categoryDef);

    // Create multiple blog posts
    for ($i = 1; $i <= 7; $i++) {
      $post = <<<'MD'
---
title: "Blog Post {$i}"
category: "blog"
---
Content for post {$i}
MD;
      file_put_contents($this->testContentDir . "/post{$i}.md", $post);
    }

    // Generate site
    // Generate site

    $container = $this->container;
    $app = new Application($container);

    $this->expectOutputString('');
    $result = $app->generate();

    $this->assertTrue($result);

    // Verify all posts moved to category
    for ($i = 1; $i <= 7; $i++) {
      $this->assertFileExists($this->testOutputDir . "/blog/post{$i}.html");
    }
  }

  public function testComplexFeaturePipeline(): void
  {
    // Create content that exercises multiple features
    $complexContent = <<<'MD'
---
title: "Complex Page"
description: "Tests multiple features"
category: "docs"
tags:
  - feature
  - test
  - integration
menu: 5
author: "Test Suite"
---
# Complex Content

This page tests:
- **Menu** integration
- *Category* organization
- Tag indexing
- Markdown rendering

## More Content

Multiple sections to verify rendering.
MD;
    file_put_contents($this->testContentDir . '/complex.md', $complexContent);

    // Create related content
    $related = <<<'MD'
---
title: "Related Page"
category: "docs"
tags:
  - feature
  - integration
menu: 6
---
Related content
MD;
    file_put_contents($this->testContentDir . '/related.md', $related);

    // Generate site
    // Generate site

    $container = $this->container;
    $app = new Application($container);

    $this->expectOutputString('');
    $result = $app->generate();

    $this->assertTrue($result);

    // Verify categorization
    $this->assertFileExists($this->testOutputDir . '/docs/complex.html');
    $this->assertFileExists($this->testOutputDir . '/docs/related.html');

    // Verify all features applied
    $complexHtml = file_get_contents($this->testOutputDir . '/docs/complex.html');

    // Check metadata
    $this->assertStringContainsString('Complex Page', $complexHtml);
    $this->assertStringContainsString('docs', $complexHtml);

    // Check tags
    $this->assertStringContainsString('feature', $complexHtml);
    $this->assertStringContainsString('test', $complexHtml);
    $this->assertStringContainsString('integration', $complexHtml);

    // Check Markdown rendering
    $this->assertMatchesRegularExpression('/<h1>\s*Complex Content/', $complexHtml);
    $this->assertStringContainsString('<strong>Menu</strong>', $complexHtml);
    $this->assertStringContainsString('<em>Category</em>', $complexHtml);
    $this->assertMatchesRegularExpression('/<h2>\s*More Content/', $complexHtml);
  }

  public function testEventPipelineOrder(): void
  {
    // Create simple content
    $content = <<<'MD'
---
title: "Pipeline Test"
menu: 1
---
Testing event pipeline
MD;
    file_put_contents($this->testContentDir . '/pipeline.md', $content);

    // Generate and verify no errors
    // Generate site

    $container = $this->container;
    $app = new Application($container);

    $this->expectOutputString('');
    $result = $app->generate();

    $this->assertTrue($result, 'Event pipeline should complete successfully');
    $this->assertFileExists($this->testOutputDir . '/pipeline.html');
  }
}
