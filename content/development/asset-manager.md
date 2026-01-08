---
title: 'Asset Manager'
description: 'Documentation for the StaticForge AssetManager service, handling JS/CSS dependency resolution and injection.'
template: docs
menu: '4.1.6'
url: "https://calevans.com/staticforge/development/asset-manager.html"
og_image: "Digital asset management interface, floating holographic icons of CSS JS and images, organized grid, futuristic UI, cyan and purple lighting, --ar 16:9"
---

# The Asset Manager: Orchestrating Chaos

Managing CSS and Javascript manually is a recipe for disaster. You end up with duplicate tags, scripts loading in the wrong order, and `jQuery is not defined` errors.

The **Asset Manager** is the traffic cop of StaticForge. It ensures your scripts and styles load exactly when and where they should.

## The Core Problem

Imagine two different features (like a Photo Gallery and a Slider) both need jQuery.
*   **Without Asset Manager:** Both features add `<script src="jquery.js">`. Your page loads jQuery twice. Chaos ensue.
*   **With Asset Manager:** Both features tell the manager "I need jQuery." The manager nods, checks its list, and outputs the script tag **once**.

---

## How it Works

The Asset Manager is a core service available in the dependency injection container. You can grab it anywhere in your code.

### 1. Adding a Script

```php
use EICC\StaticForge\Core\AssetManager;

// Get the manager
$assetManager = $container->get(AssetManager::class);

// addScript(handle, path, dependencies, inFooter)
$assetManager->addScript(
    'my-script',               // The Handle: A unique name
    '/assets/js/app.js',       // The Path
    ['jquery'],                // Dependencies: Load 'jquery' first
    true                       // Footer: Load at the bottom (default)
);
```

### 2. Adding a Style

```php
// addStyle(handle, path, dependencies)
$assetManager->addStyle(
    'bootstrap',
    '/assets/css/bootstrap.min.css',
    []
);
```

---

## Dependency Resolution (The Magic)

This is the killer feature. You don't need to know *when* jQuery was added or if it was added at all. You just say "I depend on `jquery`".

The Asset Manager uses **Topological Sorting** to figure out the perfect order.

*   You add `app.js` (depends on `bootstrap`)
*   You add `bootstrap.js` (depends on `popper`)
*   You add `popper.js` (no deps)

**Output Order:** `popper.js` -> `bootstrap.js` -> `app.js`.

---

## Integration with Templates

You don't need to write `<script>` tags in your templates anymore. The Asset Manager injects three magic variables into every Twig template.

*   `{{ styles }}`: All CSS link tags.
*   `{{ head_scripts }}`: Critical JS that must be in `<head>`.
*   `{{ scripts }}`: All other JS (typically for the footer).

### Your Base Template

Make sure your `base.html.twig` looks something like this:

```twig
<!DOCTYPE html>
<html>
<head>
    <title>{{ title }}</title>

    <!-- CSS Injection Point -->
    {% if styles %}{{ styles | raw }}{% endif %}

    <!-- Critical JS Injection Point -->
    {% if head_scripts %}{{ head_scripts | raw }}{% endif %}
</head>
<body>
    {% block content %}{% endblock %}

    <!-- Footer JS Injection Point -->
    {% if scripts %}{{ scripts | raw }}{% endif %}
</body>
</html>
```
