<?php

namespace EICC\StaticForge\Tests\Integration;

use EICC\StaticForge\Core\Application;

/**
 * Error handling and graceful failure integration tests
 * Tests system behavior when encountering various error conditions
 */
class ErrorHandlingTest extends IntegrationTestCase
{
  private string $testOutputDir;
  private string $testContentDir;
  private string $testTemplateDir;
  private string $envPath;

  protected function setUp(): void
  {
    parent::setUp();

    $this->testOutputDir = sys_get_temp_dir() . '/staticforge_error_output_' . uniqid();
    $this->testContentDir = sys_get_temp_dir() . '/staticforge_error_content_' . uniqid();
    $this->testTemplateDir = sys_get_temp_dir() . '/staticforge_error_templates_' . uniqid();

    mkdir($this->testOutputDir, 0755, true);
    mkdir($this->testContentDir, 0755, true);
    mkdir($this->testTemplateDir . '/sample', 0755, true);

    $this->envPath = $this->createTestEnv([
      'SITE_NAME' => 'Error Test Site',
      'SITE_BASE_URL' => 'https://error.test',
      'TEMPLATE' => 'sample',
      'SOURCE_DIR' => $this->testContentDir,
      'OUTPUT_DIR' => $this->testOutputDir,
      'TEMPLATE_DIR' => $this->testTemplateDir,
      'FEATURES_DIR' => 'src/Features',
      'LOG_LEVEL' => 'ERROR',
      'LOG_FILE' => sys_get_temp_dir() . '/staticforge_test_' . uniqid() . '.log',
    ]);

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
title = "Valid Title"
invalid line without equals
another_field = "valid"
---

Content here
MD;
    file_put_contents($this->testContentDir . '/invalid.md', $invalidContent);

    // Create valid file to ensure processing continues
    $validContent = <<<'MD'
---
title = "Valid Page"
---

Valid content
MD;
    file_put_contents($this->testContentDir . '/valid.md', $validContent);

    // Generate site
    // Generate site

    $app = new Application($this->envPath);

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
<!-- INI
title = "Test Page"
template = "nonexistent"
-->
<p>Content</p>
HTML;
    file_put_contents($this->testContentDir . '/test.html', $content);

    // Generate site
    // Generate site

    $app = new Application($this->envPath);

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

    $app = new Application($this->envPath);

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

    // Create Markdown without frontmatter
    $plainMd = '# Markdown Title

Plain markdown content without frontmatter.';
    file_put_contents($this->testContentDir . '/plain.md', $plainMd);

    // Generate site
    // Generate site

    $app = new Application($this->envPath);

    $result = $app->generate();

    $this->assertTrue($result);

    // Both files should be processed
    $this->assertFileExists($this->testOutputDir . '/plain.html');
    $this->assertFileExists($this->testOutputDir . '/plain.html');

    // Check content preserved
    $htmlOutput = file_get_contents($this->testOutputDir . '/plain.html');
    $this->assertStringContainsString('No Frontmatter', $htmlOutput);
  }

  public function testHandlesSpecialCharactersInFilenames(): void
  {
    // Create file with spaces and special chars
    $content = <<<'MD'
---
title = "Special File"
---

Content
MD;
    file_put_contents($this->testContentDir . '/special file.md', $content);

    // Generate site
    // Generate site

    $app = new Application($this->envPath);

    $result = $app->generate();

    // Should handle filename
    $this->assertTrue($result);
  }

  public function testContinuesAfterSingleFileError(): void
  {
    // Create multiple valid files
    for ($i = 1; $i <= 3; $i++) {
      $content = <<<MD
---
title = "Page {$i}"
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

    $app = new Application($this->envPath);

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
title = "Test"
---

Content
MD;
    file_put_contents($this->testContentDir . '/test.md', $content);

    // Generate site - should recreate output dir
    // Generate site

    $app = new Application($this->envPath);

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
title = "Deep Page"
category = "docs/api/v1"
---

Deep nesting
MD;
    file_put_contents($this->testContentDir . '/deep.md', $content);

    // Generate site
    // Generate site

    $app = new Application($this->envPath);

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
title = "Unicode Test"
description = "Тест Unicode 测试"
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

    $app = new Application($this->envPath);

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
