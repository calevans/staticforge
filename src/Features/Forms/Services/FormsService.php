<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Forms\Services;

use EICC\Utils\Container;
use EICC\Utils\Log;
use Twig\Environment;

class FormsService
{
    private Log $logger;
    private Environment $twig;

    public function __construct(Log $logger, Environment $twig)
    {
        $this->logger = $logger;
        $this->twig = $twig;
    }

    /**
     * Process content to replace form shortcodes with rendered forms
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function processForms(Container $container, array $parameters): array
    {
        $filePath = $parameters['file_path'] ?? null;
        $content = $parameters['file_content'] ?? null;

        if (!$content && $filePath && file_exists($filePath)) {
            // Security: Validate that the file path is within the source directory
            $sourceDir = $container->getVariable('SOURCE_DIR');
            if (!$sourceDir) {
                throw new \RuntimeException('SOURCE_DIR not set in container');
            }

            // Allow vfs:// paths for testing
            if (strpos($filePath, 'vfs://') === 0) {
                $realSourceDir = $sourceDir;
                $realFilePath = $filePath;
            } else {
                $realSourceDir = realpath($sourceDir);
                $realFilePath = realpath($filePath);

                if ($realFilePath === false || strpos($realFilePath, $realSourceDir) !== 0) {
                    throw new \RuntimeException("Security Error: File path is outside the allowed source directory: {$filePath}");
                }
            }

            if (!is_readable($realFilePath)) {
                $this->logger->log('WARNING', "Failed to read file (unreadable): {$filePath}");
                return $parameters;
            }

            $content = file_get_contents($realFilePath);
        }

        if (!$content) {
            return $parameters;
        }

        // Check for form shortcode: {{ form('formName') }}
        if (preg_match_all('/\{\{\s*form\([\'"]([a-zA-Z0-9_-]+)[\'"]\)\s*\}\}/', $content, $matches, PREG_SET_ORDER)) {
            $siteConfig = $container->getVariable('site_config') ?? [];
            $formsConfig = $siteConfig['forms'] ?? [];
            $activeTemplate = $container->getVariable('TEMPLATE') ?? 'staticforce';

            foreach ($matches as $match) {
                $fullMatch = $match[0];
                $formName = $match[1];

                if (!isset($formsConfig[$formName])) {
                    $this->logger->log('WARNING', "Form '{$formName}' not found in siteconfig.yaml");
                    continue;
                }

                $formHtml = $this->generateFormHtml($formsConfig[$formName], $activeTemplate);
                $content = str_replace($fullMatch, $formHtml, $content);
            }

            $parameters['file_content'] = $content;
        }

        return $parameters;
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
        $providerUrl = $config['provider_url'] ?? '';
        $formId = $config['form_id'] ?? '';

        // Ensure provider URL ends with ? or & if it has query params, or add ?FORMID=
        if (strpos($providerUrl, '?') !== false) {
            $endpoint = $providerUrl . '&FORMID=' . $formId;
        } else {
            $endpoint = $providerUrl . '?FORMID=' . $formId;
        }

        $context = [
            'endpoint' => $endpoint,
            'challenge_url' => $config['challenge_url'] ?? null,
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
}
