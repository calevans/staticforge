# Asset Manager

The `AssetManager` is a core service in StaticForge that handles the registration and dependency resolution of JavaScript and CSS assets. It allows features and shortcodes to inject assets into the page without worrying about duplicates or load order.

## Key Features

*   **Dependency Resolution**: Ensures scripts and styles are loaded in the correct order based on their dependencies.
*   **Deduplication**: Prevents the same asset from being loaded multiple times.
*   **Placement Control**: Supports loading scripts in the `<head>` or before the closing `</body>` tag.

## Usage

The `AssetManager` is available in the dependency injection container as `EICC\StaticForge\Core\AssetManager`.

### Adding a Script

```php
use EICC\StaticForge\Core\AssetManager;

// Get the manager from the container
$assetManager = $container->get(AssetManager::class);

// Add a script
// addScript(string $handle, string $src, array $deps = [], bool $inFooter = true)
$assetManager->addScript(
    'my-script',
    '/assets/js/my-script.js',
    ['jquery'], // Depends on 'jquery'
    true        // Load in footer
);
```

### Adding a Style

```php
// Add a stylesheet
// addStyle(string $handle, string $src, array $deps = [])
$assetManager->addStyle(
    'my-style',
    '/assets/css/my-style.css',
    ['bootstrap'] // Depends on 'bootstrap'
);
```

## Integration with Templates

The `AssetManager` automatically injects the generated HTML into your Twig templates via the `TemplateVariableBuilder`.

*   `{{ styles }}`: Contains all registered stylesheets.
*   `{{ head_scripts }}`: Contains scripts registered with `$inFooter = false`.
*   `{{ scripts }}`: Contains scripts registered with `$inFooter = true` (default).

Ensure your base template includes these variables:

```twig
<head>
    ...
    {% if styles %}{{ styles | raw }}{% endif %}
    {% if head_scripts %}{{ head_scripts | raw }}{% endif %}
</head>
<body>
    ...
    {% if scripts %}{{ scripts | raw }}{% endif %}
</body>
```
