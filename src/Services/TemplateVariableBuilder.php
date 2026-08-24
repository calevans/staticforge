<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services;

use EICC\StaticForge\Core\AssetManager;
use EICC\Utils\Container;

class TemplateVariableBuilder
{
    /**
     * Container variables safe to expose to templates. Everything else —
     * including every raw .env value (SFTP_PASSWORD, API keys, etc.) that
     * src/bootstrap.php copies onto the container — stays out. Page-specific
     * values (toc, pagination, category/tag listings, reading time, etc.)
     * don't go through this list at all; they arrive via $parsedContent['metadata'].
     *
     * @var array<int, string>
     */
    private const ALLOWED_CONTAINER_VARIABLES = [
        'site_config',
        'SITE_BASE_URL',
        'cache_buster',
        'features',
    ];

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
        $templateVars = $this->allowedContainerVariables($container);

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

        // Map site config values to top-level variables
        // This allows siteconfig.yaml to define site_name and site_tagline
        if (isset($templateVars['site']) && is_array($templateVars['site'])) {
            if (isset($templateVars['site']['name']) && !isset($templateVars['site_name'])) {
                $templateVars['site_name'] = $templateVars['site']['name'];
            }
            if (isset($templateVars['site']['tagline']) && !isset($templateVars['site_tagline'])) {
                $templateVars['site_tagline'] = $templateVars['site']['tagline'];
            }
        }

        // Normalize common env variable names to lowercase for template consistency
        // Templates expect site_base_url, etc. but env vars are UPPERCASE
        $envVarMap = [
            'SITE_BASE_URL' => 'site_base_url',
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

        // Inject AssetManager variables
        try {
            $assetManager = $container->get(AssetManager::class);
            $templateVars['scripts'] = $assetManager->getScripts(true); // Footer scripts
            $templateVars['head_scripts'] = $assetManager->getScripts(false); // Head scripts
            $templateVars['styles'] = $assetManager->getStyles();
        } catch (\Exception $e) {
            // AssetManager not available, ignore
        }

        return $templateVars;
    }

    /**
     * @return array<string, mixed>
     */
    private function allowedContainerVariables(Container $container): array
    {
        $allVariables = $container->getAllVariables();
        $allowed = [];

        foreach ($allVariables as $key => $value) {
            if (in_array($key, self::ALLOWED_CONTAINER_VARIABLES, true) || str_starts_with($key, 'menu')) {
                $allowed[$key] = $value;
            }
        }

        return $allowed;
    }
}
