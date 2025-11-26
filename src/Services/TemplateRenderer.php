<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services;

use EICC\Utils\Container;
use EICC\Utils\Log;
use Exception;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader;

class TemplateRenderer
{
    private TemplateVariableBuilder $variableBuilder;
    private Log $logger;

    public function __construct(TemplateVariableBuilder $variableBuilder, Log $logger)
    {
        $this->variableBuilder = $variableBuilder;
        $this->logger = $logger;
    }

    /**
     * Apply Twig template to rendered content
     *
     * @param array{metadata: array<string, mixed>, content: string, title: string} $parsedContent
     */
    public function render(array $parsedContent, Container $container, string $sourceFile = ''): string
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
            $templateVars = $this->variableBuilder->build($parsedContent, $container, $sourceFile);

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
     * @param array{metadata: array<string, mixed>, content: string, title: string} $parsedContent
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
