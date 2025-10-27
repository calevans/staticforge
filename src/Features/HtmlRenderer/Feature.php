<?php

namespace EICC\StaticForge\Features\HtmlRenderer;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use Exception;
use Symfony\Component\Yaml\Yaml;

/**
 * HTML Renderer Feature - processes .html files during RENDER event
 * Extracts YAML metadata, processes content, and writes output files
 */
class Feature extends BaseFeature implements FeatureInterface
{
    protected $logger;

    protected array $eventListeners = [
        'RENDER' => ['method' => 'handleRender', 'priority' => 100]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);

        // Get logger from container
        $this->logger = $container->getVariable('logger');

        // Register .html extension for processing
        $extensionRegistry = $container->get('extension_registry');
        $extensionRegistry->registerExtension('.html');

        $this->logger->log('INFO', 'HTML Renderer Feature registered');
    }

    /**
     * Handle RENDER event for HTML files
     */
    public function handleRender(Container $container, array $parameters): array
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

            // Read and parse the HTML file
            $content = @file_get_contents($filePath);
            if ($content === false) {
                throw new Exception("Failed to read file: {$filePath}");
            }

            $parsedContent = $this->parseHtmlFile($content);

            // Generate output file path
            $outputPath = $this->generateOutputPath($filePath, $container);

            // Apply basic template
            $renderedContent = $this->applyTemplate($parsedContent, $container);

            // Write output file
            $this->writeOutputFile($outputPath, $renderedContent);

            $this->logger->log('INFO', "HTML file processed successfully: {$filePath} -> {$outputPath}");

            // Mark as processed
            $parameters['processed'] = true;
            $parameters['output_path'] = $outputPath;

        } catch (Exception $e) {
            $this->logger->log('ERROR', "Failed to process HTML file {$filePath}: " . $e->getMessage());
            $parameters['error'] = $e->getMessage();
        }

        return $parameters;
    }

    /**
     * Parse HTML file content, extracting YAML metadata if present
     */
    private function parseHtmlFile(string $content): array
    {
        $metadata = [];
        $htmlContent = $content;

        // Check for YAML frontmatter (--- at start, --- to end)
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            try {
                $yamlContent = $matches[1];
                $htmlContent = $matches[2];

                if (!empty(trim($yamlContent))) {
                    $metadata = Yaml::parse($yamlContent) ?: [];
                }
            } catch (Exception $e) {
                $this->logger->log('WARNING', "Failed to parse YAML metadata: " . $e->getMessage());
                // Continue with empty metadata
            }
        }

        return [
            'metadata' => $metadata,
            'content' => $htmlContent,
            'title' => $metadata['title'] ?? 'Untitled Page',
            'menu_position' => $metadata['menu_position'] ?? null,
            'category' => $metadata['category'] ?? null,
            'tags' => $metadata['tags'] ?? []
        ];
    }

    /**
     * Generate output file path based on input file and configuration
     */
    private function generateOutputPath(string $inputPath, Container $container): string
    {
        $sourceDir = $container->getVariable('SOURCE_DIR');
        $outputDir = $container->getVariable('OUTPUT_DIR');

        // Get relative path from source directory
        $relativePath = str_replace($sourceDir, '', $inputPath);
        $relativePath = ltrim($relativePath, '/\\');

        // Build output path
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $relativePath;

        // Ensure output directory exists
        $outputDirPath = dirname($outputPath);
        if (!is_dir($outputDirPath)) {
            mkdir($outputDirPath, 0755, true);
        }

        return $outputPath;
    }

    /**
     * Apply basic template to rendered content
     */
    private function applyTemplate(array $parsedContent, Container $container): string
    {
        // Basic template - simple string replacement for now
        $siteName = $container->getVariable('SITE_NAME') ?? 'Static Site';
        $siteBaseUrl = $container->getVariable('SITE_BASE_URL') ?? '';

        $template = $this->getBasicTemplate();

        $replacements = [
            '{{SITE_NAME}}' => $siteName,
            '{{SITE_BASE_URL}}' => $siteBaseUrl,
            '{{PAGE_TITLE}}' => $parsedContent['title'],
            '{{CONTENT}}' => $parsedContent['content'],
            '{{META_DESCRIPTION}}' => $parsedContent['metadata']['description'] ?? '',
            '{{META_KEYWORDS}}' => is_array($parsedContent['tags']) ? implode(', ', $parsedContent['tags']) : ''
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Get basic HTML template
     */
    private function getBasicTemplate(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{PAGE_TITLE}} | {{SITE_NAME}}</title>
    <meta name="description" content="{{META_DESCRIPTION}}">
    <meta name="keywords" content="{{META_KEYWORDS}}">
    <base href="{{SITE_BASE_URL}}">
</head>
<body>
    <header>
        <h1>{{SITE_NAME}}</h1>
    </header>
    <main>
        {{CONTENT}}
    </main>
    <footer>
        <p>&copy; 2025 {{SITE_NAME}}. Generated by StaticForge.</p>
    </footer>
</body>
</html>
HTML;
    }

    /**
     * Write rendered content to output file
     */
    private function writeOutputFile(string $outputPath, string $content): void
    {
        $bytesWritten = file_put_contents($outputPath, $content);

        if ($bytesWritten === false) {
            throw new Exception("Failed to write output file: {$outputPath}");
        }

        $this->logger->log('INFO', "Written {$bytesWritten} bytes to {$outputPath}");
    }
}