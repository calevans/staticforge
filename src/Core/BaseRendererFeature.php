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
  protected function applyDefaultMetadata(array $metadata): array
  {
    return array_merge([
      'template' => $this->defaultTemplate,
      'title' => 'Untitled Page',
    ], $metadata);
  }

  /**
   * Get the default template name
   * Can be overridden by child classes if a different default is needed
   *
   * @return string Default template name
   */
  protected function getDefaultTemplate(): string
  {
    return $this->defaultTemplate;
  }

  /**
   * Set a custom default template (optional, for specialized renderers)
   *
   * @param string $template Template name to use as default
   * @return void
   */
  protected function setDefaultTemplate(string $template): void
  {
    $this->defaultTemplate = $template;
  }
}
