# Implementation Plan: audit:config-check

## Overview
This plan details the consolidation of configuration checks into a new command `audit:config-check`. This replaces the existing `site:check` command and expands its scope to include core system configuration and environment sanity checks.

## Goals
1.  **Rename & Migrate**: Move logic from `CheckCommand.php` (`site:check`) to a new `AuditConfigCheckCommand.php` (`audit:config-check`).
2.  **Expand Scope**: Add checks for:
    *   Core `siteconfig.yaml` schema validation (beyond feature-specific keys).
    *   Filesystem path validation (ensure configured directories exist).
    *   Environment file existence (`.env`).

## Proposed Command Class
**File**: `src/Commands/Audit/ConfigCheckCommand.php`
**Command Signature**: `audit:config-check`

### Logic Flow
1.  **Core Config Validation**:
    *   Load `siteconfig.yaml`.
    *   Check for critical top-level keys: `baseUrl`, `theme`, `paths` (if applicable).
    *   **New**: Validate that directories pointed to by config (e.g., `content/`, `templates/`, `public/`) actually exist and are readable.

2.  **Environment Validation**:
    *   Check if `.env` file exists in the project root.
    *   (Existing) Check for feature-specific required ENV vars.

3.  **Feature Config Validation (Legacy `site:check`)**:
    *   Iterate through `ConfigurableFeatureInterface` features.
    *   Validate feature-specific `siteconfig` keys.
    *   Validate feature-specific `.env` variables.

4.  **Reporting**:
    *   Use `SymfonyStyle` to output a consolidated report.
    *   Group errors by "Core System" vs "Feature: [Name]".

## Detailed Tasks
1.  **Create Directory**: Create `src/Commands/Audit/` if it doesn't exist.
2.  **create `ConfigCheckCommand.php`**: Implement the class extending `Command`.
    *   Copy existing logic from `CheckCommand.php`.
    *   Add validation for `baseUrl` and `theme`.
    *   Add `is_dir()` checks for configured paths.
    *   Add file existence check for `.env`.
3.  **Deprecate/Remove `CheckCommand.php`**: Remove the old file or alias it to the new command with a deprecation warning (User instruction implies replacement: "Move the command"). We will delete the old file.
4.  **Registration**: Ensure the new command is registered in `src/Utilities/Container.php` or wherever commands are loaded (Likely `bin/staticforge.php` or a Service Provider).

## Verification
*   Run `lando php bin/staticforge.php audit:config-check`.
*   Verify it catches missing `baseUrl`.
*   Verify it catches missing `.env` (temporarily rename it).
*   Verify it still reports missing feature configurations.
