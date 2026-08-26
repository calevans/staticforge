---
template: docs
menu: '4.1.4'
title: 'The Plugin System: Features'
description: 'How to build, register, and manage Features (plugins) to extend StaticForge functionality.'
og_image: "Modular plugin system concept, interlocking 3D blocks, lego-like structure, puzzle pieces connecting, colorful geometric shapes, clean white background, --ar 16:9"
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
namespace EICC\StaticForge\Features\MyCoolFeature;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;

class Feature extends BaseFeature
{
    // Put an #[EventListener] attribute directly on the handler method.
    // BaseFeature scans for these and registers them automatically —
    // no separate registration array needed.
    #[EventListener('PRE_RENDER', priority: 500)]
    public function doSomethingCool(RenderEvent $event): void
    {
        // Do the cool thing — mutate $event directly, nothing to return
    }
}
```

---

## Creating a Feature

### The Easy Way (CLI)

Don't write boilerplate. Let the robot do it.

```bash
lando php vendor/bin/staticforge.php feature:create MyNewFeature
```

Boom. You have a new feature structure in `src/Features/MyNewFeature/`. Go fill in the blanks.

### The Manual Way

1.  Create a folder: `src/Features/MyNewFeature`.
2.  Create a `Feature.php` file inside it, namespaced `EICC\StaticForge\Features\MyNewFeature` (matching what `feature:create` itself generates).
3.  Make sure it extends `BaseFeature`.
4.  Make sure your `composer.json` autoloads that namespace to `src/Features/`.

---

## Hooking into Events

Most features never need to write a `register()` method at all — `BaseFeature`'s own `register(EventManager $eventManager)` already scans your class for `#[EventListener]` attributes and wires them up (see [Events](events.html)):

```php
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\RenderEvent;

// Run early (Priority 100)
#[EventListener('POST_GLOB', priority: 100)]
public function scanFiles(Event $event): void
{
    // ...
}

// Run late (Priority 900)
#[EventListener('POST_RENDER', priority: 900)]
public function cleanup(RenderEvent $event): void
{
    // ...
}
```

Only override `register()` yourself if you need to do something *besides* listener registration at load time — building a service that needs a computed value (like a resolved file path), or gating registration behind a feature-flag check. If you do, call `parent::register($eventManager)` first so the attribute scan still runs:

```php
public function register(EventManager $eventManager): void
{
    parent::register($eventManager);

    // Extra one-time setup goes here
}
```

### Common Use Cases

*   **Need to build a list of files?** Listen to `POST_GLOB`.
*   **Need to change content?** Listen to `PRE_RENDER`.
*   **Need to add analytics?** Listen to `POST_RENDER`.
*   **Need to generate a new file (like sitemap.xml)?** Listen to `POST_LOOP`.

---

## Configuration

You don't want to hardcode settings in your PHP files. Instead, put them under your own top-level key in `siteconfig.yaml` — by convention, the snake_case version of your feature's name.

**siteconfig.yaml:**
```yaml
my_cool_feature:
  enabled: true
  show_author: true
  prefix: "Written by: "
```

**In your Feature:** read it straight off the container's `site_config` variable — there's no separate config-loading API. Constructor-inject `Container`; StaticForge's `FeatureFactory` autowires it automatically.

```php
use EICC\Utils\Container;

class Feature extends BaseFeature
{
    private Container $applicationContainer;

    public function __construct(Container $applicationContainer)
    {
        $this->applicationContainer = $applicationContainer;
    }

    #[EventListener('PRE_RENDER', priority: 500)]
    public function doSomethingCool(RenderEvent $event): void
    {
        $siteConfig = $this->applicationContainer->getVariable('site_config') ?? [];
        $config = $siteConfig['my_cool_feature'] ?? [];

        if (!empty($config['show_author'])) {
            $prefix = $config['prefix'] ?? 'By: ';
            // ...
        }
    }
}
```

To let a site owner disable your feature entirely (skip loading it, not just toggle behavior inside it), they add its name to the separate `disabled_features` list:

```yaml
disabled_features:
  - MyCoolFeature
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
3.  **Type Your Event Parameter**: Your listener methods take the real typed event (`Event`, `RenderEvent`, etc.) and mutate it directly — there's no array to remember to return.

---

[← Back to Documentation](index.html)

