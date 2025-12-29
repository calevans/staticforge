# Plan: Configuration Validation (site:check)

## Objective
Implement a system to validate `siteconfig.yaml` and `.env` settings against the requirements of installed features.

## Core Implementation

### 1. New Interface: `EICC\StaticForge\Core\ConfigurableFeatureInterface`
We will create a new interface that extends `FeatureInterface` (or stands alone, likely standalone to adhere to ISP).

```php
namespace EICC\StaticForge\Core;

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
```

### 2. New Command: `site:check`
We will create `src/Commands/CheckCommand.php`.
*   **Logic**:
    1.  Iterate through all registered features in the Container.
    2.  Check if the feature implements `ConfigurableFeatureInterface`.
    3.  If yes, validate `siteconfig.yaml` (using `dot` notation helper) and `$_ENV`.
    4.  Output a table of results: [Feature] [Type] [Key] [Status].
    5.  Return exit code 1 if any checks fail.

## External Feature Update Guide

To enable validation for external features, you will need to update them individually.

### Checklist for External Features

1.  **Update Dependency**: Ensure `composer.json` requires the version of `staticforge/core` that includes the new interface.
2.  **Implement Interface**: Update the main `Feature` class.

```php
use EICC\StaticForge\Core\ConfigurableFeatureInterface;

class Feature extends BaseFeature implements FeatureInterface, ConfigurableFeatureInterface
{
    public function getRequiredConfig(): array
    {
        return [
            'my_feature.api_key',
            'my_feature.settings.color',
        ];
    }

    public function getRequiredEnv(): array
    {
        return [
            'MY_FEATURE_SECRET',
        ];
    }
}
```

3.  **Release**: Tag a new version of the external feature.

## AI Agent Prompt Template
Use this prompt when asking an LLM to update an external feature repository:

> We need to update this StaticForge feature to support the new configuration validation system.
>
> 1.  **Update Dependencies**: In `composer.json`, ensure the requirement for `eicc/staticforge` allows for the latest version (e.g., `dev-main` or the latest tag) that includes `ConfigurableFeatureInterface`.
> 2.  **Implement Interface**: Modify the main Feature class (usually in `src/Feature.php`) to implement `EICC\StaticForge\Core\ConfigurableFeatureInterface`.
> 3.  **Define Requirements**:
>     *   Implement `getRequiredConfig(): array`: Return a list of dot-notation keys that MUST exist in `siteconfig.yaml` (e.g., `['google_analytics.id']`). Look at the code to see what config values are accessed.
>     *   Implement `getRequiredEnv(): array`: Return a list of environment variables that MUST exist in `.env` (e.g., `['GA_API_SECRET']`).
> 4.  **Verify**: Ensure the code is syntactically correct and follows PSR-12.
