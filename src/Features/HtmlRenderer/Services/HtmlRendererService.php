<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\HtmlRenderer\Services;

use EICC\StaticForge\Services\BaseRendererService;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\Utils\Container;
use EICC\Utils\Log;
use Exception;

class HtmlRendererService extends BaseRendererService
{
    private TemplateRenderer $templateRenderer;

    public function __construct(Log $logger, TemplateRenderer $templateRenderer)
    {
        parent::__construct($logger);
        $this->templateRenderer = $templateRenderer;
    }

    /**
     * Process HTML file content and render it
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function processHtmlFile(Container $container, array $parameters): array
    {
        $filePath = $parameters['file_path'] ?? null;

        if (!$filePath) {
            return $parameters;
        }

        // Only process .html files
        if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'html') {
            return $parameters;
        }

        try {
            $this->logger->log('INFO', "Processing HTML file: {$filePath}");

            // Get pre-parsed metadata from file discovery
            $metadata = $parameters['file_metadata'] ?? [];

            // Read file content
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
            $htmlContent = $this->extractHtmlContent($content);

            // Apply default metadata
            $metadata = $this->applyDefaultMetadata($metadata);

            // Generate output file path
            $outputPath = $this->generateOutputPath($filePath, $container);

            // Apply template (pass source file path)
            $renderedContent = $this->templateRenderer->render([
                'metadata' => $metadata,
                'content' => $htmlContent,
                'title' => $metadata['title'] ?? 'Untitled',
            ], $container, $filePath);

            // Beautify HTML output
            $renderedContent = $this->beautifyHtml($renderedContent);

            $this->logger->log('INFO', "HTML file rendered: {$filePath}");

            // Store rendered content and metadata for Core to write
            $parameters['rendered_content'] = $renderedContent;
            $parameters['output_path'] = $outputPath;
            $parameters['metadata'] = $metadata;
        } catch (Exception $e) {
            $this->logger->log('ERROR', "Failed to process HTML file {$filePath}: " . $e->getMessage());
            $parameters['error'] = $e->getMessage();
        }

        return $parameters;
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
