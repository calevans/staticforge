<?php

namespace EICC\StaticForge\Tests\Unit\Features\MarkdownRenderer;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureFactory;
use EICC\StaticForge\Features\MarkdownRenderer\Feature;
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
        $eventManager = new EventManager();
        $this->addToContainer(EventManager::class, $eventManager);

        // Register dependencies in container for DI
        $this->addToContainer(\EICC\StaticForge\Features\MarkdownRenderer\MarkdownProcessor::class, new \EICC\StaticForge\Features\MarkdownRenderer\MarkdownProcessor());
        $this->addToContainer(\EICC\StaticForge\Features\MarkdownRenderer\ContentExtractor::class, new \EICC\StaticForge\Features\MarkdownRenderer\ContentExtractor());
        $this->addToContainer(\EICC\StaticForge\Services\TemplateVariableBuilder::class, new \EICC\StaticForge\Services\TemplateVariableBuilder());
        $this->addToContainer(\EICC\StaticForge\Services\TemplateRenderer::class, new \EICC\StaticForge\Services\TemplateRenderer(
            $this->container->get(\EICC\StaticForge\Services\TemplateVariableBuilder::class),
            $this->container->get('logger')
        ));

        $feature = (new FeatureFactory($this->container))->make(Feature::class);
        $this->assertInstanceOf(Feature::class, $feature);
        $this->feature = $feature;
        $this->feature->register($eventManager);

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
     * @param array<string, mixed> $metadata
     */
    private function makeEvent(string $filePath, array $metadata = []): RenderEvent
    {
        return new RenderEvent(
            name: 'RENDER',
            filePath: $filePath,
            fileUrl: '',
            metadata: $metadata,
        );
    }

    /**
     * Test basic Markdown processing without frontmatter
     */
    public function testBasicMarkdownProcessing(): void
    {
        $markdownContent = "# Test Heading\n\nThis is a **bold** paragraph with *italic* text.";
        $testFile = $this->testSourceDir . '/test.md';
        file_put_contents($testFile, $markdownContent);

        $event = $this->makeEvent($testFile);
        $this->feature->handleRender($event);

        $this->assertNotNull($event->renderedContent);
        $this->assertNotNull($event->outputPath);

        $outputContent = $event->renderedContent;
        // Updated regex to handle attributes on h1 (e.g. id)
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*Test Heading/', $outputContent);
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

        $event = $this->makeEvent($testFile, [
            'title' => 'Custom Title',
            'description' => 'This is a test page description',
            'author' => 'Test Author',
            'tags' => ['test', 'markdown', 'yaml'],
            'template' => 'base'
        ]);
        $this->feature->handleRender($event);

        $this->assertNotNull($event->renderedContent);

        $outputContent = $event->renderedContent;
        $this->assertStringContainsString('<title>Custom Title | Test Site</title>', $outputContent);
        $this->assertStringContainsString('This is a test page description', $outputContent);
        $this->assertStringContainsString('Test Author', $outputContent);
        // Updated regex to handle attributes on h1
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*Heading from Content/', $outputContent);
    }

    /**
     * Test title extraction from content when not in frontmatter
     */
    public function testTitleExtractionFromContent(): void
    {
        $markdownContent = "## Main Heading\n\nSome content here.";
        $testFile = $this->testSourceDir . '/title-extract.md';
        file_put_contents($testFile, $markdownContent);

        $event = $this->makeEvent($testFile);
        $this->feature->handleRender($event);

        $this->assertNotNull($event->renderedContent);

        $outputContent = $event->renderedContent;
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

        $event = $this->makeEvent($testFile, [
            'title' => 'Fallback Test',
            'template' => 'nonexistent'
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Template file not found: test/nonexistent.html.twig');

        $this->feature->handleRender($event);
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

        $event = $this->makeEvent($testFile, [
            'title' => 'Complex Markdown',
            'description' => 'Testing various Markdown features'
        ]);
        $this->feature->handleRender($event);

        $this->assertNotNull($event->renderedContent);

        $outputContent = $event->renderedContent;
        // Updated regexes to handle attributes on headers
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*Main Title/', $outputContent);
        $this->assertMatchesRegularExpression('/<h2[^>]*>\s*Subtitle/', $outputContent);
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
        $event = $this->makeEvent('test.html');
        $this->feature->handleRender($event);

        $this->assertNull($event->renderedContent);
        $this->assertNull($event->outputPath);
        $this->assertSame([], $event->metadata);
    }

    /**
     * Test error handling for files outside source directory
     */
    public function testErrorHandlingOutsideSourceDir(): void
    {
        $event = $this->makeEvent('/nonexistent/file.md');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Security Error: File path is outside the allowed source directory');

        $this->feature->handleRender($event);
    }

    /**
     * Test error handling for unreadable files
     */
    public function testErrorHandlingUnreadableFile(): void
    {
        $testFile = $this->testSourceDir . '/unreadable.md';
        file_put_contents($testFile, '# Test');
        chmod($testFile, 0000); // Make unreadable

        $event = $this->makeEvent($testFile);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Failed to read file');

            $this->feature->handleRender($event);
        } finally {
            // Restore permissions so it can be deleted during tearDown
            chmod($testFile, 0644);
        }
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

        $event = $this->makeEvent($testFile);
        $this->feature->handleRender($event);

        $this->assertNotNull($event->renderedContent);
        $this->assertNotNull($event->outputPath);
        $this->assertStringEndsWith('/subdir/nested.html', $event->outputPath);
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

        $event = $this->makeEvent($testFile, [
            'title' => 'Variable Test',
            'custom_var' => 'Custom Value',
            'keywords' => 'test, variables',
            'template' => 'variables'
        ]);
        $this->feature->handleRender($event);

        $this->assertNotNull($event->renderedContent);

        $outputContent = $event->renderedContent;
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

    // removeDirectory is now provided by UnitTestCase
}
