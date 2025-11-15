<?php

namespace EICC\StaticForge\Features\HtmlRenderer;

use EICC\StaticForge\Core\BaseRendererFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;
use Exception;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * HTML Renderer Feature - processes .html files during RENDER event
 * Extracts INI metadata, processes content, and writes output files
 */
class Feature extends BaseRendererFeature implements FeatureInterface
{
    protected string $name = 'HtmlRenderer';
    protected Log $logger;

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

        // Register .html extension for processing
        $extensionRegistry = $container->get('extension_registry');
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

            // Apply category template if file has category but no explicit template
            $metadata = $this->applyCategoryTemplate($metadata);

            // Apply default metadata
            $metadata = $this->applyDefaultMetadata($metadata);

            // Generate output file path
            $outputPath = $this->generateOutputPath($filePath, $container);

            // Apply basic template (pass source file path)
            $renderedContent = $this->applyTemplate([
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

        return $content;
    }

    /**
     * Generate output path for rendered HTML file
     */
    private function generateOutputPath(string $inputPath, Container $container): string
    {
        $sourceDir = $container->getVariable('SOURCE_DIR') ?? 'content';
        $outputDir = $container->getVariable('OUTPUT_DIR') ?? 'public';

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
     * Apply Twig template to rendered content
     *
     * @param array{metadata: array<string, mixed>, content: string} $parsedContent
     */
    private function applyTemplate(array $parsedContent, Container $container, string $sourceFile = ''): string
    {
        try {
            // Get template configuration
            $templateDir = $container->getVariable('TEMPLATE_DIR') ?? 'templates';
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
                if (isset($categoryTemplates[$parsedContent['metadata']['category']])) {
                    $templateName = $categoryTemplates[$parsedContent['metadata']['category']];
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
            $twig = new Environment($loader, [
                'debug' => true,
                'strict_variables' => false,
                'autoescape' => 'html',  // Enable auto-escaping for security
                'cache' => false,        // Disable cache for development
            ]);

            // Build template variables dynamically from all sources
            $templateVars = $this->buildTemplateVariables($parsedContent, $container, $sourceFile);

            $this->logger->log('DEBUG', "Template variables: " . json_encode(array_keys($templateVars)));

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
            '{{META_KEYWORDS}}' => isset($parsedContent['tags']) && is_array($parsedContent['tags'])
                ? implode(', ', $parsedContent['tags'])
                : ''
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
     * Apply category template if file has category but no explicit template
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function applyCategoryTemplate(array $metadata): array
    {
        // If file already has a template, don't override it
        if (isset($metadata['template'])) {
            return $metadata;
        }

        // If file has a category, check for category template
        if (isset($metadata['category'])) {
            $categoryTemplates = $this->container->getVariable('category_templates') ?? [];
            $category = $metadata['category'];

            if (isset($categoryTemplates[$category])) {
                $metadata['template'] = $categoryTemplates[$category];
                $this->logger->log(
                    'INFO',
                    "Applying category template '{$categoryTemplates[$category]}' for category '{$category}'"
                );
            }
        }

        return $metadata;
    }
}
