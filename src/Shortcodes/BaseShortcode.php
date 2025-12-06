<?php

declare(strict_types=1);

namespace EICC\StaticForge\Shortcodes;

use EICC\StaticForge\Features\MarkdownRenderer\MarkdownProcessor;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\Utils\Container;

abstract class BaseShortcode implements ShortcodeInterface
{
    protected ?TemplateRenderer $templateRenderer = null;
    protected ?MarkdownProcessor $markdownProcessor = null;
    protected ?Container $container = null;

    public function setTemplateRenderer(TemplateRenderer $renderer): void
    {
        $this->templateRenderer = $renderer;
    }

    public function setMarkdownProcessor(MarkdownProcessor $processor): void
    {
        $this->markdownProcessor = $processor;
    }

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    /**
     * Render a template with the given variables
     */
    protected function render(string $template, array $variables = []): string
    {
        if (!$this->templateRenderer || !$this->container) {
            throw new \RuntimeException('TemplateRenderer or Container not initialized in Shortcode');
        }

        return $this->templateRenderer->renderTemplate($template, $variables, $this->container);
    }

    /**
     * Process content as Markdown
     */
    protected function processMarkdown(string $content): string
    {
        if (!$this->markdownProcessor) {
            // Lazy load if not set (though Manager should set it)
            $this->markdownProcessor = new MarkdownProcessor();
        }

        return $this->markdownProcessor->convert($content);
    }
}
