# Feature Development Guide

This guide explains how to create a standalone feature package for StaticForge that can be installed via Composer.

## Overview

StaticForge supports a modular ecosystem where features can be developed as separate Composer packages. These packages are automatically discovered and loaded by the system.

## Creating a Feature Package

### 1. Directory Structure

A typical feature package structure:

```
my-feature/
├── composer.json
├── src/
│   └── Feature.php
├── .env.example
└── siteconfig.yaml.example
```

### 2. The Feature Class

Your feature must implement `EICC\StaticForge\Core\FeatureInterface`.

```php
namespace Vendor\MyFeature;

use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;

class Feature implements FeatureInterface
{
    public function getName(): string
    {
        return 'MyFeature';
    }

    public function register(EventManager $eventManager, Container $container): void
    {
        // Register event listeners
        $eventManager->addEventListener('site.render.pre', [$this, 'onPreRender']);
    }

    public function onPreRender(array $data): void
    {
        // Your logic here
    }
}
```

### 3. Composer Configuration (`composer.json`)

You must add a `staticforge` entry to the `extra` section of your `composer.json`. This tells StaticForge which class to load.

```json
{
    "name": "vendor/my-feature",
    "type": "library",
    "require": {
        "eicc/staticforge": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "Vendor\\MyFeature\\": "src/"
        }
    },
    "extra": {
        "staticforge": {
            "feature": "Vendor\\MyFeature\\Feature",
            "config_key": "my_feature"
        }
    }
}
```

### 4. Configuration Files

If your feature requires configuration, **do not** attempt to modify the user's files automatically. Instead, provide example files in the root of your package:

*   **`.env.example`**: For secrets (API keys, passwords).
*   **`siteconfig.yaml.example`**: For general configuration.

The user can run `bin/console feature:setup vendor/my-feature` to copy these examples to their project root.

**Best Practice:**
Your feature should check for configuration values and fail gracefully (log a warning) if they are missing, rather than crashing the build.

```php
$apiKey = getenv('MY_FEATURE_API_KEY');
if (!$apiKey) {
    $container->get('logger')->log('WARNING', 'MyFeature: API key missing. Feature disabled.');
    return;
}
```

### 5. Registering Console Commands

Features can register their own CLI commands by listening to the `CONSOLE_INIT` event.

```php
use Symfony\Component\Console\Application;
use Vendor\MyFeature\Commands\MyCommand;

public function register(EventManager $eventManager, Container $container): void
{
    // Listen for console initialization
    $eventManager->registerListener('CONSOLE_INIT', [$this, 'onConsoleInit']);
}

public function onConsoleInit(Container $container, array $data): void
{
    /** @var Application $app */
    $app = $data['application'];

    // Add your commands
    $app->add(new MyCommand());
}
```

## Strict Isolation Rules

To maintain the integrity of the core system, you must adhere to the following rules:

1.  **Self-Containment**: All code for your feature (Commands, Services, Templates, etc.) MUST reside within your package's directory structure.
2.  **No Core Pollution**: Do not attempt to modify core files or inject code into other namespaces.
3.  **Namespace**: Use a unique namespace for your feature (e.g., `Vendor\MyFeature\`) to avoid conflicts.

