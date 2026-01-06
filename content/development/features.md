---
template: docs
menu: '4.1.4'
title: 'The Plugin System: Features'
description: 'How to build, register, and manage Features (plugins) to extend StaticForge functionality.'
---
# The Plugin System: Features

If StaticForge is the operating system, **Features** are the apps.

Almost everything StaticForge does—converting Markdown, building menus, generating RSS feeds—is actually just a Feature. The core system is tiny; it just loads features and fires events.

This means you can change *anything*. Don't like how we handle Markdown? Disable our renderer and write your own. Want to add a "Reading Time" calculator? Just write a feature.

---

## Anatomy of a Feature

A Feature is just a PHP class that extends `BaseFeature`. It has one main job: to tell the system which events it cares about.

### The Basic Structure

```php
namespace App\Features\MyCoolFeature;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\Container;
use EICC\StaticForge\Core\EventManager;

class Feature extends BaseFeature
{
    protected string $name = 'MyCoolFeature';

    public function register(EventManager $eventManager, Container $container): void
    {
        // "Hey system, wake me up when you are about to render a file!"
        $eventManager->on('PRE_RENDER', [$this, 'doSomethingCool']);
    }

    public function doSomethingCool(Container $container, array $data): array
    {
        // Do the cool thing
        return $data;
    }
}
```

---

## Creating a Feature

### The Easy Way (CLI)

We have a command that builds the skeleton for you.

```bash
lando php bin/staticforge.php feature:create MyNewFeature
```

This creates `src/Features/MyNewFeature/Feature.php` ready for you to edit.

### The Manual Way

1.  Create a folder: `src/Features/MyNewFeature`.
2.  Create a `Feature.php` file inside it.
3.  Make sure it extends `BaseFeature`.
4.  Make sure your `composer.json` autoloads it (usually mapped to `App\`).

---

## Hooking into Events

The `register` method is where you subscribe to the "Radio Station" (see [Events](events.html)).

```php
public function register(EventManager $eventManager, Container $container): void
{
    // Run early (Priority 100)
    $eventManager->on('POST_GLOB', [$this, 'scanFiles'], 100);

    // Run late (Priority 900)
    $eventManager->on('POST_RENDER', [$this, 'cleanup'], 900);
}
```

### Common Use Cases

*   **Need to build a list of files?** Listen to `POST_GLOB`.
*   **Need to change content?** Listen to `PRE_RENDER`.
*   **Need to add analytics?** Listen to `POST_RENDER`.
*   **Need to generate a new file (like sitemap.xml)?** Listen to `POST_LOOP`.

---

## Configuration

You don't want to hardcode settings in your PHP files. Instead, put them in `siteconfig.yaml`.

**siteconfig.yaml:**
```yaml
features:
  MyCoolFeature:
    enabled: true
    show_author: true
    prefix: "Written by: "
```

**In your Feature:**
```php
public function doSomethingCool(Container $container, array $data): array
{
    // Get the config
    $config = $this->getConfig();

    if ($config['show_author']) {
        $prefix = $config['prefix'] ?? 'By: ';
        // ...
    }

    return $data;
}
```

---

## Library vs. User Features

StaticForge comes with a set of "Built-in" features (like MarkdownRenderer). These live in the `vendor/` folder.

Your custom features live in `src/Features/`.

### Overriding Core Features

Here is the cool part: **You can replace built-in features.**

If you create a feature with the **exact same name** as a built-in feature (e.g., `MarkdownRenderer`), the system will load yours instead of the built-in one.

This allows you to completely swap out core functionality without hacking the vendor folder.

---

## Best Practices

1.  **Keep it Focused**: A feature should do one thing well. Don't make a "GeneralUtils" feature.
2.  **Use Services**: If your feature has complex logic, move it into a separate Service class (`MyService.php`) and inject it. Don't put 500 lines of code in the `Feature.php` file.
3.  **Always Return Data**: Remember the Bucket Brigade. Your event listeners must return the `$data` array.

---

[← Back to Documentation](index.html)

