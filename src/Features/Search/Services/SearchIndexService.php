<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Search\Services;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\OutputWriter;
use EICC\Utils\Container;
use EICC\Utils\Log;

class SearchIndexService
{
    private Log $logger;
    private OutputWriter $outputWriter;
    private Container $container;

    /**
     * @var array<int, array{id: int, title: string, text: string, url: string, tags: string, category: mixed}>
     */
    private array $documents = [];
    private int $idCounter = 1;

    public function __construct(Log $logger, OutputWriter $outputWriter, Container $container)
    {
        $this->logger = $logger;
        $this->outputWriter = $outputWriter;
        $this->container = $container;
    }

    /**
     * Collect page data for the search index
     */
    public function collectPage(RenderEvent $event): void
    {
        $metadata = $event->metadata;
        $outputPath = $event->outputPath;
        $content = $event->renderedContent ?? '';

        // 1. Check if page should be indexed
        if (!$this->shouldIndex($event)) {
            return;
        }

        // If output_path is null, we can't index it (e.g., if rendering failed)
        if ($outputPath === null) {
            return;
        }

        // 2. Calculate URL
        $url = $this->calculateUrl($outputPath);

        // 3. Parse content into sections
        $pageTitle = $metadata['title'] ?? 'Untitled';
        $sections = $this->parseHtmlSections($content, $pageTitle);

        // 4. Create documents
        $tags = $metadata['tags'] ?? [];
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }
        $tagsString = implode(' ', $tags);
        $category = $metadata['category'] ?? '';

        foreach ($sections as $section) {
            // Skip empty sections
            if (empty(trim($section['text']))) {
                continue;
            }

            $sectionUrl = $url;
            if (!empty($section['anchor'])) {
                $sectionUrl .= '#' . $section['anchor'];
            }

            $doc = [
                'id' => $this->idCounter++,
                'title' => $section['title'],
                'text' => mb_substr(trim($section['text']), 0, 5000),
                'url' => $sectionUrl,
                'tags' => $tagsString,
                'category' => $category,
            ];

            $this->documents[] = $doc;
        }
    }

    /**
     * Parse HTML content into sections based on headers
     *
     * @param string $html
     * @param string $defaultTitle
     * @return array<array{title: string, anchor: string, text: string}>
     */
    private function parseHtmlSections(string $html, string $defaultTitle): array
    {
        if (empty($html)) {
            return [];
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        // Hack for UTF-8
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Remove script, style, head
        $nodesToRemove = $xpath->query('//script | //style | //head');
        if ($nodesToRemove !== false) {
            foreach ($nodesToRemove as $node) {
                if ($node instanceof \DOMNode && $node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        // Find all headers and text nodes
        // We select h1-h6 and text nodes
        $query = '//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6] | //text()';
        $nodes = $xpath->query($query);
        if ($nodes === false) {
            return [];
        }

        $sections = [];
        $currentSection = [
            'title' => $defaultTitle,
            'anchor' => '',
            'text' => ''
        ];

        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement) {
                // It's a header
                // Save previous section if it has content
                if (!empty(trim($currentSection['text']))) {
                    $sections[] = $currentSection;
                }

                // Start new section
                $headerText = trim($node->textContent);
                $anchor = $node->getAttribute('id');

                $currentSection = [
                    'title' => $headerText ?: $defaultTitle,
                    'anchor' => $anchor,
                    'text' => ''
                ];
            } elseif ($node instanceof \DOMText) {
                // It's text
                // If the parent of this text node is a header, we skip it because we used it for the title.
                $parent = $node->parentNode;
                if ($parent && preg_match('/^h[1-6]$/i', $parent->nodeName)) {
                    continue;
                }

                $text = preg_replace('/\s+/', ' ', $node->textContent);
                $currentSection['text'] .= ' ' . $text;
            }
        }

        // Add last section
        if (!empty(trim($currentSection['text']))) {
            $sections[] = $currentSection;
        }

        return $sections;
    }

    /**
     * Build the search index and write it to disk
     */
    public function buildIndex(): void
    {
        $outputDir = $this->container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            $this->logger->log('ERROR', 'OUTPUT_DIR not set, cannot write search index');
            return;
        }

        $this->logger->log('INFO', 'Building search index with ' . count($this->documents) . ' documents');

        // Write search.json
        $json = json_encode($this->documents, JSON_PRETTY_PRINT);
        if ($json === false) {
            $this->logger->log('ERROR', 'Failed to encode search index to JSON');
            return;
        }

        $indexPath = $outputDir . '/search.json';
        try {
            $this->outputWriter->write($indexPath, $json);
        } catch (\Throwable $e) {
            $this->logger->log('ERROR', "Failed to write search index to {$indexPath}: " . $e->getMessage());
        }
    }

    private function shouldIndex(RenderEvent $event): bool
    {
        $metadata = $event->metadata;
        $outputPath = $event->outputPath ?? '';

        // Check frontmatter exclusion
        if (isset($metadata['search_index']) && $metadata['search_index'] === false) {
            return false;
        }

        // Check config exclusions
        $config = $this->container->getVariable('site_config')['search'] ?? [];
        $excludePaths = $config['exclude_paths'] ?? [];
        $excludeContentIn = $config['exclude_content_in'] ?? [];

        // Get relative path for checking
        $outputDir = $this->container->getVariable('OUTPUT_DIR');
        $relativePath = str_replace($outputDir, '', $outputPath);

        // Check exclude_paths (exact matches or starts with)
        foreach ($excludePaths as $exclude) {
            if (str_starts_with($relativePath, $exclude)) {
                return false;
            }
        }

        // Check exclude_content_in
        foreach ($excludeContentIn as $exclude) {
            if (str_starts_with($relativePath, $exclude)) {
                return false;
            }
        }

        return true;
    }

    private function calculateUrl(string $outputPath): string
    {
        $siteUrl = $this->container->getVariable('SITE_BASE_URL');
        if ($siteUrl === null) {
            throw new \RuntimeException('SITE_BASE_URL not set in container');
        }
        $siteUrl = rtrim($siteUrl, '/');
        $outputDir = $this->container->getVariable('OUTPUT_DIR');
        $relativePath = str_replace($outputDir, '', $outputPath);

        return $siteUrl . $relativePath;
    }
}
