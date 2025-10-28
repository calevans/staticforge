<?php

namespace EICC\StaticForge\Tests\Unit\Features\HtmlRenderer;

use PHPUnit\Framework\TestCase;
use EICC\StaticForge\Features\HtmlRenderer\Feature;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;
use Twig\Environment;

/**
 * Unit tests for template rendering functionality
 */
class TemplateRenderingTest extends TestCase
{
    private Feature $feature;
    private Container $container;
    private string $testTemplateDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test template directory
        $this->testTemplateDir = sys_get_temp_dir() . '/staticforge_templates_' . uniqid();
        mkdir($this->testTemplateDir . '/test', 0755, true);

        // Create container with test configuration
        $this->container = new Container();
        $this->container->setVariable('TEMPLATE_DIR', $this->testTemplateDir);
        $this->container->setVariable('TEMPLATE', 'test');
        $this->container->setVariable('SITE_NAME', 'Test Site');
        $this->container->setVariable('SITE_BASE_URL', 'https://test.example.com');
        $this->container->setVariable('logger', new Log('test', sys_get_temp_dir() . '/test.log', 'DEBUG'));

        // Create mock extension registry
        $extensionRegistry = new \EICC\StaticForge\Core\ExtensionRegistry($this->container);
        $this->container->add('extension_registry', $extensionRegistry);

        // Create EventManager and test feature
        $eventManager = new EventManager($this->container);
        $this->feature = new Feature();
        $this->feature->register($eventManager, $this->container);

        // Create test templates
        $this->createTestTemplates();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up test directory
        $this->removeDirectory($this->testTemplateDir);
    }

    /**
     * Test basic template rendering with variables
     */
    public function testBasicTemplateRendering(): void
    {
        $parsedContent = [
            'metadata' => ['description' => 'Test page description'],
            'content' => '<h1>Test Content</h1><p>Test paragraph</p>',
            'title' => 'Test Page',
            'template' => 'base'
        ];

        $result = $this->invokeTemplateMethod($parsedContent);

        $this->assertStringContainsString('<title>Test Page</title>', $result);
        $this->assertStringContainsString('<h1>Test Content</h1>', $result);
        $this->assertStringContainsString('<p>Test paragraph</p>', $result);
        $this->assertStringContainsString('Test Site', $result);
        $this->assertStringContainsString('https://test.example.com', $result);
    }

    /**
     * Test template variable injection
     */
    public function testTemplateVariableInjection(): void
    {
        $parsedContent = [
            'metadata' => [
                'description' => 'Custom description',
                'author' => 'Test Author',
                'keywords' => 'test, template, variables'
            ],
            'content' => '<p>Variable test content</p>',
            'title' => 'Variable Test',
            'template' => 'variables'
        ];

        $result = $this->invokeTemplateMethod($parsedContent);

        $this->assertStringContainsString('Custom description', $result);
        $this->assertStringContainsString('Test Author', $result);
        $this->assertStringContainsString('test, template, variables', $result);
        $this->assertStringContainsString('Variable Test', $result);
    }

    /**
     * Test template security (auto-escaping)
     */
    public function testTemplateAutoEscaping(): void
    {
        $parsedContent = [
            'metadata' => ['description' => '<script>alert("xss")</script>'],
            'content' => '<p>Safe content</p><script>alert("dangerous")</script>',
            'title' => 'Security Test',
            'template' => 'security'
        ];

        $result = $this->invokeTemplateMethod($parsedContent);

        // Content should be rendered as-is (using |raw filter)
        $this->assertStringContainsString('<p>Safe content</p>', $result);
        $this->assertStringContainsString('<script>alert("dangerous")</script>', $result);

        // Metadata should be auto-escaped
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $result);
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $result);
    }

    /**
     * Test fallback to basic template on error
     */
    public function testFallbackTemplate(): void
    {
        $parsedContent = [
            'metadata' => ['description' => 'Test description'],
            'content' => '<p>Test content</p>',
            'title' => 'Fallback Test',
            'template' => 'nonexistent'  // This template doesn't exist
        ];

        $result = $this->invokeTemplateMethod($parsedContent);

        // Should fall back to basic template
        $this->assertStringContainsString('<!DOCTYPE html>', $result);
        $this->assertStringContainsString('Fallback Test | Test Site', $result);
        $this->assertStringContainsString('<p>Test content</p>', $result);
        $this->assertStringContainsString('Generated by StaticForge', $result);
    }

    /**
     * Test template inheritance
     */
    public function testTemplateInheritance(): void
    {
        $parsedContent = [
            'metadata' => ['section' => 'homepage'],
            'content' => '<p>Extended template content</p>',
            'title' => 'Inheritance Test',
            'template' => 'extended'
        ];

        $result = $this->invokeTemplateMethod($parsedContent);

        // Should contain base template elements
        $this->assertStringContainsString('Base Template Layout', $result);
        $this->assertStringContainsString('Extended Template Content', $result);
        $this->assertStringContainsString('<p>Extended template content</p>', $result);
        $this->assertStringContainsString('homepage', $result);
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
    <title>{{ title | default("Default Title") }}</title>
    <meta name="description" content="{{ description | default("Default description") }}">
</head>
<body>
    <header>
        <h1>{{ site_name }}</h1>
        <p>{{ site_base_url }}</p>
    </header>
    <main>
        {{ content | raw }}
    </main>
</body>
</html>';
        file_put_contents($this->testTemplateDir . '/test/base.html.twig', $baseTemplate);

        // Variables template
        $variablesTemplate = '<!DOCTYPE html>
<html>
<head>
    <title>{{ title }}</title>
    <meta name="description" content="{{ description }}">
    <meta name="author" content="{{ author }}">
    <meta name="keywords" content="{{ keywords }}">
</head>
<body>
    <h1>{{ title }}</h1>
    <div>{{ content | raw }}</div>
</body>
</html>';
        file_put_contents($this->testTemplateDir . '/test/variables.html.twig', $variablesTemplate);

        // Security template
        $securityTemplate = '<!DOCTYPE html>
<html>
<head>
    <title>{{ title }}</title>
    <meta name="description" content="{{ description }}">
</head>
<body>
    <h1>{{ title }}</h1>
    <div>{{ content | raw }}</div>
    <p>Description: {{ description }}</p>
</body>
</html>';
        file_put_contents($this->testTemplateDir . '/test/security.html.twig', $securityTemplate);

        // Base template for inheritance
        $inheritanceBase = '<!DOCTYPE html>
<html>
<head>
    <title>{% block title %}{{ title }}{% endblock %}</title>
</head>
<body>
    <header>Base Template Layout</header>
    <main>
        {% block content %}{{ content | raw }}{% endblock %}
    </main>
    <footer>{% block section %}{{ section }}{% endblock %}</footer>
</body>
</html>';
        file_put_contents($this->testTemplateDir . '/test/base_inheritance.html.twig', $inheritanceBase);

        // Extended template
        $extendedTemplate = '{% extends "test/base_inheritance.html.twig" %}

{% block content %}
<div>Extended Template Content</div>
{{ parent() }}
{% endblock %}';
        file_put_contents($this->testTemplateDir . '/test/extended.html.twig', $extendedTemplate);
    }

    /**
     * Invoke the private applyTemplate method for testing
     */
    private function invokeTemplateMethod(array $parsedContent): string
    {
        $reflection = new \ReflectionClass($this->feature);
        $method = $reflection->getMethod('applyTemplate');
        $method->setAccessible(true);

        return $method->invoke($this->feature, $parsedContent, $this->container);
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