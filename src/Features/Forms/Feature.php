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
            $formsReplaced = false;

            foreach ($matches as $match) {
                $fullMatch = $match[0];
                $formName = $match[1];

                if (!isset($formsConfig[$formName])) {
                    $this->logger->log('WARNING', "Form '{$formName}' not found in siteconfig.yaml");
                    continue;
                }

                $formHtml = $this->generateFormHtml($formName, $formsConfig[$formName]);
                $content = str_replace($fullMatch, $formHtml, $content);
                $formsReplaced = true;
            }

            // Inject CSS if forms were replaced
            if ($formsReplaced) {
                $content = $this->injectFormCss($content);
            }

            $parameters['file_content'] = $content;
        }

        return $parameters;
    }

    /**
     * Generate HTML for a form based on configuration
     *
     * @param string $formName
     * @param array<string, mixed> $config
     * @return string
     */
    private function generateFormHtml(string $formName, array $config): string
    {
        $endpoint = $config['endpoint'] ?? '';
        $submitText = $config['submit_text'] ?? 'Submit';
        $fields = $config['fields'] ?? [];

        $html = "<form action=\"{$endpoint}\" method=\"POST\" class=\"sf-form\">\n";

        foreach ($fields as $field) {
            $name = $field['name'];
            $label = $field['label'] ?? ucfirst($name);
            $type = $field['type'] ?? 'text';
            $required = !empty($field['required']) ? 'required' : '';
            $placeholder = $field['placeholder'] ?? '';

            $html .= "  <div class=\"sf-form-group\">\n";
            $html .= "    <label for=\"{$name}\" class=\"sf-label\">{$label}</label>\n";

            if ($type === 'textarea') {
                $rows = $field['rows'] ?? 5;
                $html .= "    <textarea name=\"{$name}\" id=\"{$name}\" rows=\"{$rows}\" class=\"sf-input\" placeholder=\"{$placeholder}\" {$required}></textarea>\n";
            } else {
                $html .= "    <input type=\"{$type}\" name=\"{$name}\" id=\"{$name}\" class=\"sf-input\" placeholder=\"{$placeholder}\" {$required}>\n";
            }

            $html .= "  </div>\n";
        }

        $html .= "  <button type=\"submit\" class=\"sf-button\">{$submitText}</button>\n";
        $html .= "</form>\n";

        return $html;
    }

    /**
     * Inject default CSS for forms
     *
     * @param string $content
     * @return string
     */
    private function injectFormCss(string $content): string
    {
        // Simple check to avoid double injection
        if (strpos($content, 'id="sf-forms-css"') !== false) {
            return $content;
        }

        $css = <<<CSS
<style id="sf-forms-css">
.sf-form {
    max-width: 600px;
    margin: 20px 0;
    font-family: inherit;
}
.sf-form-group {
    margin-bottom: 15px;
}
.sf-label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}
.sf-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 16px;
    box-sizing: border-box;
}
.sf-input:focus {
    border-color: #666;
    outline: none;
}
.sf-button {
    background-color: #333;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
}
.sf-button:hover {
    background-color: #555;
}
</style>
CSS;
        return $content . "\n" . $css;
    }
}
