<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services;

use EICC\StaticForge\Exceptions\InvalidSlugException;
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
    private ?TwigEnvironment $cachedTwig = null;
    private ?string $cachedTemplateDir = null;
    private ?string $cachedActiveTemplate = null;

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
        $templatePath = '';
        try {
            // Get template configuration
            $templateDir = $container->getVariable('TEMPLATE_DIR');
            if (!$templateDir) {
                throw new \RuntimeException('TEMPLATE_DIR not set in container');
            }
            $activeTemplate = $container->getVariable('TEMPLATE') ?? 'sample';

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
                if (!CategorySlugGuard::isUnsafe($categorySlug) && isset($categoryTemplates[$categorySlug])) {
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
            $templatePath = $activeTemplate . '/' . $templateName;

            $this->logger->log('INFO', "Using template: {$templatePath}");

            $twig = $this->getTwig($container, $templateDir, $activeTemplate);

            // Empty content stays empty so themes can still test {% if content %}
            if ($parsedContent['content'] !== '') {
                $parsedContent['content'] = ContentMarkers::wrap($parsedContent['content']);
            }

            // Build template variables dynamically from all sources
            $templateVars = $this->variableBuilder->build($parsedContent, $container, $sourceFile);

            // Render template
            $html = $twig->render($templatePath, $templateVars);

            // Auto-inject assets if AssetManager is available
            if ($this->assetManager) {
                $html = $this->injectAssets($html);
            }

            return $html;
        } catch (\Twig\Error\LoaderError $e) {
            $this->logger->log('ERROR', "Template file not found: {$templatePath}. Error: " . $e->getMessage());
            throw new \RuntimeException("Template file not found: {$templatePath}", 0, $e);
        } catch (\Twig\Error\SyntaxError $e) {
            $this->logger->log('ERROR', "Template syntax error in {$templatePath}: " . $e->getMessage());
            throw new \RuntimeException("Template syntax error in {$templatePath}", 0, $e);
        } catch (Exception $e) {
            $this->logger->log('ERROR', "Template rendering failed for {$templatePath}: " . $e->getMessage());
            throw new \RuntimeException("Template rendering failed for {$templatePath}", 0, $e);
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
            $activeTemplate = $container->getVariable('TEMPLATE') ?? 'sample';

            $twig = $this->getTwig($container, $templateDir, $activeTemplate);

            // Add global site config variables if available
            $siteConfig = $container->getVariable('site_config') ?? [];
            $variables = array_merge(['site' => $siteConfig], $variables);

            return $twig->render($templateName, $variables);
        } catch (Exception $e) {
            $this->logger->log('ERROR', "Partial template rendering failed for {$templateName}: " . $e->getMessage());
            return "<!-- Error rendering {$templateName} -->";
        }
    }

    private function getTwig(Container $container, string $templateDir, string $activeTemplate): TwigEnvironment
    {
        if ($container->has('twig')
            && $container->getVariable('twig_template_dir') === $templateDir
            && $container->getVariable('twig_active_template') === $activeTemplate
        ) {
            $twig = $container->get('twig');
            if ($twig instanceof TwigEnvironment) {
                $this->addFallbackPaths($twig);
                return $twig;
            }
        }

        if ($this->cachedTwig
            && $this->cachedTemplateDir === $templateDir
            && $this->cachedActiveTemplate === $activeTemplate
        ) {
            return $this->cachedTwig;
        }

        $loader = new FilesystemLoader($templateDir);
        // Add the active template directory so includes work
        $loader->addPath($templateDir . '/' . $activeTemplate);

        $twig = new TwigEnvironment($loader, [
            'debug' => true,
            'strict_variables' => false,
            'autoescape' => 'html',
            'cache' => false,
        ]);
        $this->addFallbackPaths($twig);
        $this->cachedTwig = $twig;
        $this->cachedTemplateDir = $templateDir;
        $this->cachedActiveTemplate = $activeTemplate;

        return $twig;
    }

    /**
     * Built-in shortcode templates ship with the package, appended last so a
     * theme's own shortcodes/*.twig still takes precedence.
     */
    private function addFallbackPaths(TwigEnvironment $twig): void
    {
        $loader = $twig->getLoader();
        if (!$loader instanceof FilesystemLoader) {
            return;
        }

        $shortcodeTemplates = dirname(__DIR__) . "/Shortcodes/templates";
        if (!in_array($shortcodeTemplates, $loader->getPaths(), true)) {
            $loader->addPath($shortcodeTemplates);
        }
    }

    /**
     * Inject any registered assets the template didn't render itself. The
     * exact strings AssetManager produces are deterministic for this build,
     * so their presence is the test - not whether the theme links some other
     * stylesheet of its own.
     */
    private function injectAssets(string $html): string
    {
        if ($this->assetManager === null) {
            return $html;
        }

        $headInjection = '';
        foreach ([$this->assetManager->getStyles(), $this->assetManager->getScripts(false)] as $markup) {
            if ($markup !== '' && !str_contains($html, $markup)) {
                $headInjection .= $markup;
            }
        }
        if ($headInjection !== '') {
            $html = $this->insertBefore($html, '</head>', $headInjection, false);
        }

        $footerScripts = $this->assetManager->getScripts(true);
        if ($footerScripts !== '' && !str_contains($html, $footerScripts)) {
            $html = $this->insertBefore($html, '</body>', $footerScripts, true);
        }

        return $html;
    }

    /**
     * Insert $markup before one occurrence of $tag (first or last), rather
     * than every occurrence - page content can legitimately contain the tag.
     */
    private function insertBefore(string $html, string $tag, string $markup, bool $last): string
    {
        $pos = $last ? strripos($html, $tag) : stripos($html, $tag);
        if ($pos === false) {
            return $html;
        }

        return substr($html, 0, $pos) . $markup . substr($html, $pos);
    }

    /**
     * Slugify category name to match filename format
     */
    private function slugifyCategory(string $category): string
    {
        try {
            return Slugger::category($category);
        } catch (InvalidSlugException) {
            return '';
        }
    }
}
