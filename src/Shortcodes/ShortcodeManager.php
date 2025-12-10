<?php

declare(strict_types=1);

namespace EICC\StaticForge\Shortcodes;

use EICC\StaticForge\Features\MarkdownRenderer\MarkdownProcessor;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\Utils\Container;
use EICC\Utils\Log;

class ShortcodeManager
{
    private Container $container;
    private Log $logger;
    private TemplateRenderer $templateRenderer;
    private MarkdownProcessor $markdownProcessor;

    /**
     * @var array<string, ShortcodeInterface>
     */
    private array $shortcodes = [];

    public function __construct(
        Container $container,
        TemplateRenderer $templateRenderer
    ) {
        $this->container = $container;
        $this->logger = $container->get('logger');
        $this->templateRenderer = $templateRenderer;
        $this->markdownProcessor = new MarkdownProcessor();
    }

    public function register(ShortcodeInterface $shortcode): void
    {
        // Inject dependencies if it extends BaseShortcode
        if ($shortcode instanceof BaseShortcode) {
            $shortcode->setContainer($this->container);
            $shortcode->setTemplateRenderer($this->templateRenderer);
            $shortcode->setMarkdownProcessor($this->markdownProcessor);
        }

        $this->shortcodes[$shortcode->getName()] = $shortcode;
        $this->logger->log('DEBUG', "Registered shortcode: [[{$shortcode->getName()}]]");
    }

    public function process(string $content): string
    {
        if (empty($this->shortcodes)) {
            return $content;
        }

        // Regex to match shortcodes with optional escaping
        // 1. Optional opening bracket (escaping)
        // 2. Tag Name
        // 3. Attributes
        // 4. Content (optional)
        // 5. Optional closing bracket (escaping)
        $pattern = '/(\[?)\[\[\s*([a-zA-Z0-9_-]+)([^\]]*?)\]\](?:([\s\S]*?)\[\[\/\2\]\])?(\]?)/';

        return preg_replace_callback($pattern, function ($matches) {
            $fullMatch = $matches[0];
            $escapeOpen = $matches[1];
            $tagName = $matches[2];
            $attributesStr = $matches[3];
            $innerContent = $matches[4];
            $escapeClose = $matches[5];

            // Handle escaping: [[[tag]]] -> [[tag]]
            if ($escapeOpen === '[' && $escapeClose === ']') {
                // Return the inner part without the outer brackets
                return substr($fullMatch, 1, -1);
            }

            // If the tag is not registered, return it as is.
            if (!isset($this->shortcodes[$tagName])) {
                return $fullMatch;
            }

            $attributes = $this->parseAttributes($attributesStr);

            try {
                return $this->shortcodes[$tagName]->handle($attributes, $innerContent);
            } catch (\Exception $e) {
                $this->logger->log('ERROR', "Shortcode [[{$tagName}]] failed: " . $e->getMessage());
                return "<!-- Shortcode error: {$tagName} -->";
            }
        }, $content);
    }

    /**
     * Parse attributes string into array
     * e.g. id="123" type="warning" -> ['id' => '123', 'type' => 'warning']
     *
     * @return array<string, string>
     */
    private function parseAttributes(string $text): array
    {
        $attributes = [];
        $pattern = '/(\w+)\s*=\s*"([^"]*)"(?:\s|$)|(\w+)\s*=\s*\'([^\']*)\'(?:\s|$)|(\w+)(?:\s|$)/';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (!empty($match[1])) {
                    $attributes[$match[1]] = $match[2] ?? '';
                } elseif (!empty($match[3])) {
                    $attributes[$match[3]] = $match[4] ?? '';
                } elseif (!empty($match[5])) {
                    $attributes[$match[5]] = 'true';
                }
            }
        }

        return $attributes;
    }
}
