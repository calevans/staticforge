<?php

namespace EICC\StaticForge\Core;

use EICC\Utils\Container;

/**
 * Base class for renderer features that process content files
 * Provides common functionality for handling metadata defaults and templates
 */
abstract class BaseRendererFeature extends BaseFeature
{
  /**
   * Default template to use when none is specified in frontmatter
   */
    private string $defaultTemplate = 'base';

  /**
   * Apply default metadata values, merging with provided metadata
   * Ensures consistent defaults across all renderer types
   *
   * @param array $metadata Metadata extracted from frontmatter
   * @return array Metadata with defaults applied
   */
    /**
     * Apply default metadata to file metadata
     * Merges default values with file-specific metadata
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    protected function applyDefaultMetadata(array $metadata): array
    {
        return array_merge([
        'template' => $this->defaultTemplate,
        'title' => 'Untitled Page',
        ], $metadata);
    }

    /**
     * Build template variables dynamically from all available sources
     * Merges file metadata, container variables, and flattened site config
     *
     * @param array<string, mixed> $parsedContent Content with metadata, title, content keys
     * @param Container $container Dependency injection container
     * @param string $sourceFile Source file path
     * @return array<string, mixed> Complete template variables array
     */
    protected function buildTemplateVariables(array $parsedContent, Container $container, string $sourceFile = ''): array
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
            'title' => $parsedContent['title'],
            'content' => $parsedContent['content'],
            'source_file' => $sourceFile,
        ]);

        // Merge file metadata (description, tags, etc. - these override as well)
        $templateVars = array_merge($templateVars, $parsedContent['metadata']);

        return $templateVars;
    }
}
