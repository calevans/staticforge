<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Forms\Services;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\PathGuard;
use EICC\StaticForge\Services\HtmlPlaceholders;
use EICC\Utils\Container;
use EICC\Utils\Log;
use Twig\Environment;

class FormsService
{
    private Log $logger;
    private Environment $twig;
    private Container $container;

    public function __construct(Log $logger, Environment $twig, Container $container)
    {
        $this->logger = $logger;
        $this->twig = $twig;
        $this->container = $container;
    }

    /**
     * Process content to replace form shortcodes with rendered forms
     */
    public function processForms(RenderEvent $event): void
    {
        $filePath = $event->filePath;
        $content = $event->extra['file_content'] ?? null;

        if (!$content && $filePath !== '' && file_exists($filePath)) {
            // Security: Validate that the file path is within the source directory
            $sourceDir = $this->container->getVariable('SOURCE_DIR');
            if (!$sourceDir) {
                throw new \RuntimeException('SOURCE_DIR not set in container');
            }

            try {
                $realFilePath = PathGuard::resolveInside($filePath, $sourceDir);
            } catch (\RuntimeException $e) {
                throw new \RuntimeException(
                    "Security Error: File path is outside the allowed source directory: {$filePath}"
                );
            }

            if (!is_readable($realFilePath)) {
                $this->logger->log('WARNING', "Failed to read file (unreadable): {$filePath}");
                return;
            }

            $content = file_get_contents($realFilePath);
        }

        if (!$content) {
            return;
        }

        $replaced = $this->replaceForms(
            $content,
            static fn (string $formHtml): string => HtmlPlaceholders::reserve($event, $formHtml)
        );
        if ($replaced !== $content) {
            $event->extra['file_content'] = $replaced;
        }

        // A form inside a shortcode's body is already parked as HTML, out of file_content's reach
        HtmlPlaceholders::transform(
            $event,
            fn (string $html): string => $this->replaceForms($html, static fn (string $formHtml): string => $formHtml)
        );
    }

    /**
     * Replace each {{ form('formName') }} in $text with the rendered form, passed through $wrap.
     *
     * @param callable(string): string $wrap
     */
    private function replaceForms(string $text, callable $wrap): string
    {
        if (!preg_match_all('/\{\{\s*form\([\'"]([a-zA-Z0-9_-]+)[\'"]\)\s*\}\}/', $text, $matches, PREG_SET_ORDER)) {
            return $text;
        }

        $siteConfig = $this->container->getVariable('site_config') ?? [];
        $formsConfig = $siteConfig['forms'] ?? [];
        $activeTemplate = $this->container->getVariable('TEMPLATE') ?? 'staticforce';

        foreach ($matches as $match) {
            $fullMatch = $match[0];
            $formName = $match[1];

            if (!isset($formsConfig[$formName])) {
                $this->logger->log('WARNING', "Form '{$formName}' not found in siteconfig.yaml");
                continue;
            }

            $formHtml = $this->generateFormHtml($formsConfig[$formName], $activeTemplate);
            $text = str_replace($fullMatch, $wrap($formHtml), $text);
        }

        return $text;
    }

    /**
     * Generate HTML for a form based on configuration
     *
     * @param array<string, mixed> $config
     * @param string $activeTemplate
     * @return string
     */
    public function generateFormHtml(array $config, string $activeTemplate): string
    {
        $providerUrl = $this->requireHttpsUrl($config['provider_url'] ?? '', 'provider_url');
        $formId = $config['form_id'] ?? '';

        // Ensure provider URL ends with ? or & if it has query params, or add ?FORMID=
        if ($providerUrl === '') {
            $endpoint = '';
        } elseif (strpos($providerUrl, '?') !== false) {
            $endpoint = $providerUrl . '&FORMID=' . $formId;
        } else {
            $endpoint = $providerUrl . '?FORMID=' . $formId;
        }

        $context = [
            'endpoint' => $endpoint,
            'challenge_url' => $this->requireHttpsUrl($config['challenge_url'] ?? '', 'challenge_url') ?: null,
            'submit_text' => $config['submit_text'] ?? 'Submit',
            'success_message' => $config['success_message'] ?? 'Thank you for your message.',
            'error_message' => $config['error_message'] ?? 'There was an error sending your message.',
            'fields' => $config['fields'] ?? [],
        ];

        // Check for custom template
        if (!empty($config['template'])) {
            $customTemplate = $activeTemplate . '/' . $config['template'] . '.html.twig';
            if ($this->twig->getLoader()->exists($customTemplate)) {
                return $this->twig->render($customTemplate, $context);
            }
            $this->logger->log(
                'WARNING',
                "Custom form template '{$customTemplate}' not found. Falling back to default."
            );
        }

        return $this->twig->render('staticforce/_form.html.twig', $context);
    }

    /**
     * Returns $url unchanged if it's https: (or empty), otherwise logs a warning
     * and returns an empty string so an http:/javascript:-shaped value never
     * reaches rendered HTML.
     */
    private function requireHttpsUrl(string $url, string $configKey): string
    {
        if ($url === '' || str_starts_with($url, 'https://')) {
            return $url;
        }

        $this->logger->log('WARNING', "Form {$configKey} must use https:, ignoring: {$url}");
        return '';
    }
}
