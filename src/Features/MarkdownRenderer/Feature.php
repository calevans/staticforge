<?php

namespace EICC\StaticForge\Features\MarkdownRenderer;

use EICC\StaticForge\Core\BaseRendererFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use Exception;
use Symfony\Component\Yaml\Yaml;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader;

/**
 * Markdown Renderer Feature - processes .md files during RENDER event
 * Extracts YAML frontmatter, converts Markdown to HTML, and applies templates
 */
class Feature extends BaseRendererFeature implements FeatureInterface
{
    protected string $name = 'MarkdownRenderer';
    protected $logger;
    private MarkdownConverter $markdownConverter;

    protected array $eventListeners = [
        'RENDER' => ['method' => 'handleRender', 'priority' => 100]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);

        // Get logger from container
        $this->logger = $container->getVariable('logger');

        // Initialize Markdown converter
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $this->markdownConverter = new MarkdownConverter($environment);

        // Register .md extension for processing
        $extensionRegistry = $container->get('extension_registry');
        $extensionRegistry->registerExtension('.md');

        $this->logger->log('INFO', 'Markdown Renderer Feature registered');
    }

    /**
     * Handle RENDER event for Markdown files
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

            // Read and parse the Markdown file
            // Use provided content if available (e.g., from CategoryIndex)
            $content = $parameters['file_content'] ?? @file_get_contents($filePath);
            if ($content === false) {
                throw new Exception("Failed to read file: {$filePath}");
            }

            $parsedContent = $this->parseMarkdownFile($content);

            // Generate output file path (change .md to .html)
            // Use existing output_path if already set (e.g., by CategoryIndex)
            $outputPath = $parameters['output_path'] ?? $this->generateOutputPath($filePath, $container);

            // Apply template
            $renderedContent = $this->applyTemplate($parsedContent, $container);

            $this->logger->log('INFO', "Markdown file rendered: {$filePath}");

            // Store rendered content and metadata for Core to write
            $parameters['rendered_content'] = $renderedContent;
            $parameters['output_path'] = $outputPath;
            $parameters['metadata'] = $parsedContent['metadata'];

        } catch (Exception $e) {
            $this->logger->log('ERROR', "Failed to process Markdown file {$filePath}: " . $e->getMessage());
            $parameters['error'] = $e->getMessage();
        }

        return $parameters;
    }

    /**
     * Parse Markdown file content, extracting INI frontmatter if present
     */
    private function parseMarkdownFile(string $content): array
    {
        $metadata = [];
        $markdownContent = $content;

        // Check for INI frontmatter (--- ... ---)
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            $iniContent = trim($matches[1]);
            $markdownContent = trim($matches[2]);

            // Parse INI content
            if (!empty($iniContent)) {
                $lines = explode("\n", $iniContent);
                foreach ($lines as $line) {
                    $line = trim($line);
                    // INI format uses = not :
                    if (empty($line) || strpos($line, '=') === false) {
                        continue;
                    }

                    list($key, $value) = array_map('trim', explode('=', $line, 2));
                    // Remove quotes if present
                    $value = trim($value, '"\'');

                    // Handle arrays in square brackets [item1, item2]
                    if (preg_match('/^\[(.*)\]$/', $value, $arrayMatch)) {
                        $value = array_map('trim', explode(',', $arrayMatch[1]));
                    }

                    $metadata[$key] = $value;
                }
            }
        }

        // Convert Markdown to HTML
        $htmlContent = $this->markdownConverter->convert($markdownContent)->getContent();

        // Extract title from metadata or first heading
        if (!isset($metadata['title'])) {
            $metadata['title'] = $this->extractTitleFromContent($htmlContent);
        }

        // Apply default metadata
        $metadata = $this->applyDefaultMetadata($metadata);

        return [
            'metadata' => $metadata,
            'content' => $htmlContent,
            'title' => $metadata['title'],
            'template' => $metadata['template'],
            'tags' => $metadata['tags'] ?? []
        ];
    }

    /**
     * Extract title from HTML content if not in metadata
     */
    private function extractTitleFromContent(string $htmlContent): string
    {
        // Look for first h1-h6 tag
        if (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $htmlContent, $matches)) {
            return strip_tags($matches[1]);
        }

        return 'Untitled';
    }

    /**
     * Generate output file path, changing .md to .html
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
            // Change .md extension to .html
            $relativePath = preg_replace('/\.md$/', '.html', $relativePath);
        } else {
            // Fallback to filename only
            $relativePath = basename($inputPath);
            $relativePath = preg_replace('/\.md$/', '.html', $relativePath);
        }

        // Build output path preserving directory structure
        $outputPath = $outputDir . '/' . $relativePath;

        return $outputPath;
    }

    /**
     * Apply Twig template to rendered content
     */
    private function applyTemplate(array $parsedContent, Container $container): string
    {
        try {
            // Get template configuration
            $templateDir = $container->getVariable('TEMPLATE_DIR') ?? 'templates';
            $templateTheme = $container->getVariable('TEMPLATE') ?? 'sample';
            $templateName = $parsedContent['template'] . '.html.twig';

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

            // Prepare template variables
            $templateVars = array_merge($parsedContent['metadata'], [
                'title' => $parsedContent['title'],
                'content' => $parsedContent['content'],
                'site_name' => $container->getVariable('SITE_NAME') ?? 'Static Site',
                'site_base_url' => $container->getVariable('SITE_BASE_URL') ?? '',
                'site_tagline' => $container->getVariable('SITE_TAGLINE') ?? '',
                'features' => $container->getVariable('features') ?? [],
            ]);

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
     */
    private function applyBasicTemplate(array $parsedContent, Container $container): string
    {
        // Basic template - simple string replacement for fallback
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

}
