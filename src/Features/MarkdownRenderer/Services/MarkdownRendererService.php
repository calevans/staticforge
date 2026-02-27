<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\MarkdownRenderer\Services;

use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\MarkdownRenderer\ContentExtractor;
use EICC\StaticForge\Features\MarkdownRenderer\MarkdownProcessor;
use EICC\StaticForge\Services\BaseRendererService;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\Utils\Container;
use EICC\Utils\Log;
use Exception;

class MarkdownRendererService extends BaseRendererService
{
    private MarkdownProcessor $markdownProcessor;
    private ContentExtractor $contentExtractor;
    private TemplateRenderer $templateRenderer;

    public function __construct(
        Log $logger,
        MarkdownProcessor $markdownProcessor,
        ContentExtractor $contentExtractor,
        TemplateRenderer $templateRenderer
    ) {
        parent::__construct($logger);
        $this->markdownProcessor = $markdownProcessor;
        $this->contentExtractor = $contentExtractor;
        $this->templateRenderer = $templateRenderer;
    }

    /**
     * Process Markdown file content and render it
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function processMarkdownFile(Container $container, array $parameters): array
    {
        $filePath = $parameters['file_path'] ?? null;

        if (!$filePath) {
            return $parameters;
        }

        // Only process .md files
        if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'md') {
            return $parameters;
        }

        try {
            $this->logger->log('INFO', "Processing Markdown file: {$filePath}");

            // Get pre-parsed metadata from file discovery
            $metadata = $parameters['file_metadata'] ?? [];

            // Read file content
            // Use provided content if available (e.g., from CategoryIndex)
            if (isset($parameters['file_content'])) {
                $content = $parameters['file_content'];
            } else {
                // Security: Validate that the file path is within the source directory
                $sourceDir = $container->getVariable('SOURCE_DIR');
                if (!$sourceDir) {
                    throw new \RuntimeException('SOURCE_DIR not set in container');
                }

                // Allow vfs:// paths for testing
                if (strpos($filePath, 'vfs://') === 0) {
                    $realSourceDir = $sourceDir;
                    $realFilePath = $filePath;
                } else {
                    $realSourceDir = realpath($sourceDir);
                    $realFilePath = realpath($filePath);

                    if ($realFilePath === false || strpos($realFilePath, $realSourceDir) !== 0) {
                        throw new \RuntimeException("Security Error: File path is outside the allowed source directory: {$filePath}");
                    }
                }

                if (!is_readable($realFilePath)) {
                    throw new \RuntimeException("Failed to read file: {$filePath} (Permission denied or file not found)");
                }

                $content = file_get_contents($realFilePath);
                if ($content === false) {
                    throw new \RuntimeException("Failed to read file: {$filePath}");
                }
            }

            // Extract content (skip frontmatter)
            $markdownContent = $this->contentExtractor->extractMarkdownContent($content);

            // Convert Markdown to HTML
            $htmlContent = $this->markdownProcessor->convert($markdownContent);

            // Fix heading IDs (move from anchor to header)
            $htmlContent = $this->fixHeadingIds($htmlContent);

            // Fire MARKDOWN_CONVERTED event to allow modification (e.g., Table of Contents)
            $eventManager = $container->get(EventManager::class);
            $eventResult = $eventManager->fire('MARKDOWN_CONVERTED', [
                'html_content' => $htmlContent,
                'metadata' => $metadata,
                'file_path' => $filePath
            ]);

            $htmlContent = $eventResult['html_content'];
            $metadata = $eventResult['metadata'];

            // Extract title from metadata or first heading
            if (!isset($metadata['title'])) {
                $metadata['title'] = $this->contentExtractor->extractTitleFromContent($htmlContent);
            }

            // Apply default metadata
            $metadata = $this->applyDefaultMetadata($metadata);

            // Generate output file path (change .md to .html)
            // Use existing output_path if already set (e.g., by CategoryIndex)
            $outputPath = $parameters['output_path'] ?? $this->generateOutputPath($filePath, $container, 'html');

            // Apply template (pass source file path)
            $renderedContent = $this->templateRenderer->render([
                'metadata' => $metadata,
                'content' => $htmlContent,
                'title' => $metadata['title'],
            ], $container, $filePath);

            // Beautify HTML output
            $renderedContent = $this->beautifyHtml($renderedContent);

            $this->logger->log('INFO', "Markdown file rendered: {$filePath}");

            // Store rendered content and metadata for Core to write
            $parameters['rendered_content'] = $renderedContent;
            $parameters['output_path'] = $outputPath;
            $parameters['metadata'] = $metadata;
        } catch (\RuntimeException $e) {
            // Re-throw RuntimeExceptions (like missing templates) so they fail the build
            throw $e;
        } catch (Exception $e) {
            $this->logger->log('ERROR', "Failed to process Markdown file {$filePath}: " . $e->getMessage());
            $parameters['error'] = $e->getMessage();
        }

        return $parameters;
    }

    /**
     * Move IDs from permalink anchors to the parent heading elements
     */
    private function fixHeadingIds(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        // Hack for UTF-8
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $anchors = $xpath->query('//a[contains(@class, "heading-permalink")]');

        if ($anchors === false || $anchors->length === 0) {
            return $html;
        }

        $modified = false;
        foreach ($anchors as $anchor) {
            if (!$anchor instanceof \DOMElement) {
                continue;
            }

            $id = $anchor->getAttribute('id');
            if (empty($id)) {
                continue;
            }

            $parent = $anchor->parentNode;
            if ($parent instanceof \DOMElement && preg_match('/^h[1-6]$/i', $parent->nodeName)) {
                // Move ID to parent
                $parent->setAttribute('id', $id);
                // Remove ID from anchor
                $anchor->removeAttribute('id');
                $modified = true;
            }
        }

        if ($modified) {
            $result = $dom->saveHTML();
            if ($result === false) {
                return $html;
            }
            // Remove the XML declaration added by the UTF-8 hack
            $result = str_replace('<?xml encoding="utf-8" ?>', '', $result);
            return $result;
        }

        return $html;
    }
}
