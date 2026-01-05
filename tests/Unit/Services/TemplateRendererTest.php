<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services;

use EICC\StaticForge\Services\TemplateRenderer;
use EICC\StaticForge\Services\TemplateVariableBuilder;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;

class TemplateRendererTest extends UnitTestCase
{
    private TemplateRenderer $renderer;
    private string $testTemplateDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test template directory
        $this->testTemplateDir = sys_get_temp_dir() . '/staticforge_templates_' . uniqid();
        mkdir($this->testTemplateDir . '/test', 0755, true);

        // Create container with test configuration
        $this->setContainerVariable('TEMPLATE_DIR', $this->testTemplateDir);
        $this->setContainerVariable('TEMPLATE', 'test');
        $this->setContainerVariable('SITE_NAME', 'Test Site');
        $this->setContainerVariable('SITE_BASE_URL', 'https://test.example.com');
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);

        // Initialize renderer
        $logger = $this->container->get('logger');
        $this->renderer = new TemplateRenderer(new TemplateVariableBuilder(), $logger, null);

        // Create test templates
        $this->createTestTemplates();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->testTemplateDir);
    }

    public function testBasicTemplateRendering(): void
    {
        $parsedContent = [
            'metadata' => [
                'description' => 'Test page description',
                'template' => 'base'
            ],
            'content' => '<h1>Test Content</h1><p>Test paragraph</p>',
            'title' => 'Test Page'
        ];

        $result = $this->renderer->render($parsedContent, $this->container);

        $this->assertStringContainsString('<title>Test Page</title>', $result);
        $this->assertStringContainsString('<h1>Test Content</h1>', $result);
        $this->assertStringContainsString('<p>Test paragraph</p>', $result);
        $this->assertStringContainsString('Test Site', $result);
        $this->assertStringContainsString('https://test.example.com', $result);
    }

    public function testTemplateVariableInjection(): void
    {
        $parsedContent = [
            'metadata' => [
                'description' => 'Custom description',
                'author' => 'Test Author',
                'keywords' => 'test, template, variables',
                'template' => 'variables'
            ],
            'content' => '<p>Variable test content</p>',
            'title' => 'Variable Test'
        ];

        $result = $this->renderer->render($parsedContent, $this->container);

        $this->assertStringContainsString('Author: Test Author', $result);
        $this->assertStringContainsString('Description: Custom description', $result);
        $this->assertStringContainsString('Keywords: test, template, variables', $result);
    }

    public function testTemplateAutoEscaping(): void
    {
        $parsedContent = [
            'metadata' => [
                'template' => 'variables',
                'author' => '<script>alert("xss")</script>'
            ],
            'content' => 'Content',
            'title' => 'Security Test'
        ];

        $result = $this->renderer->render($parsedContent, $this->container);

        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $result);
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $result);
    }

    public function testFallbackTemplate(): void
    {
        // Use a non-existent template
        $parsedContent = [
            'metadata' => [
                'template' => 'nonexistent'
            ],
            'content' => 'Fallback content',
            'title' => 'Fallback Test'
        ];

        $result = $this->renderer->render($parsedContent, $this->container);

        // Should fall back to basic template
        $this->assertStringContainsString('Fallback content', $result);
        $this->assertStringContainsString('Fallback Test | Test Site', $result);
    }

    public function testTemplateInheritance(): void
    {
        $parsedContent = [
            'metadata' => [
                'template' => 'child'
            ],
            'content' => 'Child content',
            'title' => 'Inheritance Test'
        ];

        $result = $this->renderer->render($parsedContent, $this->container);

        $this->assertStringContainsString('<div class="layout">', $result);
        $this->assertStringContainsString('<div class="content">', $result);
        $this->assertStringContainsString('Child content', $result);
    }

    private function createTestTemplates(): void
    {
        $baseTemplate = <<<'EOT'
<!DOCTYPE html>
<html>
<head>
    <title>{{ title }}</title>
</head>
<body>
    <div class="site-name">{{ site_name }}</div>
    <div class="base-url">{{ site_base_url }}</div>
    <div class="content">{{ content|raw }}</div>
</body>
</html>
EOT;

        $variablesTemplate = <<<'EOT'
Author: {{ author }}
Description: {{ description }}
Keywords: {{ keywords }}
EOT;

        $layoutTemplate = <<<'EOT'
<!DOCTYPE html>
<html>
<body>
    <div class="layout">
        {% block content %}{% endblock %}
    </div>
</body>
</html>
EOT;

        $childTemplate = <<<'EOT'
{% extends "layout.html.twig" %}
{% block content %}
    <div class="content">{{ content|raw }}</div>
{% endblock %}
EOT;

        file_put_contents($this->testTemplateDir . '/test/base.html.twig', $baseTemplate);
        file_put_contents($this->testTemplateDir . '/test/variables.html.twig', $variablesTemplate);
        file_put_contents($this->testTemplateDir . '/test/layout.html.twig', $layoutTemplate);
        file_put_contents($this->testTemplateDir . '/test/child.html.twig', $childTemplate);
    }

    // removeDirectory is now provided by UnitTestCase
}
