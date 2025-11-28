<?php

namespace EICC\StaticForge\Tests\Unit\Features\MarkdownRenderer;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Features\MarkdownRenderer\Feature;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Unit tests for Markdown rendering functionality
 */
class FeatureTest extends UnitTestCase
{
    private Feature $feature;

    private string $testSourceDir;
    private string $testOutputDir;
    private string $testTemplateDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test directories
        $this->testSourceDir = sys_get_temp_dir() . '/staticforge_source_' . uniqid();
        $this->testOutputDir = sys_get_temp_dir() . '/staticforge_output_' . uniqid();
        $this->testTemplateDir = sys_get_temp_dir() . '/staticforge_templates_' . uniqid();

        mkdir($this->testSourceDir, 0755, true);
        mkdir($this->testOutputDir, 0755, true);
        mkdir($this->testTemplateDir . '/test', 0755, true);

        // Configure container with test paths
        $this->setContainerVariable('SOURCE_DIR', $this->testSourceDir);
        $this->setContainerVariable('OUTPUT_DIR', $this->testOutputDir);
        $this->setContainerVariable('TEMPLATE_DIR', $this->testTemplateDir);
        $this->setContainerVariable('TEMPLATE', 'test');
        $this->setContainerVariable('SITE_NAME', 'Test Site');
        $this->setContainerVariable('SITE_BASE_URL', 'https://test.example.com');

