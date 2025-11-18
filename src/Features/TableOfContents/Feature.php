<?php

namespace EICC\StaticForge\Features\TableOfContents;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;
use DOMDocument;
use DOMXPath;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'TableOfContents';
    protected Log $logger;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'MARKDOWN_CONVERTED' => ['method' => 'handleMarkdownConverted', 'priority' => 500]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);
        $this->logger = $container->get('logger');
        $eventManager->registerEvent('MARKDOWN_CONVERTED');
        $this->logger->log('INFO', 'TableOfContents Feature registered');
    }

    /**
     * Handle MARKDOWN_CONVERTED event
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handleMarkdownConverted(Container $container, array $parameters): array
    {
        $htmlContent = $parameters['html_content'] ?? '';
        $metadata = $parameters['metadata'] ?? [];

        if (empty($htmlContent)) {
            return $parameters;
        }

        // Generate TOC
        $toc = $this->generateToc($htmlContent);

        // Add to metadata
        $metadata['toc'] = $toc;
        $parameters['metadata'] = $metadata;

        return $parameters;
    }

    private function generateToc(string $html): string
    {
        $dom = new DOMDocument();
        // Suppress warnings for HTML5 tags
        libxml_use_internal_errors(true);
        // Hack to handle UTF-8 correctly
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $headings = $xpath->query('//h2|//h3');

        if ($headings->length === 0) {
            return '';
        }

        $toc = '<ul class="toc-list">';
        $currentLevel = 2;

        foreach ($headings as $heading) {
            $level = (int) substr($heading->nodeName, 1);

            // Clone node to manipulate it without affecting the original DOM
            $clonedHeading = $heading->cloneNode(true);

            // Remove permalink anchors if present (added by HeadingPermalinkExtension)
            $permalinks = $xpath->query('.//a[contains(@class, "heading-permalink")]', $clonedHeading);
            foreach ($permalinks as $permalink) {
                $permalink->parentNode->removeChild($permalink);
            }

            $text = $clonedHeading->textContent;
            $id = $heading->getAttribute('id');

            if (empty($id)) {
                continue;
            }

            if ($level > $currentLevel) {
                $toc .= '<ul>';
            } elseif ($level < $currentLevel) {
                $toc .= '</ul>';
            }

            $toc .= sprintf('<li><a href="#%s">%s</a></li>', $id, htmlspecialchars($text));
            $currentLevel = $level;
        }

        // Close remaining tags
        while ($currentLevel > 2) {
            $toc .= '</ul>';
            $currentLevel--;
        }
        $toc .= '</ul>';

        return $toc;
    }
}
