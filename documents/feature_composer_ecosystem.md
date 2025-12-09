# Feature: Composer-Based Feature Ecosystem Burndown List

## Overview
Implement a system where features can be installed via Composer and automatically discovered by StaticForge. This allows for a modular ecosystem where features are separate packages, keeping the core lightweight.

## Principles
- **KISS**: `composer require` is the only installation step.
- **Zero Config**: Features auto-discover via `composer.json` metadata.
- **Centralized Config**: All config remains in `siteconfig.yaml` and `.env`.
- **Safety**: Features fail gracefully if misconfigured. No automatic modification of user config files.

## Architecture
- **Discovery**: `FeatureManager` scans `vendor/composer/installed.json` for packages with `extra.staticforge.feature` key.
- **Registration**: Instantiates the feature class defined in metadata.
- **Configuration**: Features read from existing `siteconfig.yaml` (namespaced keys) and `.env`.

## Tasks

### 1. Core Infrastructure (`src/Core/FeatureManager.php`)
- [x] **Refactor `loadFeatures`**: Add a step to scan `vendor/composer/installed.json`.
- [x] **Implement `discoverComposerFeatures`**:
    - Read `vendor/composer/installed.json`.
    - Iterate through packages.
    - Check for `extra.staticforge.feature` (class name).
    - Validate class exists.
    - Instantiate and register.
- [x] **Conflict Resolution**: Ensure Composer features don't overwrite local `src/Features` with the same name (Local wins).

### 2. Feature Interface Update (Optional but Recommended)
- [x] **Review `FeatureInterface`**: Ensure it supports the new lifecycle (if needed). *Decision: Current interface `register(EventManager, Container)` is sufficient.*

### 3. Testing & Validation
- [x] **Mock Package**: Create a dummy `installed.json` entry in tests to simulate a package.
- [x] **Unit Test**: Verify `FeatureManager` correctly loads the "external" feature.
- [x] **Integration Test**: Verify the feature's events are actually fired.

### 4. User Experience Improvements
- [x] **Implement `feature:setup <feature-name>` command**:
    - Locate the feature's package in `vendor/`.
    - Look for `siteconfig.yaml.example` and `.env.example` in the package root.
    - Copy them to the project root as `siteconfig.yaml.example.<feature>` and `.env.example.<feature>`.
    - Output instructions to the user to merge these files.

### 5. Documentation
- [x] **Developer Guide**: Create `documents/feature_development.md` explaining how to create a StaticForge-compatible package.
    - `composer.json` schema (`extra.staticforge`).
    - Class requirements.
    - Configuration best practices (fail gracefully).

### 6. Command Registration (Event-Driven)
- [x] **Update `bin/staticforge.php`**:
    - Dispatch a new event `CONSOLE_INIT` before `$app->run()`.
    - Pass the `$app` instance in the event data.
- [x] **Update Documentation**:
    - Add a section to `documents/feature_development.md` explaining how to register commands using the `CONSOLE_INIT` event.

## Example `composer.json` for a Feature Package
```json
{
    "name": "vendor/staticforge-podcast",
    "type": "library",
    "require": {
        "eicc/staticforge": "^1.0"
    },
    "extra": {
        "staticforge": {
            "feature": "Vendor\\Podcast\\Feature",
            "config_key": "podcast"
        }
    }
}
```
