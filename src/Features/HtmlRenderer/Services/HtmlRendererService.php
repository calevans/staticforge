<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\HtmlRenderer\Services;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\PathGuard;
use EICC\StaticForge\Services\BaseRendererService;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\Utils\Container;
use EICC\Utils\Log;
use Exception;

class HtmlRendererService extends BaseRendererService
{
    private TemplateRenderer $templateRenderer;
    private Container $container;

    public function __construct(Log $logger, TemplateRenderer $templateRenderer, Container $container)
    {
        parent::__construct($logger);
        $this->templateRenderer = $templateRenderer;
        $this->container = $container;
    }

    /**
     * Process HTML file content and render it, mutating $event in place.
     */
    public function processHtmlFile(RenderEvent $event): void
    {
        $filePath = $event->filePath;

        // Only process .html files
        if ($filePath === '' || pathinfo($filePath, PATHINFO_EXTENSION) !== 'html') {
            return;
        }

        try {
            $this->logger->log('INFO', "Processing HTML file: {$filePath}");

            $metadata = $event->metadata;

            // Read file content
            if (isset($event->extra['file_content'])) {
                $content = $event->extra['file_content'];
            } else {
                // Security: Validate that the file path is within the source directory
                $sourceDir = $this->container->getVariable('SOURCE_DIR');
                if (!$sourceDir) {
                    throw new \RuntimeException('SOURCE_DIR not set in container');
                }

                try {
                    $realFilePath = PathGuard::resolveInside($filePath, $sourceDir);
                } catch (\RuntimeException $e) {
                    throw new \RuntimeException(
                        "Security Error: File path is outside the allowed source directory: {$filePath}"
                    );
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
            $htmlContent = $this->extractHtmlContent($content);

            // Apply default metadata
            $metadata = $this->applyDefaultMetadata($metadata);

            // Generate output file path
            $outputPath = $this->generateOutputPath($filePath, $this->container);

            // Apply template (pass source file path)
            $renderedContent = $this->templateRenderer->render([
                'metadata' => $metadata,
                'content' => $htmlContent,
                'title' => $metadata['title'] ?? 'Untitled',
            ], $this->container, $filePath);

            // Beautify HTML output
            $renderedContent = $this->beautifyHtml($renderedContent);

            $this->logger->log('INFO', "HTML file rendered: {$filePath}");

            // Store rendered content and metadata for Core to write
            $event->renderedContent = $renderedContent;
            $event->outputPath = $outputPath;
            $event->metadata = $metadata;
        } catch (Exception $e) {
            $this->logger->log('ERROR', "Failed to process HTML file {$filePath}: " . $e->getMessage());
            $event->extra['error'] = $e->getMessage();
        }
    }

    /**
     * Extract HTML content, skipping frontmatter
     *
     * @param string $content Full file content
     * @return string HTML content without frontmatter
     */
    public function extractHtmlContent(string $content): string
    {
        // Check for INI frontmatter (<!-- INI ... -->)
        if (preg_match('/^<!--\s*INI\s*(.*?)\s*-->\s*\n(.*)$/s', $content, $matches)) {
            return $matches[2];
        }

        // Check for YAML frontmatter (<!-- --- ... --- -->)
        if (preg_match('/^<!--\s*\n---\s*\n.*?\n---\s*\n-->\s*\n(.*)$/s', $content, $matches)) {
            return $matches[1];
        }

        return $content;
    }
}
