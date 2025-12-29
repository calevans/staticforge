<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core;

/**
 * Interface for features that require configuration validation.
 *
 * Features implementing this interface can define required keys for
 * siteconfig.yaml and environment variables. The site:check command
 * uses this to validate the project configuration.
 */
interface ConfigurableFeatureInterface
{
    /**
     * Returns an array of required keys for siteconfig.yaml.
     * Supports dot notation for nested keys (e.g., 'forms.contact.provider_url').
     *
     * @return string[]
     */
    public function getRequiredConfig(): array;

    /**
     * Returns an array of required environment variable names.
     *
     * @return string[]
     */
    public function getRequiredEnv(): array;
}
