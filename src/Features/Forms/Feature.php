<?php

namespace EICC\StaticForge\Features\Forms;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'Forms';
    protected Log $logger;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'RENDER' => ['method' => 'handleRender', 'priority' => 50]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);
        $this->logger = $container->get('logger');
        $this->logger->log('INFO', 'Forms Feature registered');
    }

    /**
     * Handle RENDER event
     * Replaces form shortcodes with HTML forms
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handleRender(Container $container, array $parameters): array
    {
        $filePath = $parameters['file_path'] ?? null;
        $content = $parameters['file_content'] ?? null;

        if (!$content && $filePath && file_exists($filePath)) {
            $content = @file_get_contents($filePath);
        }

        if (!$content) {
            return $parameters;
        }

        // Check for form shortcode: {{ form('formName') }}
        if (preg_match_all('/\{\{\s*form\([\'"]([a-zA-Z0-9_-]+)[\'"]\)\s*\}\}/', $content, $matches, PREG_SET_ORDER)) {
            $siteConfig = $container->getVariable('site_config') ?? [];
            $formsConfig = $siteConfig['forms'] ?? [];
            $twig = $container->get('twig');
            $activeTemplate = $container->getVariable('TEMPLATE') ?? 'staticforce';

            foreach ($matches as $match) {
                $fullMatch = $match[0];
                $formName = $match[1];

                if (!isset($formsConfig[$formName])) {
                    $this->logger->log('WARNING', "Form '{$formName}' not found in siteconfig.yaml");
                    continue;
                }

                $formHtml = $this->generateFormHtml($formsConfig[$formName], $twig, $activeTemplate);
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
     * @param \Twig\Environment $twig
     * @param string $activeTemplate
     * @return string
     */
    private function generateFormHtml(array $config, $twig, string $activeTemplate): string
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
            if ($twig->getLoader()->exists($customTemplate)) {
                return $twig->render($customTemplate, $context);
            }
            $this->logger->log(
                'WARNING',
                "Custom form template '{$customTemplate}' not found. Falling back to default."
            );
        }

        return $twig->render('staticforce/_form.html.twig', $context);
    }
}