        // Override site_config to ensure SITE_NAME is used or matches
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);

        // Create extension registry
        $extensionRegistry = new \EICC\StaticForge\Core\ExtensionRegistry($this->container);
        $this->addToContainer(\EICC\StaticForge\Core\ExtensionRegistry::class, $extensionRegistry);

        // Create EventManager and test feature
        $eventManager = new EventManager($this->container);
        $this->addToContainer(EventManager::class, $eventManager);
        $this->feature = new Feature();
        $this->feature->register($eventManager, $this->container);

        // Create test templates
        $this->createTestTemplates();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up test directories
        $this->removeDirectory($this->testSourceDir);
        $this->removeDirectory($this->testOutputDir);
        $this->removeDirectory($this->testTemplateDir);
    }

    /**
     * Test basic Markdown processing without frontmatter
     */
    public function testBasicMarkdownProcessing(): void
    {
        $markdownContent = "# Test Heading\n\nThis is a **bold** paragraph with *italic* text.";
        $testFile = $this->testSourceDir . '/test.md';
        file_put_contents($testFile, $markdownContent);

        $parameters = ['file_path' => $testFile];
        $result = $this->feature->handleRender($this->container, $parameters);

        $this->assertArrayHasKey('rendered_content', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('output_path', $result);

        $outputContent = $result['rendered_content'];
        $this->assertMatchesRegularExpression('/<h1>\s*Test Heading/', $outputContent);
        $this->assertStringContainsString('<strong>bold</strong>', $outputContent);
        $this->assertStringContainsString('<em>italic</em>', $outputContent);
        $this->assertStringContainsString('Test Site', $outputContent);
    }

    /**
     * Test Markdown processing with YAML frontmatter
     */
    public function testMarkdownWithYAMLFrontmatter(): void
    {
        $markdownContent = <<<MD
---
title: "Custom Title"
description: "This is a test page description"
author: "Test Author"
tags:
  - test
  - markdown
  - yaml
template: "base"
---

# Heading from Content

This is the actual content of the page.
MD;

        $testFile = $this->testSourceDir . '/frontmatter.md';
        file_put_contents($testFile, $markdownContent);

        $parameters = [
            'file_path' => $testFile,
            'file_metadata' => [
                'title' => 'Custom Title',
                'description' => 'This is a test page description',
                'author' => 'Test Author',
                'tags' => ['test', 'markdown', 'yaml'],
                'template' => 'base'
            ]
        ];
        $result = $this->feature->handleRender($this->container, $parameters);

        $this->assertArrayHasKey('rendered_content', $result);
        $this->assertArrayHasKey('metadata', $result);

        $outputContent = $result['rendered_content'];
        $this->assertStringContainsString('<title>Custom Title | Test Site</title>', $outputContent);
        $this->assertStringContainsString('This is a test page description', $outputContent);
        $this->assertStringContainsString('Test Author', $outputContent);
        $this->assertMatchesRegularExpression('/<h1>\s*Heading from Content/', $outputContent);
    }

    /**
     * Test title extraction from content when not in frontmatter
     */
    public function testTitleExtractionFromContent(): void
    {
        $markdownContent = "## Main Heading\n\nSome content here.";
        $testFile = $this->testSourceDir . '/title-extract.md';
        file_put_contents($testFile, $markdownContent);

        $parameters = ['file_path' => $testFile];
        $result = $this->feature->handleRender($this->container, $parameters);

        $this->assertArrayHasKey('rendered_content', $result);
        $this->assertArrayHasKey('metadata', $result);

        $outputContent = $result['rendered_content'];
        $this->assertStringContainsString('<title>Main Heading | Test Site</title>', $outputContent);
    }

    /**
     * Test fallback template when Twig template fails
     */
    public function testFallbackTemplate(): void
    {
        $markdownContent = <<<MD
---
title: "Fallback Test"
template: "nonexistent"
---

# Test Content

This should use the fallback template.
MD;

        $testFile = $this->testSourceDir . '/fallback.md';
        file_put_contents($testFile, $markdownContent);

        $parameters = [
            'file_path' => $testFile,
            'file_metadata' => [
                'title' => 'Fallback Test',
                'template' => 'nonexistent'
            ]
        ];
        $result = $this->feature->handleRender($this->container, $parameters);

        $this->assertArrayHasKey('rendered_content', $result);
        $this->assertArrayHasKey('metadata', $result);

        $outputContent = $result['rendered_content'];
        $this->assertStringContainsString('<!DOCTYPE html>', $outputContent);
        $this->assertStringContainsString('Fallback Test | Test Site', $outputContent);
        $this->assertStringContainsString('Generated by StaticForge', $outputContent);
    }

    /**
     * Test complex Markdown features
     */
    public function testComplexMarkdownFeatures(): void
    {
        $markdownContent = <<<MD
---
title: Complex Markdown
description: Testing various Markdown features
---

# Main Title

## Subtitle

Here's a paragraph with a [link](https://example.com) and some `inline code`.

### Lists

- Item 1
- Item 2
  - Nested item
  - Another nested item

1. Numbered item
2. Another numbered item

### Code Block

```php
<?php
echo "Hello, World!";
```

### Blockquote

> This is a blockquote
> with multiple lines

### Table

| Header 1 | Header 2 |
|----------|----------|
| Cell 1   | Cell 2   |
| Cell 3   | Cell 4   |
MD;

        $testFile = $this->testSourceDir . '/complex.md';
        file_put_contents($testFile, $markdownContent);

        $parameters = [
            'file_path' => $testFile,
            'file_metadata' => [
                'title' => 'Complex Markdown',
                'description' => 'Testing various Markdown features'
            ]
        ];
        $result = $this->feature->handleRender($this->container, $parameters);

        $this->assertArrayHasKey('rendered_content', $result);
        $this->assertArrayHasKey('metadata', $result);

        $outputContent = $result['rendered_content'];
        $this->assertMatchesRegularExpression('/<h1>\s*Main Title/', $outputContent);
        $this->assertMatchesRegularExpression('/<h2>\s*Subtitle/', $outputContent);
        $this->assertStringContainsString('<a href="https://example.com">link</a>', $outputContent);
        $this->assertStringContainsString('<code>inline code</code>', $outputContent);
        $this->assertStringContainsString('<ul>', $outputContent);
        $this->assertStringContainsString('<ol>', $outputContent);
        $this->assertStringContainsString('<pre><code', $outputContent);
        $this->assertStringContainsString('<blockquote>', $outputContent);
    }

    /**
     * Test that non-Markdown files are ignored
     */
    public function testIgnoresNonMarkdownFiles(): void
    {
        $parameters = ['file_path' => 'test.html'];
        $result = $this->feature->handleRender($this->container, $parameters);

        $this->assertArrayNotHasKey('processed', $result);
        $this->assertEquals(['file_path' => 'test.html'], $result);
    }

    /**
     * Test error handling for invalid files
     */
    public function testErrorHandling(): void
    {
        $parameters = ['file_path' => '/nonexistent/file.md'];
        $result = $this->feature->handleRender($this->container, $parameters);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Failed to read file', $result['error']);
    }

    /**
     * Test output file path generation
     */
    public function testOutputPathGeneration(): void
    {
        $markdownContent = "# Test\n\nContent here.";
        $subDir = $this->testSourceDir . '/subdir';
        mkdir($subDir, 0755, true);
        $testFile = $subDir . '/nested.md';
        file_put_contents($testFile, $markdownContent);

        $parameters = ['file_path' => $testFile];
        $result = $this->feature->handleRender($this->container, $parameters);

        $this->assertArrayHasKey('rendered_content', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertStringEndsWith('/subdir/nested.html', $result['output_path']);
        // Core writes files, not renderer - just verify output_path is correct
    }

    /**
     * Test template variable injection
     */
    public function testTemplateVariableInjection(): void
    {
        $markdownContent = <<<MD
---
title = "Variable Test"
custom_var = "Custom Value"
keywords = "test, variables"
template = "variables"
---

# Variable Test Content
MD;

        $testFile = $this->testSourceDir . '/variables.md';
        file_put_contents($testFile, $markdownContent);

        $parameters = [
            'file_path' => $testFile,
            'file_metadata' => [
                'title' => 'Variable Test',
                'custom_var' => 'Custom Value',
                'keywords' => 'test, variables',
                'template' => 'variables'
            ]
        ];
        $result = $this->feature->handleRender($this->container, $parameters);

        $this->assertArrayHasKey('rendered_content', $result);
        $this->assertArrayHasKey('metadata', $result);

        $outputContent = $result['rendered_content'];
        $this->assertStringContainsString('Variable Test', $outputContent);
        $this->assertStringContainsString('Custom Value', $outputContent);
        $this->assertStringContainsString('test, variables', $outputContent);
    }

    /**
     * Create test templates for testing
     */
    private function createTestTemplates(): void
    {
        // Base template
        $baseTemplate = '<!DOCTYPE html>
<html>
<head>
    <title>{{ title | default("Default Title") }} | {{ site_name }}</title>
    <meta name="description" content="{{ description | default("Default description") }}">
    <meta name="author" content="{{ author | default("") }}">
    <meta name="keywords" content="{{ keywords | default("") }}">
</head>
<body>
    <header>
        <h1>{{ site_name }}</h1>
    </header>
    <main>
        {{ content | raw }}
    </main>
    <footer>
        <p>&copy; 2025 {{ site_name }}. Generated by StaticForge.</p>
    </footer>
</body>
</html>';
        file_put_contents($this->testTemplateDir . '/test/base.html.twig', $baseTemplate);

        // Variables template for testing custom variables
        $variablesTemplate = '<!DOCTYPE html>
<html>
<head>
    <title>{{ title }}</title>
    <meta name="keywords" content="{{ keywords }}">
</head>
<body>
    <h1>{{ title }}</h1>
    <div>{{ content | raw }}</div>
    <p>Custom: {{ custom_var }}</p>
    <p>Keywords: {{ keywords }}</p>
</body>
</html>';
        file_put_contents($this->testTemplateDir . '/test/variables.html.twig', $variablesTemplate);
    }

    /**
     * Recursively remove a directory and its contents
     */
    private function removeDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }
}