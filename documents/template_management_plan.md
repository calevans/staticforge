# Template Management Implementation Plan (Separate Installer Package)

## Overview
We will implement the template installation logic in a separate package (`eicc/staticforge-installer`). This ensures the main framework (`eicc/staticforge`) remains clean and avoids dependency conflicts with Composer.

## Architecture

1.  **New Package**: `eicc/staticforge-installer`
    *   **Type**: `composer-plugin`
    *   **Dependencies**: Only `composer-plugin-api`.
    *   **Responsibility**: Hooks into Composer events to copy template files.

2.  **Main Framework**: `eicc/staticforge`
    *   **Type**: Remains `library`.
    *   **Dependency**: Adds `require: { "eicc/staticforge-installer": "^1.0" }`.

3.  **Template Packages**:
    *   **Type**: `staticforge-template` (Custom type supported by the installer).
    *   **Structure**: Contains template files.

## Development Workflow (Mocking the Separate Repo)
Since we are currently working inside the `staticforge` mono-repo context, we cannot easily "create a new repo" externally. **We will simulate this by creating the installer code inside a `packages/staticforge-installer` directory** and configuring Composer to load it locally.

## Implementation Steps

### Phase 1: Create Local Installer Package
1.  Create directory `packages/staticforge-installer`.
2.  Create `packages/staticforge-installer/composer.json`:
    ```json
    {
        "name": "eicc/staticforge-installer",
        "type": "composer-plugin",
        "require": {
            "composer-plugin-api": "^2.0"
        },
        "autoload": {
            "psr-4": { "EICC\\StaticForge\\Installer\\": "src/" }
        },
        "extra": {
            "class": "EICC\\StaticForge\\Installer\\Plugin"
        }
    }
    ```
3.  Create `packages/staticforge-installer/src/Plugin.php`:
    *   Implement the `install` / `uninstall` logic (copying `templates/` folders).
    *   It listens for packages of type `staticforge-template`.

### Phase 2: Link Installer to Main Project
1.  Modify the root `composer.json` of `staticforge`:
    *   Add a "path" repository:
        ```json
        "repositories": [
            {
                "type": "path",
                "url": "./packages/staticforge-installer"
            }
        ]
        ```
    *   Require the package: `"eicc/staticforge-installer": "*@dev"`.

### Phase 3: Testing
1.  Create a dummy template in `packages/dummy-template`.
2.  Run `composer require packages/dummy-template`.
3.  Verify the installer plugin triggers and copies files.

## Outcome
*   You will have the code for the installer ready.
*   You can later push `packages/staticforge-installer` to its own GitHub repository and publish it to Packagist.
*   The main project uses it seamlessly.

