<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Shortcodes;

use EICC\StaticForge\Services\TemplateRenderer;
use EICC\StaticForge\Services\TemplateVariableBuilder;
use EICC\StaticForge\Shortcodes\YoutubeShortcode;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class YoutubeShortcodeTest extends UnitTestCase
{
    private string $templateDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templateDir = sys_get_temp_dir() . '/staticforge_youtube_templates_' . uniqid();
        mkdir($this->templateDir . '/test/shortcodes', 0755, true);

        $template = <<<'EOT'
<iframe width="{{ width }}" height="{{ height }}" title="{{ title }}" src="https://www.youtube.com/embed/{{ id }}"></iframe>
EOT;

        file_put_contents($this->templateDir . '/test/shortcodes/youtube.twig', $template);

        $this->setContainerVariable('TEMPLATE_DIR', $this->templateDir);
        $this->setContainerVariable('TEMPLATE', 'test');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->templateDir);
        parent::tearDown();
    }

    public function testGetNameReturnsYoutube(): void
    {
        $shortcode = new YoutubeShortcode();
        $this->assertSame('youtube', $shortcode->getName());
    }

    public function testHandleRendersIframeWithDefaults(): void
    {
        $shortcode = $this->createShortcodeWithRenderer();

        $output = $shortcode->handle(['id' => 'abc123']);

        $this->assertStringContainsString('src="https://www.youtube.com/embed/abc123"', $output);
        $this->assertStringContainsString('width="560"', $output);
        $this->assertStringContainsString('height="315"', $output);
        $this->assertStringContainsString('title="YouTube video player"', $output);
    }

    public function testHandleRendersIframeWithCustomAttributes(): void
    {
        $shortcode = $this->createShortcodeWithRenderer();

        $output = $shortcode->handle([
            'id' => 'xyz789',
            'width' => '800',
            'height' => '450',
            'title' => 'My Custom Video',
        ]);

        $this->assertStringContainsString('width="800"', $output);
        $this->assertStringContainsString('height="450"', $output);
        $this->assertStringContainsString('title="My Custom Video"', $output);
    }

    public function testHandleReturnsErrorCommentWhenIdMissing(): void
    {
        $shortcode = $this->createShortcodeWithRenderer();

        $output = $shortcode->handle([]);

        $this->assertSame('<!-- Youtube shortcode missing id -->', $output);
    }

    public function testHandleReturnsErrorCommentWhenIdEmpty(): void
    {
        $shortcode = $this->createShortcodeWithRenderer();

        $output = $shortcode->handle(['id' => '']);

        $this->assertSame('<!-- Youtube shortcode missing id -->', $output);
    }

    private function createShortcodeWithRenderer(): YoutubeShortcode
    {
        $logger = $this->container->get('logger');
        $renderer = new TemplateRenderer(new TemplateVariableBuilder(), $logger, null);

        $shortcode = new YoutubeShortcode();
        $shortcode->setTemplateRenderer($renderer);
        $shortcode->setContainer($this->container);

        return $shortcode;
    }
}
