<?php

namespace EICC\StaticForge\Features\HtmlRenderer;

use EICC\StaticForge\Core\BaseRendererFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\ExtensionRegistry;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\StaticForge\Services\TemplateVariableBuilder;
use EICC\Utils\Container;
use EICC\Utils\Log;
use Exception;

/**
 * HTML Renderer Feature - processes .html files during RENDER event
 * Extracts INI metadata, processes content, and writes output files
 */
class Feature extends BaseRendererFeature implements FeatureInterface
{
    protected string $name = 'HtmlRenderer';
    protected Log $logger;
    private TemplateRenderer $templateRenderer;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'RENDER' => ['method' => 'handleRender', 'priority' => 100]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);

        // Get logger from container
        $this->logger = $container->get('logger');

        // Initialize helpers
        $this->templateRenderer = new TemplateRenderer(
            new TemplateVariableBuilder(),
            $this->logger
        );

        // Register .html extension for processing
        $extensionRegistry = $container->get(ExtensionRegistry::class);
        $extensionRegistry->registerExtension('.html');

        $this->logger->log('INFO', 'HTML Renderer Feature registered');
    }

    /**
     * Handle RENDER event for HTML files
     *
     * Called dynamically by EventManager when RENDER event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
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
    private function extractHtmlContent(string $content): string
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
    private function generateOutputPath(string $inputPath, Container $container): string
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
}
