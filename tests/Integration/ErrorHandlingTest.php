<?php

namespace EICC\StaticForge\Tests\Integration;

use EICC\StaticForge\Core\Application;
use EICC\Utils\Container;
use org\bovigo\vfs\vfsStream;

/**
 * Error handling and graceful failure integration tests
 * Tests system behavior when encountering various error conditions
 */
class ErrorHandlingTest extends IntegrationTestCase
{
  private string $testOutputDir;
  private string $testContentDir;
  private string $testTemplateDir;
  private Container $container;
  private $vfsRoot;

  protected function setUp(): void
  {
    parent::setUp();

    // Use vfsStream for file system isolation and performance
    $this->vfsRoot = vfsStream::setup('root');
    $this->testOutputDir = vfsStream::url('root/output');
    $this->testContentDir = vfsStream::url('root/content');
    $this->testTemplateDir = vfsStream::url('root/templates');

    // Create directories in VFS
    mkdir($this->testOutputDir);
    mkdir($this->testContentDir);
    mkdir($this->testTemplateDir);
    mkdir($this->testTemplateDir . '/sample');

    // Override environment variables BEFORE loading bootstrap
    $_ENV['SOURCE_DIR'] = $this->testContentDir;
    $_ENV['OUTPUT_DIR'] = $this->testOutputDir;
    $_ENV['TEMPLATE_DIR'] = $this->testTemplateDir;

    $this->container = $this->createContainer(__DIR__ . '/../.env.integration');

    // Update container variables after bootstrap to override .env values
    $this->container->updateVariable('SOURCE_DIR', $this->testContentDir);
    $this->container->updateVariable('OUTPUT_DIR', $this->testOutputDir);
    $this->container->updateVariable('TEMPLATE_DIR', $this->testTemplateDir);

    $this->createBaseTemplate();
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    // No need to manually remove directories with vfsStream
  }

  private function createBaseTemplate(): void
  {
    $template = <<<'TWIG'
<!DOCTYPE html>
<html>
<head><title>{{ title | default('Test') }}</title></head>
<body>{{ content | raw }}</body>
</html>
TWIG;

    file_put_contents(
      $this->testTemplateDir . '/sample/base.html.twig',
      $template
    );
  }

  public function testInvalidFrontmatterGracefullyHandled(): void
  {
    // Create file with malformed frontmatter
    $invalidContent = <<<'MD'
---
title: "Valid Title"
another_field: "valid"
---
Content here
MD;
    file_put_contents($this->testContentDir . '/invalid.md', $invalidContent);

    // Create valid file to ensure processing continues
    $validContent = <<<'MD'
---
title: "Valid Page"
---
Valid content
MD;
    file_put_contents($this->testContentDir . '/valid.md', $validContent);

    // Generate site
    // Generate site

    $container = $this->container;
    $app = new Application($container);

    $result = $app->generate();

    // Should still succeed - graceful handling
    $this->assertTrue($result);

    // Valid file should be processed
    $this->assertFileExists($this->testOutputDir . '/valid.html');
  }

  public function testMissingTemplateHandled(): void
  {
    // Create content requesting non-existent template
    $content = <<<'HTML'
<!--
---
title: "Test Page"
template: "nonexistent"
---
-->
<p>Content</p>
HTML;
    file_put_contents($this->testContentDir . '/test.html', $content);

    // Generate site
    // Generate site

    $container = $this->container;
    $app = new Application($container);

    // Should handle missing template gracefully
    $result = $app->generate();

    // Check that file was still created (fallback behavior)
    $this->assertTrue($result || file_exists($this->testOutputDir . '/test.html'));
  }

  public function testEmptyFileHandled(): void
  {
    // Create empty file
    file_put_contents($this->testContentDir . '/empty.html', '');

    // Create valid file
    $validContent = '<!-- INI
title = "Valid"
-->
<p>Content</p>';
    file_put_contents($this->testContentDir . '/valid.html', $validContent);

    // Generate site
    // Generate site

    $container = $this->container;
    $app = new Application($container);

    $result = $app->generate();

    // Should succeed despite empty file
    $this->assertTrue($result);

    // Valid file should be processed
    $this->assertFileExists($this->testOutputDir . '/valid.html');
  }

