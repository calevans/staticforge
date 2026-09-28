<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\TableOfContents\Services;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\Utils\Log;
use DOMDocument;
use DOMXPath;

class TableOfContentsService
{
    private Log $logger;

    public function __construct(Log $logger)
    {
        $this->logger = $logger;
    }

    public function handleMarkdownConverted(RenderEvent $event): void
    {
        $htmlContent = $event->renderedContent ?? '';
        $filePath = $event->filePath !== '' ? $event->filePath : 'unknown';

        if ($htmlContent === '') {
            return;
        }

        $toc = $this->generateToc($htmlContent);

        if (!empty($toc)) {
            $this->logger->log('INFO', "TOC generated for {$filePath}: " . substr($toc, 0, 50) . "...");
        } else {
            $this->logger->log('INFO', "No TOC generated for {$filePath} (no headings found?)");
        }

        $event->metadata['toc'] = $toc;
    }

    public function generateToc(string $html): string
    {
        $dom = new DOMDocument();
        // Suppress warnings for HTML5 tags
        libxml_use_internal_errors(true);
        // Hack to handle UTF-8 correctly
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $headings = $xpath->query('//h2|//h3');

        if ($headings === false || $headings->length === 0) {
            return '';
        }

        $toc = '<ul class="toc-list">';
        $currentDepth = 0;
        $itemsAdded = 0;

        foreach ($headings as $heading) {
            if (!$heading instanceof \DOMElement) {
                continue;
            }

            $level = (int) substr($heading->nodeName, 1);

            // Clone node to manipulate it without affecting the original DOM
            /** @var \DOMElement $clonedHeading */
            $clonedHeading = $heading->cloneNode(true);

            // Find the permalink anchor to get the ID
            // Note: HeadingPermalinkExtension adds <a class="heading-permalink" ...>
            $permalinks = $xpath->query('.//a[contains(@class, "heading-permalink")]', $clonedHeading);
            $permalinkId = '';

            if ($permalinks && $permalinks->length > 0) {
                $permalink = $permalinks->item(0);

                if ($permalink instanceof \DOMElement) {
                    // Try to get ID from the anchor, or href (stripping #)
                    $permalinkId = $permalink->getAttribute('id');
                    if (empty($permalinkId)) {
                        $href = $permalink->getAttribute('href');
                        $permalinkId = ltrim($href, '#');
                    }

                    // Remove the permalink anchor from the text we want to display
                    if ($permalink->parentNode) {
                        $permalink->parentNode->removeChild($permalink);
                    }
                }
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

            // Sub-lists must sit inside the parent <li>, and a level can't be
            // skipped, so an h3 with no preceding h2 stays at the top level.
            $depth = min($level - 2, $itemsAdded > 0 ? $currentDepth + 1 : 0);
            if ($depth > $currentDepth) {
                $toc .= '<ul>';
            } else {
                if ($itemsAdded > 0) {
                    $toc .= '</li>';
                }
                for (; $currentDepth > $depth; $currentDepth--) {
                    $toc .= '</ul></li>';
                }
            }

            $toc .= sprintf(
                '<li><a href="#%s">%s</a>',
                htmlspecialchars($id, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
            );
            $currentDepth = $depth;
            $itemsAdded++;
        }

        if ($itemsAdded > 0) {
            $toc .= '</li>';
        }
        for (; $currentDepth > 0; $currentDepth--) {
            $toc .= '</ul></li>';
        }
        $toc .= '</ul>';

        if ($itemsAdded === 0) {
            return '';
        }

        return $toc;
    }
}
