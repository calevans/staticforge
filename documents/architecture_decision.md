# Architecture Decision: Template Installer

## The Core Question
Should `eicc/staticforge` (the main framework) be converted to `type: composer-plugin` to handle template installation, or should we create a separate `eicc/staticforge-installer` package?

## Option A: Single Package (Convert Framework to Plugin)
We change `composer.json` in `eicc/staticforge` to `type: composer-plugin`.

### Side Effects & Risks
1.  **Dependency Conflict (Critical)**:
    *   Composer plugins run *inside* the Composer process.
    *   This means the plugin package's dependencies must be compatible with the Composer application's internal dependencies.
    *   **Example**: `staticforge` requires `symfony/console: ^6.0`. If a user is running a version of Composer that uses `symfony/console: 5.x`, **installation will fail** for that user. They would be forced to upgrade/downgrade Composer to match your framework's dependencies.
2.  **Performance**: Composer loads the plugin logic on every command. While negligible now, if the framework grows, loading the massive autoloader for the framework just to run `composer install` is inefficient.
3.  **Semantics**: It is confusing for a "Framework" to be labeled as a "Plugin".

## Option B: Separate Package (`eicc/staticforge-installer`)
We create a small, standalone package solely for moving files.

### Pros
1.  **Zero Dependencies**: This package will only require `composer-plugin-api`. It will have **no** conflict with Composer's internal libraries.
2.  **Universal Compatibility**: It will work with almost any Composer version.
3.  **Clean Separation**: The framework remains `type: library`. The installer does one job.

### Cons
1.  **Maintenance**: Requires managing a second repository and Packagist entry.

## Recommendation
**Option B (Separate Package)** is the standard best practice for PHP frameworks (e.g., `laravel/installer`, `phpstan/extension-installer`).

However, since `eicc/staticforge` is currently a single repository, adopting **Option B** requires creating a new repo.

**If you want to avoid a new repo for now (Option A)**, we must accept that `staticforge` will only be installable by users with compatible Composer versions (likely Composer 2.2+).
