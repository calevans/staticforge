<?php

namespace EICC\StaticForge\Features\MarkdownRenderer;

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
 * Markdown Renderer Feature - processes .md files during RENDER event
 * Extracts INI frontmatter, converts Markdown to HTML, and applies templates
 */
class Feature extends BaseRendererFeature implements FeatureInterface
{
    protected string $name = 'MarkdownRenderer';
    protected Log $logger;
    private MarkdownProcessor $markdownProcessor;
    private ContentExtractor $contentExtractor;
    private PathGenerator $pathGenerator;
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
        $this->markdownProcessor = new MarkdownProcessor();
        $this->contentExtractor = new ContentExtractor();
        $this->pathGenerator = new PathGenerator();
        $this->templateRenderer = new TemplateRenderer(
            new TemplateVariableBuilder(),
            $this->logger
        );

        // Register .md extension for processing
        $extensionRegistry = $container->get(ExtensionRegistry::class);
        $extensionRegistry->registerExtension('.md');

        $this->logger->log('INFO', 'Markdown Renderer Feature registered');
    }

    /**
     * Handle RENDER event for Markdown files
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
            $content = $parameters['file_content'] ?? @file_get_contents($filePath);
            if ($content === false) {
                throw new Exception("Failed to read file: {$filePath}");
            }

            // Extract content (skip frontmatter)
            $markdownContent = $this->contentExtractor->extractMarkdownContent($content);

            // Convert Markdown to HTML
            $htmlContent = $this->markdownProcessor->convert($markdownContent);

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
            $outputPath = $parameters['output_path'] ?? $this->pathGenerator->generateOutputPath($filePath, $container);

            // Apply template (pass source file path)
            $renderedContent = $this->templateRenderer->render([
                'metadata' => $metadata,
                'content' => $htmlContent,
                'title' => $metadata['title'],
            ], $container, $filePath);

            $this->logger->log('INFO', "Markdown file rendered: {$filePath}");

            // Store rendered content and metadata for Core to write
            $parameters['rendered_content'] = $renderedContent;
            $parameters['output_path'] = $outputPath;
            $parameters['metadata'] = $metadata;
        } catch (Exception $e) {
            $this->logger->log('ERROR', "Failed to process Markdown file {$filePath}: " . $e->getMessage());
            $parameters['error'] = $e->getMessage();
        }

        return $parameters;
    }
}
