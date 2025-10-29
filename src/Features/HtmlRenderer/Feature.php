<?php

namespace EICC\StaticForge\Features\HtmlRenderer;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use Exception;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * HTML Renderer Feature - processes .html files during RENDER event
 * Extracts YAML metadata, processes content, and writes output files
 */
class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'HtmlRenderer';
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

            $this->logger->log('INFO', "HTML file rendered: {$filePath}");

            // Store rendered content and metadata for Core to write
            $parameters['rendered_content'] = $renderedContent;
            $parameters['output_path'] = $outputPath;
            $parameters['metadata'] = $parsedContent['metadata'];

        } catch (Exception $e) {
            $this->logger->log('ERROR', "Failed to process HTML file {$filePath}: " . $e->getMessage());
            $parameters['error'] = $e->getMessage();
        }

        return $parameters;
    }

    /**
     * Parse HTML file content, extracting INI metadata if present
     */
    private function parseHtmlFile(string $content): array
    {
        $metadata = [];
        $htmlContent = $content;

        // Check for INI frontmatter (<!-- INI ... -->)
        if (preg_match('/^<!--\s*INI\s*(.*?)\s*-->\s*\n(.*)$/s', $content, $matches)) {
            $iniContent = trim($matches[1]);
            $htmlContent = $matches[2];

            // Parse INI content if present
            if (!empty($iniContent)) {
                $lines = explode("\n", $iniContent);
                foreach ($lines as $line) {
                    $line = trim($line);
                    // INI format uses = not : (unlike YAML)
                    if (empty($line) || strpos($line, '=') === false) {
                        continue;
                    }

                    list($key, $value) = array_map('trim', explode('=', $line, 2));
                    // Remove quotes if present
                    $value = trim($value, '"\'');
                    $metadata[$key] = $value;
                }
            }
        }

        return [
            'metadata' => $metadata,
            'content' => $htmlContent,
            'title' => $metadata['title'] ?? 'Untitled Page',
            'template' => $metadata['template'] ?? 'base',
            'menu_position' => $metadata['menu_position'] ?? null,
            'category' => $metadata['category'] ?? null,
            'tags' => isset($metadata['tags']) ? explode(',', $metadata['tags']) : []
        ];
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
            $twig = new Environment($loader, [
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
    }}