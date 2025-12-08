<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\HtmlRenderer\Services;

use EICC\StaticForge\Services\TemplateRenderer;
use EICC\Utils\Container;
use EICC\Utils\Log;
use Exception;
use Gajus\Dindent\Indenter;

class HtmlRendererService
{
    private Log $logger;
    private TemplateRenderer $templateRenderer;
    private string $defaultTemplate = 'base';

    public function __construct(Log $logger, TemplateRenderer $templateRenderer)
    {
        $this->logger = $logger;
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
            $content = @file_get_contents($filePath);
            if ($content === false) {
                throw new Exception("Failed to read file: {$filePath}");
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

    /**
     * Generate output path for rendered HTML file
     */
    public function generateOutputPath(string $inputPath, Container $container): string
    {
        $sourceDir = $container->getVariable('SOURCE_DIR');
        if (!$sourceDir) {
            throw new \RuntimeException('SOURCE_DIR not set in container');
        }
        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set in container');
        }

        // Normalize paths for comparison (handle both real and virtual filesystems)
        $normalizedSourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR);
        $normalizedInputPath = $inputPath;

        // Check if input path starts with source directory
        if (strpos($normalizedInputPath, $normalizedSourceDir) === 0) {
            // Get path relative to source directory
            $relativePath = substr($normalizedInputPath, strlen($normalizedSourceDir) + 1);
        } else {
            // Fallback to filename only
            $relativePath = basename($inputPath);
        }

        // Build output path preserving directory structure
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $relativePath;

        return $outputPath;
    }

    /**
     * Apply default metadata to file metadata
     * Merges default values with file-specific metadata
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function applyDefaultMetadata(array $metadata): array
    {
        return array_merge([
            'template' => $this->defaultTemplate,
            'title' => 'Untitled Page',
        ], $metadata);
    }

    /**
     * Beautify HTML content using Dindent
     *
     * @param string $html Raw HTML content
     * @return string Beautified HTML content
     */
    public function beautifyHtml(string $html): string
    {
        $originalHtml = $html;

        // Protect <pre> and <textarea> tags from whitespace collapsing
        $protectedBlocks = [];
        $html = preg_replace_callback(
            '/<(pre|textarea)\b[^>]*>([\s\S]*?)<\/\1>/im',
            function ($matches) use (&$protectedBlocks) {
                $placeholder = '<!--PROTECTED_BLOCK_' . count($protectedBlocks) . '-->';
                $protectedBlocks[$placeholder] = $matches[0];
                return $placeholder;
            },
            $html
        );

        try {
            $indenter = new Indenter();
            $html = $indenter->indent($html);
        } catch (\Exception $e) {
            // If beautification fails, return original HTML
            return $originalHtml;
        }

        // Restore protected blocks
        if (!empty($protectedBlocks)) {
            $html = str_replace(array_keys($protectedBlocks), array_values($protectedBlocks), $html);
        }

        return $html;
    }
}
