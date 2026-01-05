<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services;

use EICC\Utils\Container;
use EICC\Utils\Log;
use EICC\StaticForge\Core\AssetManager;
use Exception;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader;

class TemplateRenderer
{
    private TemplateVariableBuilder $variableBuilder;
    private Log $logger;
    private ?AssetManager $assetManager;

    public function __construct(TemplateVariableBuilder $variableBuilder, Log $logger, ?AssetManager $assetManager = null)
    {
        $this->variableBuilder = $variableBuilder;
        $this->logger = $logger;
        $this->assetManager = $assetManager;
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
                $this->logger->log(
                    'DEBUG',
                    "Template lookup: category={$parsedContent['metadata']['category']}, " .
                    "slug={$categorySlug}, available=" . json_encode(array_keys($categoryTemplates))
                );
                if (isset($categoryTemplates[$categorySlug])) {
                    $templateName = $categoryTemplates[$categorySlug];
                    $this->logger->log(
                        'INFO',
                        "Applied category template '{$templateName}' " .
                        "for category '{$parsedContent['metadata']['category']}'"
                    );
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
            $html = $twig->render($templatePath, $templateVars);

            // Auto-inject assets if AssetManager is available
            if ($this->assetManager) {
                $html = $this->injectAssets($html);
            }

            return $html;
        } catch (Exception $e) {
            $this->logger->log('ERROR', "Template rendering failed: " . $e->getMessage());

            // Fallback to basic template
            return $this->applyBasicTemplate($parsedContent, $container);
        }
    }

    /**
     * Render a specific template with provided variables
     * Used by Shortcodes and other features needing direct template rendering
     *
     * @param string $templateName Template name (e.g., 'shortcodes/youtube.html.twig')
     * @param array<string, mixed> $variables Variables to pass to the template
     * @param Container $container Dependency injection container
     * @return string Rendered HTML
     */
    public function renderTemplate(string $templateName, array $variables, Container $container): string
    {
        try {
            $templateDir = $container->getVariable('TEMPLATE_DIR');
            if (!$templateDir) {
                throw new \RuntimeException('TEMPLATE_DIR not set in container');
            }
            $templateTheme = $container->getVariable('TEMPLATE') ?? 'sample';

            // Set up Twig
            $loader = new FilesystemLoader($templateDir);
            // Add the specific template theme directory so includes work
            $loader->addPath($templateDir . '/' . $templateTheme);

            // Also add the root templates directory to find shared templates like shortcodes
            // if they are not in the theme

            $twig = new TwigEnvironment($loader, [
                'debug' => true,
                'strict_variables' => false,
                'autoescape' => 'html',
                'cache' => false,
            ]);

            // Add global site config variables if available
            $siteConfig = $container->getVariable('site_config') ?? [];
            $variables = array_merge(['site' => $siteConfig], $variables);

            return $twig->render($templateName, $variables);
        } catch (Exception $e) {
            $this->logger->log('ERROR', "Partial template rendering failed for {$templateName}: " . $e->getMessage());
            return "<!-- Error rendering {$templateName} -->";
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
        $siteBaseUrl = $container->getVariable('SITE_BASE_URL');
        if ($siteBaseUrl === null) {
            throw new \RuntimeException('SITE_BASE_URL not set in container');
        }

        $template = $this->getBasicTemplate();

        $replacements = [
            '{{SITE_NAME}}' => $siteName,
            '{{SITE_BASE_URL}}' => $siteBaseUrl,
            '{{PAGE_TITLE}}' => $parsedContent['title'],
            '{{CONTENT}}' => $parsedContent['content'],
            '{{META_DESCRIPTION}}' => $parsedContent['metadata']['description'] ?? '',
            '{{META_KEYWORDS}}' => isset($parsedContent['metadata']['tags']) &&
                is_array($parsedContent['metadata']['tags'])
                ? implode(', ', $parsedContent['metadata']['tags'])
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
     * Inject assets into HTML if they haven't been rendered by the template
     */
    private function injectAssets(string $html): string
    {
        // Check if styles were rendered
        if (strpos($html, '<!-- ASSETS:STYLES -->') === false && strpos($html, '<link rel="stylesheet"') === false) {
            // We can't easily know if specific styles were rendered, but we can check if the variable was used.
            // A better approach is to check if the AssetManager's output is present.
            // However, since we pass the strings to the template, we can't know for sure.
            // Strategy: Look for </head>. If found, inject styles and head scripts before it.
            
            $styles = $this->assetManager->getStyles();
            $headScripts = $this->assetManager->getScripts(false);
            
            if ($styles || $headScripts) {
                $injection = $styles . $headScripts;
                // Only inject if not already present (simple check)
                // This is imperfect but handles the "forgot to add {{ styles }}" case.
                // We assume if the user added {{ styles }}, the content is there.
                // But since we generate the content fresh, we can't compare easily.
                // Let's rely on a marker or just inject if missing.
                
                // Actually, TemplateVariableBuilder passes the strings.
                // If the template didn't output them, they are missing.
                // We can try to detect if the specific asset strings are in the HTML.
                // But that's expensive.
                
                // Let's just look for </head> and inject.
                // To avoid duplication, we could wrap the output in a comment marker in AssetManager,
                // but AssetManager returns raw HTML strings.
                
                // For now, we will just inject before </head> and </body>.
                // Users should use the variables for control. This is a fallback.
                
                // Simple heuristic: If the HTML doesn't contain the exact string returned by getStyles(), inject it.
                // This works because getStyles() returns a deterministic string for the current state.
                
                if ($styles && strpos($html, $styles) === false) {
                    $html = str_replace('</head>', $styles . '</head>', $html);
                }
                if ($headScripts && strpos($html, $headScripts) === false) {
                    $html = str_replace('</head>', $headScripts . '</head>', $html);
                }
            }
        }

        $scripts = $this->assetManager->getScripts(true);
        if ($scripts && strpos($html, $scripts) === false) {
            $html = str_replace('</body>', $scripts . '</body>', $html);
        }

        return $html;
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
