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
        $filePath = $parameters['file_path'] ?? 'unknown';

        if (empty($htmlContent)) {
            return $parameters;
        }

        // Generate TOC
        $toc = $this->generateToc($htmlContent);

        if (!empty($toc)) {
            $this->logger->log('INFO', "TOC generated for {$filePath}: " . substr($toc, 0, 50) . "...");
        } else {
            $this->logger->log('INFO', "No TOC generated for {$filePath} (no headings found?)");
        }

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
        $itemsAdded = 0;

        foreach ($headings as $heading) {
            $level = (int) substr($heading->nodeName, 1);

            // Clone node to manipulate it without affecting the original DOM
            $clonedHeading = $heading->cloneNode(true);

            // Find the permalink anchor to get the ID
            // Note: HeadingPermalinkExtension adds <a class="heading-permalink" ...>
            $permalinks = $xpath->query('.//a[contains(@class, "heading-permalink")]', $clonedHeading);
            $permalinkId = '';

            if ($permalinks->length > 0) {
                $permalink = $permalinks->item(0);

                // Try to get ID from the anchor, or href (stripping #)
                $permalinkId = $permalink->getAttribute('id');
                if (empty($permalinkId)) {
                    $href = $permalink->getAttribute('href');
                    $permalinkId = ltrim($href, '#');
                }

                // Remove the permalink anchor from the text we want to display
                $permalink->parentNode->removeChild($permalink);
            }

            // Fallback to heading ID if permalink ID is not found
            if (empty($permalinkId)) {
                $permalinkId = $heading->getAttribute('id');
            }

            $text = trim($clonedHeading->textContent);
            $id = $permalinkId;

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
            $itemsAdded++;
        }

        // Close remaining tags
        while ($currentLevel > 2) {
            $toc .= '</ul>';
            $currentLevel--;
        }
        $toc .= '</ul>';

        if ($itemsAdded === 0) {
            return '';
        }

        return $toc;
    }
}
