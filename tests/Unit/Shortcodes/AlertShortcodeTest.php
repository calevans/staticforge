<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Shortcodes;

use EICC\StaticForge\Services\TemplateRenderer;
use EICC\StaticForge\Services\TemplateVariableBuilder;
use EICC\StaticForge\Shortcodes\AlertShortcode;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class AlertShortcodeTest extends UnitTestCase
{
    private string $templateDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templateDir = sys_get_temp_dir() . '/staticforge_alert_templates_' . uniqid();
        mkdir($this->templateDir . '/test/shortcodes', 0755, true);

        $template = <<<'EOT'
<div class="alert alert-{{ type }}">{{ content|raw }}</div>
EOT;

        file_put_contents($this->templateDir . '/test/shortcodes/alert.twig', $template);

        $this->setContainerVariable('TEMPLATE_DIR', $this->templateDir);
        $this->setContainerVariable('TEMPLATE', 'test');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->templateDir);
        parent::tearDown();
    }

    public function testGetNameReturnsAlert(): void
    {
        $shortcode = new AlertShortcode();
        $this->assertSame('alert', $shortcode->getName());
    }

    public function testHandleDefaultsToInfoType(): void
    {
        $shortcode = $this->createShortcodeWithRenderer();

        $output = $shortcode->handle([], 'Hello world');

        $this->assertStringContainsString('alert-info', $output);
        $this->assertStringContainsString('Hello world', $output);
    }

    public function testHandleUsesProvidedType(): void
    {
        $shortcode = $this->createShortcodeWithRenderer();

        $output = $shortcode->handle(['type' => 'danger'], 'Careful!');

        $this->assertStringContainsString('alert-danger', $output);
        $this->assertStringContainsString('Careful!', $output);
    }

    public function testHandleConvertsMarkdownInContent(): void
    {
        $shortcode = $this->createShortcodeWithRenderer();

        $output = $shortcode->handle(['type' => 'warning'], '**bold warning**');

        $this->assertStringContainsString('<strong>bold warning</strong>', $output);
    }

    public function testHandleWithEmptyContent(): void
    {
        $shortcode = $this->createShortcodeWithRenderer();

        $output = $shortcode->handle(['type' => 'info']);

        $this->assertStringContainsString('alert-info', $output);
    }

    private function createShortcodeWithRenderer(): AlertShortcode
    {
        $logger = $this->container->get('logger');
        $renderer = new TemplateRenderer(new TemplateVariableBuilder(), $logger, null);

        $shortcode = new AlertShortcode();
        $shortcode->setTemplateRenderer($renderer);
        $shortcode->setContainer($this->container);

        return $shortcode;
    }
}
