<?php

namespace EICC\StaticForge\Features\MarkdownRenderer;

use EICC\StaticForge\Core\BaseRendererFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\ExtensionRegistry;
use EICC\Utils\Container;
use EICC\Utils\Log;
use Exception;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader;

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
            $renderedContent = $this->applyTemplate([
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



    /**
     * Apply Twig template to rendered content
     *
     * @param array{metadata: array<string, mixed>, content: string} $parsedContent
     */
    private function applyTemplate(array $parsedContent, Container $container, string $sourceFile = ''): string
    {
        try {
            // Get template configuration
            $templateDir = $container->getVariable('TEMPLATE_DIR');
            if (!$templateDir) {
                throw new \RuntimeException('TEMPLATE_DIR not set in container');
            }
            $templateTheme = $container->getVariable('TEMPLATE') ?? 'sample';

            // Determine template: frontmatter > category > .env default
            $templateName = 'base'; // Ultimate fallback
            $this->logger->log('DEBUG', "Template metadata: " . json_encode($parsedContent['metadata']));

            if (isset($parsedContent['metadata']['template'])) {
                $templateName = $parsedContent['metadata']['template'];
                $this->logger->log('DEBUG', "Using frontmatter template: {$templateName}");
            } elseif (isset($parsedContent['metadata']['category'])) {
                // Check if category has a template
                $categoryTemplates = $container->getVariable('category_templates') ?? [];
                // Slugify category name to match how Categories stores them
                $categorySlug = $this->slugifyCategory($parsedContent['metadata']['category']);
                $this->logger->log('DEBUG', "Template lookup: category={$parsedContent['metadata']['category']}, slug={$categorySlug}, available=" . json_encode(array_keys($categoryTemplates)));
                if (isset($categoryTemplates[$categorySlug])) {
                    $templateName = $categoryTemplates[$categorySlug];
                    $this->logger->log('INFO', "Applied category template '{$templateName}' for category '{$parsedContent['metadata']['category']}'");
                }
            }
            $templateName .= '.html.twig';

            // Full template path
            $templatePath = $templateTheme . '/' . $templateName;

            $this->logger->log('INFO', "Using template: {$templatePath}");

            // Set up Twig with security enabled
            $loader = new FilesystemLoader($templateDir);
            // Add the specific template theme directory so includes work
            $loader->addPath($templateDir . '/' . $templateTheme);
            $twig = new TwigEnvironment($loader, [
                'debug' => true,
                'strict_variables' => false,
                'autoescape' => 'html',  // Enable auto-escaping for security
                'cache' => false,        // Disable cache for development
            ]);

            // Build template variables dynamically from all sources
            $templateVars = $this->buildTemplateVariables($parsedContent, $container, $sourceFile);

            // Render template
            return $twig->render($templatePath, $templateVars);
        } catch (Exception $e) {
            $this->logger->log('ERROR', "Template rendering failed: " . $e->getMessage());

            // Fallback to basic template
            return $this->applyBasicTemplate($parsedContent, $container);
        }
    }

    /**
     * Apply basic template as fallback
     *
     * @param array{metadata: array<string, mixed>, content: string} $parsedContent
     */
    private function applyBasicTemplate(array $parsedContent, Container $container): string
    {
        // Basic template - simple string replacement for fallback
        $siteConfig = $container->getVariable('site_config') ?? [];
        $siteInfo = $siteConfig['site'] ?? [];

        $siteName = $siteInfo['name'] ?? $container->getVariable('SITE_NAME') ?? 'Static Site';
        $siteBaseUrl = $container->getVariable('SITE_BASE_URL') ?? '';

        $template = $this->getBasicTemplate();

        $replacements = [
            '{{SITE_NAME}}' => $siteName,
            '{{SITE_BASE_URL}}' => $siteBaseUrl,
            '{{PAGE_TITLE}}' => $parsedContent['title'],
            '{{CONTENT}}' => $parsedContent['content'],
            '{{META_DESCRIPTION}}' => $parsedContent['metadata']['description'] ?? '',
            '{{META_KEYWORDS}}' => isset($parsedContent['metadata']['tags']) && is_array($parsedContent['metadata']['tags']) ? implode(', ', $parsedContent['metadata']['tags']) : ''
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
     * Slugify category name to match filename format
     */
    private function slugifyCategory(string $category): string
    {
        // Convert to lowercase and replace spaces/underscores with hyphens
        return strtolower(str_replace([' ', '_'], '-', $category));
    }
}
