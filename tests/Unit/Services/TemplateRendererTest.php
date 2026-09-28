<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services;

use EICC\StaticForge\Core\AssetManager;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\StaticForge\Services\TemplateVariableBuilder;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader;

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

    public function testMissingTemplateThrowsException(): void
    {
        // Use a non-existent template
        $parsedContent = [
            'metadata' => [
                'template' => 'nonexistent'
            ],
            'content' => 'Fallback content',
            'title' => 'Fallback Test'
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Template file not found: test/nonexistent.html.twig');

        $this->renderer->render($parsedContent, $this->container);
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

    public function testUsesContainerTwigInstance(): void
    {
        $twig = $this->container->get('twig');
        $this->assertInstanceOf(TwigEnvironment::class, $twig);

        $loader = new FilesystemLoader($this->testTemplateDir);
        $loader->addPath($this->testTemplateDir . '/test');
        $twig->setLoader($loader);
        $twig->addGlobal('marker', 'from-container');

        $this->setContainerVariable('twig_template_dir', $this->testTemplateDir);
        $this->setContainerVariable('twig_active_template', 'test');

        $parsedContent = [
            'metadata' => [
                'template' => 'marker'
            ],
            'content' => 'Marker content',
            'title' => 'Marker Test'
        ];

        $result = $this->renderer->render($parsedContent, $this->container);

        $this->assertStringContainsString('Marker: from-container', $result);
    }

    public function testRenderTemplateResolvesBuiltInYoutubeShortcodeTemplateWhenThemeHasNone(): void
    {
        $result = $this->renderer->renderTemplate(
            'shortcodes/youtube.twig',
            ['id' => 'abc123', 'width' => '560', 'height' => '315', 'title' => 'A video'],
            $this->container
        );

        $this->assertStringContainsString('src="https://www.youtube.com/embed/abc123"', $result);
        $this->assertStringContainsString('<iframe', $result);
    }

    public function testRenderTemplateUrlEncodesYoutubeId(): void
    {
        $result = $this->renderer->renderTemplate(
            'shortcodes/youtube.twig',
            ['id' => 'abc 123/x', 'width' => '560', 'height' => '315', 'title' => 'A video'],
            $this->container
        );

        $this->assertStringContainsString('src="https://www.youtube.com/embed/abc%20123%2Fx"', $result);
    }

    public function testRenderTemplatePrefersThemesOwnYoutubeShortcodeTemplate(): void
    {
        mkdir($this->testTemplateDir . '/test/shortcodes', 0755, true);
        file_put_contents(
            $this->testTemplateDir . '/test/shortcodes/youtube.twig',
            '<div class="theme-youtube">{{ id }}</div>'
        );

        $result = $this->renderer->renderTemplate(
            'shortcodes/youtube.twig',
            ['id' => 'abc123', 'width' => '560', 'height' => '315', 'title' => 'A video'],
            $this->container
        );

        $this->assertSame('<div class="theme-youtube">abc123</div>', trim($result));
        $this->assertStringNotContainsString('<iframe', $result);
    }

    public function testRenderWrapsContentInContentMarkers(): void
    {
        $parsedContent = [
            'metadata' => ['template' => 'base'],
            'content' => '<p>Article body</p>',
            'title' => 'Marked Page',
        ];

        $result = $this->renderer->render($parsedContent, $this->container);

        $this->assertStringContainsString(
            '<!--sf:content--><p>Article body</p><!--/sf:content-->',
            $result
        );
    }

    public function testRenderLeavesEmptyContentUnwrapped(): void
    {
        $result = $this->renderer->render(
            ["metadata" => ["template" => "base"], "content" => "", "title" => "Empty"],
            $this->container
        );

        $this->assertStringNotContainsString("sf:content", $result);
    }

    public function testRenderInjectsAssetManagerStylesBeforeClosingHeadTagEvenWhenThemeAlreadyLinksAStylesheet(): void
    {
        file_put_contents(
            $this->testTemplateDir . '/test/withstyles.html.twig',
            '<!DOCTYPE html><html><head><title>{{ title }}</title>'
            . '<link rel="stylesheet" href="/theme.css"></head>'
            . '<body>{{ content|raw }}</body></html>'
        );

        $assetManager = new AssetManager();
        $assetManager->addStyle('site', '/assets/site.css');
        $logger = $this->container->get('logger');
        $renderer = new TemplateRenderer(new TemplateVariableBuilder(), $logger, $assetManager);

        $parsedContent = [
            'metadata' => ['template' => 'withstyles'],
            'content' => '<p>Body</p>',
            'title' => 'Styled Page',
        ];

        $result = $renderer->render($parsedContent, $this->container);

        $this->assertStringContainsString('<link rel="stylesheet" href="/theme.css">', $result);
        $stylesMarkup = $assetManager->getStyles();
        $this->assertStringContainsString($stylesMarkup, $result);

        $headEnd = strpos($result, '</head>');
        $this->assertNotFalse($headEnd);
        $this->assertLessThan($headEnd, strpos($result, $stylesMarkup));
    }

    public function testRenderOnlyInjectsAssetsOnceEvenWhenContentContainsAClosingHeadTagLiterally(): void
    {
        file_put_contents(
            $this->testTemplateDir . '/test/withheadinbody.html.twig',
            '<!DOCTYPE html><html><head><title>{{ title }}</title></head>'
            . '<body>{{ content|raw }}</body></html>'
        );

        $assetManager = new AssetManager();
        $assetManager->addStyle('site', '/assets/site.css');
        $logger = $this->container->get('logger');
        $renderer = new TemplateRenderer(new TemplateVariableBuilder(), $logger, $assetManager);

        $parsedContent = [
            'metadata' => ['template' => 'withheadinbody'],
            // Page content literally quotes a closing </head> tag as text
            'content' => '<p>See the code: &lt;/head&gt; and also </head> literally</p>',
            'title' => 'Tricky Page',
        ];

        $result = $renderer->render($parsedContent, $this->container);

        $stylesMarkup = $assetManager->getStyles();
        $this->assertSame(1, substr_count($result, $stylesMarkup));
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

        $markerTemplate = <<<'EOT'
    Marker: {{ marker }}
    EOT;

        file_put_contents($this->testTemplateDir . '/test/base.html.twig', $baseTemplate);
        file_put_contents($this->testTemplateDir . '/test/variables.html.twig', $variablesTemplate);
        file_put_contents($this->testTemplateDir . '/test/layout.html.twig', $layoutTemplate);
        file_put_contents($this->testTemplateDir . '/test/child.html.twig', $childTemplate);
        file_put_contents($this->testTemplateDir . '/test/marker.html.twig', $markerTemplate);
    }

    // removeDirectory is now provided by UnitTestCase
}
