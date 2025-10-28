<?php

namespace EICC\StaticForge\Features\MarkdownRenderer;

use EICC\StaticForge\Core\BaseFeature;
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
class Feature extends BaseFeature implements FeatureInterface
{
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
            $content = @file_get_contents($filePath);
            if ($content === false) {
                throw new Exception("Failed to read file: {$filePath}");
            }

            $parsedContent = $this->parseMarkdownFile($content);

            // Generate output file path (change .md to .html)
            $outputPath = $this->generateOutputPath($filePath, $container);

            // Apply template
            $renderedContent = $this->applyTemplate($parsedContent, $container);

            // Write output file
            $this->writeOutputFile($outputPath, $renderedContent);

            $this->logger->log('INFO', "Markdown file processed successfully: {$filePath} -> {$outputPath}");

            // Mark as processed
            $parameters['processed'] = true;
            $parameters['output_path'] = $outputPath;

        } catch (Exception $e) {
            $this->logger->log('ERROR', "Failed to process Markdown file {$filePath}: " . $e->getMessage());
            $parameters['error'] = $e->getMessage();
        }

        return $parameters;
    }

    /**
     * Parse Markdown file content, extracting YAML frontmatter if present
     */
    private function parseMarkdownFile(string $content): array
    {
        $metadata = [];
        $markdownContent = $content;

        // Check for YAML frontmatter (--- ... ---)
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            $yamlContent = trim($matches[1]);
            $markdownContent = trim($matches[2]);

            try {
                $metadata = Yaml::parse($yamlContent) ?? [];
                $this->logger->log('DEBUG', "Parsed YAML frontmatter: " . json_encode($metadata));
            } catch (Exception $e) {
                $this->logger->log('ERROR', "Failed to parse YAML frontmatter: " . $e->getMessage());
                $metadata = [];
            }
        }

        // Convert Markdown to HTML
        $htmlContent = $this->markdownConverter->convert($markdownContent)->getContent();

        // Extract title from metadata or first heading
        $title = $metadata['title'] ?? $this->extractTitleFromContent($htmlContent);

        // Extract template from metadata, default to 'base'
        $template = $metadata['template'] ?? 'base';

        return [
            'metadata' => $metadata,
            'content' => $htmlContent,
            'title' => $title,
            'template' => $template,
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

        // Remove source directory from path and change extension
        $relativePath = str_replace($sourceDir . '/', '', $inputPath);
        $relativePath = preg_replace('/\.md$/', '.html', $relativePath);

        // Ensure output directory exists
        $outputPath = $outputDir . '/' . $relativePath;
        $outputDirPath = dirname($outputPath);

        if (!is_dir($outputDirPath)) {
            mkdir($outputDirPath, 0755, true);
        }

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