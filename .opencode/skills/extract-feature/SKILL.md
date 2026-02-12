---
name: extract-feature
description: Extract an internal Feature from `src/Features/` into a standalone Composer package directory. Use when ready to publish a feature as an open-source library.
---

# Extract Feature

## When to use this
Use this skill when the user wants to "extract", "export", or "move" an existing Feature from the internal `src/Features/` directory into a new, standalone folder (usually outside the project root).

## Constraints
*   **DO NOT** modify the project's root `composer.json`.
*   **DO NOT** add local path repositories.
*   **DO NOT** `composer require` the new package.
*   The goal is purely to generate the external package artifact so the user can push it to GitHub/Packagist later.

## Workflow

1.  **Identify Arguments**:
    *   **FeatureName**: The folder name in `src/Features` (e.g., `SocialMetadata`).
    *   **Vendor**: Defaults to `calevans` (or user specified).
    *   **PackageName**: Defaults to `staticforge-<feature-kebab-case>` (e.g., `staticforge-social-metadata`).
    *   **Namespace**: Defaults to `<Vendor>\StaticForge<Feature>` (e.g., `Calevans\StaticForgeSocialMetadata`).

2.  **Execute Script**:
    Run the extraction tool which handles file copying, namespace updating, and test harness generation.
     ```bash
     php bin/extract_feature.php [FeatureName] [Vendor] [PackageName] [Namespace]
     ```

3.  **Report Success**:
    Inform the user where the new package is located and that it is ready for `git init`.

## Example
User: "Extract the RssFeed feature."
Action:
```bash
php bin/extract_feature.php RssFeed calevans staticforge-rss "Calevans\\StaticForgeRSS"
```
