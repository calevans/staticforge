<?php

namespace EICC\StaticForge\Core;

use EICC\Utils\Container;
use Gajus\Dindent\Indenter;

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
   * Beautify HTML content using Dindent
   *
   * @param string $html Raw HTML content
   * @return string Beautified HTML content
   */
    protected function beautifyHtml(string $html): string
    {
        try {
            $indenter = new Indenter();
            return $indenter->indent($html);
        } catch (\Exception $e) {
            // If beautification fails, return original HTML and log warning
            // We don't have access to logger here directly unless we add it to base class
            // or pass it in. For now, fail safe.
            return $html;
        }
    }

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
}
