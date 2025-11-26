<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services;

use EICC\Utils\Container;

class TemplateVariableBuilder
{
    /**
     * Build template variables dynamically from all available sources
     * Merges file metadata, container variables, and flattened site config
     *
     * @param array<string, mixed> $parsedContent Content with metadata, title, content keys
     * @param Container $container Dependency injection container
     * @param string $sourceFile Source file path
     * @return array<string, mixed> Complete template variables array
     */
    public function build(array $parsedContent, Container $container, string $sourceFile = ''): array
    {
        // Start with all container variables
        $templateVars = $container->getAllVariables();

        // Flatten site_config to top level (site, menu, chapter_nav, etc.)
        // This allows templates to access {{ site.name }}, {{ menu.top }}, etc.
        $siteConfig = $templateVars['site_config'] ?? [];
        if (is_array($siteConfig)) {
            foreach ($siteConfig as $key => $value) {
                // Don't override if already exists as a standalone variable
                if (!isset($templateVars[$key])) {
                    $templateVars[$key] = $value;
                }
            }
        }

        // Normalize common env variable names to lowercase for template consistency
        // Templates expect site_name, site_base_url, etc. but env vars are UPPERCASE
        $envVarMap = [
            'SITE_NAME' => 'site_name',
            'SITE_BASE_URL' => 'site_base_url',
            'SITE_TAGLINE' => 'site_tagline',
        ];

        foreach ($envVarMap as $envKey => $templateKey) {
            if (isset($templateVars[$envKey]) && !isset($templateVars[$templateKey])) {
                $templateVars[$templateKey] = $templateVars[$envKey];
            }
        }

        // Add file-specific content (these override any container variables with same names)
        $templateVars = array_merge($templateVars, [
            'title' => $parsedContent['title'] ?? '',
            'content' => $parsedContent['content'] ?? '',
            'source_file' => $sourceFile,
        ]);

        // Merge file metadata (description, tags, etc. - these override as well)
        if (isset($parsedContent['metadata']) && is_array($parsedContent['metadata'])) {
            $templateVars = array_merge($templateVars, $parsedContent['metadata']);
        }

        return $templateVars;
    }
}