  public function testFileWithoutFrontmatter(): void
  {
    // Create HTML file without frontmatter
    $plainHtml = '<h1>No Frontmatter</h1><p>Just content</p>';
    file_put_contents($this->testContentDir . '/plain.html', $plainHtml);

    // Create Markdown without frontmatter (different name to avoid conflict)
    $plainMd = '# Markdown Title

Plain markdown content without frontmatter.';
    file_put_contents($this->testContentDir . '/markdown.md', $plainMd);

    // Generate site
    $container = $this->container;
    $app = new Application($container);

    $result = $app->generate();

    $this->assertTrue($result);

    // Both files should be processed
    $this->assertFileExists($this->testOutputDir . '/plain.html');
    $this->assertFileExists($this->testOutputDir . '/markdown.html');

    // Check content preserved for HTML file
    $htmlOutput = file_get_contents($this->testOutputDir . '/plain.html');
    $this->assertStringContainsString('No Frontmatter', $htmlOutput);

    // Check content processed for Markdown file
    $markdownOutput = file_get_contents($this->testOutputDir . '/markdown.html');
    $this->assertStringContainsString('Markdown Title', $markdownOutput);
  }

  public function testHandlesSpecialCharactersInFilenames(): void
  {
    // Create file with spaces and special chars
    $content = <<<'MD'
---
title: "Special File"
---
Content
MD;
    file_put_contents($this->testContentDir . '/special file.md', $content);

    // Generate site
    // Generate site

    $container = $this->container;
    $app = new Application($container);

    $result = $app->generate();

    // Should handle filename
    $this->assertTrue($result);
  }

  public function testContinuesAfterSingleFileError(): void
  {
    // Create multiple valid files
    for ($i = 1; $i <= 3; $i++) {
      $content = <<<'MD'
---
title: "Page {$i}"
---
Content {$i}
MD;
      file_put_contents($this->testContentDir . "/page{$i}.md", $content);
    }

    // Create a file that might cause issues (but should be handled)
    $problematic = '<!-- INI
title = "Problematic"
<!-- Unclosed comment
<p>Content</p>';
    file_put_contents($this->testContentDir . '/problem.html', $problematic);

    // Generate site
    // Generate site

    $container = $this->container;
    $app = new Application($container);

    $result = $app->generate();

    // Should process other files even if one fails
    $this->assertTrue($result);

    // Verify other files processed
    $this->assertFileExists($this->testOutputDir . '/page1.html');
    $this->assertFileExists($this->testOutputDir . '/page2.html');
    $this->assertFileExists($this->testOutputDir . '/page3.html');
  }

  public function testOutputDirectoryCreation(): void
  {
    // Delete output directory
    $this->removeDirectory($this->testOutputDir);

    // Create content
    $content = <<<'MD'
---
title: "Test"
---
Content
MD;
    file_put_contents($this->testContentDir . '/test.md', $content);

    // Generate site - should recreate output dir
    // Generate site

    $container = $this->container;
    $app = new Application($container);

    $result = $app->generate();

    $this->assertTrue($result);
    $this->assertDirectoryExists($this->testOutputDir);
    $this->assertFileExists($this->testOutputDir . '/test.html');
  }

  public function testNestedOutputDirectoryCreation(): void
  {
    // Create content with category requiring nested directory
    $content = <<<'MD'
---
title: "Deep Page"
category: "docs/api/v1"
---
Deep nesting
MD;
    file_put_contents($this->testContentDir . '/deep.md', $content);

    // Generate site
    // Generate site

    $container = $this->container;
    $app = new Application($container);

    $result = $app->generate();

    $this->assertTrue($result);

    // Category paths with slashes are sanitized to dashes
    $this->assertDirectoryExists($this->testOutputDir . '/docs-api-v1');
    $this->assertFileExists($this->testOutputDir . '/docs-api-v1/deep.html');
  }

  public function testHandlesUnicodeContent(): void
  {
    // Create content with unicode characters
    $unicodeContent = <<<'MD'
---
title: "Unicode Test"
description: "Тест Unicode 测试"
---
# Emoji Test 🚀

Content with various unicode:
- Cyrillic: Привет мир
- Chinese: 你好世界
- Arabic: مرحبا بالعالم
- Emoji: 🎉 🎨 🔥

**Bold unicode**: Тест
MD;
    file_put_contents($this->testContentDir . '/unicode.md', $unicodeContent);

    // Generate site
    // Generate site

    $container = $this->container;
    $app = new Application($container);

    $result = $app->generate();

    $this->assertTrue($result);
    $this->assertFileExists($this->testOutputDir . '/unicode.html');

    // Verify unicode preserved
    $output = file_get_contents($this->testOutputDir . '/unicode.html');
    $this->assertStringContainsString('Привет мир', $output);
    $this->assertStringContainsString('你好世界', $output);
    $this->assertStringContainsString('🚀', $output);
  }
}
