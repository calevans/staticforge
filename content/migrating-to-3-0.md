---
title: "Migrating to 3.0"
description: 'Step-by-step guide to updating a custom Feature from the pre-3.0 array-based event contract to typed events.'
template: docs
menu: '1.3'
og_image: "Blueprint of a machine being upgraded, mechanical arms swapping old gears for new glowing ones, technical diagram style, --ar 16:9"
---

# Migrating to 3.0

**Who needs this page:** anyone who has written a custom Feature — in `src/Features/`, or published as an external Composer package — against StaticForge 2.x or earlier.

**Who doesn't:** if you only write content and templates, skip this entirely. Nothing here affects `siteconfig.yaml`, frontmatter, or Twig. See [What's New in 3.0](whats-new-3-0.html) for the rest of the release.

Everything below is the exact recipe used to migrate all eight first-party external packages and every in-tree Feature to 3.0 — not theoretical, tested on real code every time.

---

## The TL;DR

If your Feature has this shape:

```php
class Feature extends BaseFeature
{
    protected array $eventListeners = [
        'PRE_RENDER' => ['method' => 'handlePreRender', 'priority' => 100]
    ];

    public function handlePreRender(Container $container, array $parameters): array
    {
        // ...
        return $parameters;
    }
}
```

It needs to look like this:

```php
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;

class Feature extends BaseFeature
{
    #[EventListener('PRE_RENDER', priority: 100)]
    public function handlePreRender(RenderEvent $event): void
    {
        // ...
    }
}
```

Five changes, always in this order:

1.  Delete the `protected array $eventListeners` property.
2.  Put a `#[EventListener('EVENT_NAME', priority: N)]` attribute directly above each handler method.
3.  Change the handler's signature from `(Container $container, array $parameters): array` to `(SomeEvent $event): void` — see the [event type table](#event-types) below for which class to use.
4.  Inside the method, replace every `$parameters['key']` read/write with `$event->property`.
5.  Delete the `return $parameters;` line. There's nothing to return.

(See "Event Types" below for which event class to use for step 3.)

If you didn't override `register()` for anything besides registering listeners, delete `register()` entirely — `BaseFeature`'s own `register(EventManager $eventManager)` already scans for `#[EventListener]` and wires everything up. If you *do* need `register()` for something else (building a service that needs a computed value, gating registration behind a feature flag), keep it, but call `parent::register($eventManager)` first and drop the old `Container $container` second parameter — `register()` takes only `EventManager` now.

---

## Event Types

| Old parameters | New event class | Fired for |
| :--- | :--- | :--- |
| `(Container, array)` with no real payload | `Event` | `CREATE`, `PRE_GLOB`, `POST_GLOB`, `PRE_LOOP`, `POST_LOOP`, `DESTROY` |
| `(Container, array)` with `file_path`/`metadata`/`rendered_content`/etc. | `RenderEvent` | `PRE_RENDER`, `RENDER`, `POST_RENDER`, `MARKDOWN_CONVERTED` |
| `(Application $app)` inside `['application' => $app]` | `ConsoleInitEvent` | `CONSOLE_INIT` |
| `(Container, array)` with `rules` | `RobotsTxtBuildingEvent` | `ROBOTS_TXT_BUILDING` |
| `(Container, array)` with `builder`/category metadata | `RssBuilderInitEvent` | `RSS_BUILDER_INIT` |
| `(Container, array)` with `item`/`file` | `RssItemBuildingEvent` | `RSS_ITEM_BUILDING` |
| `(Container, array)` with `menuData` | `CollectMenuItemsEvent` | `COLLECT_MENU_ITEMS` |
| `(Container, array)` with `crawler`/`filename`/`issues` | `SeoAuditPageEvent` | `SEO_AUDIT_PAGE` |
| `(Container, array)` with local/target path and hashes | `UploadCheckFileEvent` | `UPLOAD_CHECK_FILE` |

Full property lists for each class are in [The Nervous System: Events](development/events.html).

---

## `RenderEvent` Field Mapping

This is the one you'll hit most. Old array key → new property:

| Old | New |
| :--- | :--- |
| `$parameters['file_path']` | `$event->filePath` |
| `$parameters['file_url']` | `$event->fileUrl` |
| `$parameters['metadata']` **and** `$parameters['file_metadata']` | `$event->metadata` (one field now — the old dual-key pattern is gone) |
| `$parameters['rendered_content']` / `$parameters['html_content']` | `$event->renderedContent` (nullable — check before use) |
| `$parameters['output_path']` | `$event->outputPath` (nullable) |
| Anything else feature-specific (a bypass flag, computed data with no first-class field) | `$event->extra['your_key']` |

**One gotcha worth knowing up front:** `MARKDOWN_CONVERTED` fires against its *own* scoped `RenderEvent`, and only `renderedContent` and `metadata` get copied back into the outer render pipeline afterward — `extra` does not survive that boundary. If you need a value to reach `POST_RENDER` from a `MARKDOWN_CONVERTED` listener, put it in `$event->metadata`, not `$event->extra`.

---

## Container Access

Event handlers no longer receive `Container` as a parameter. If your logic needs live container access beyond what the event carries (`site_config`, `OUTPUT_DIR`, `SOURCE_DIR`, etc.), constructor-inject it — `FeatureFactory` autowires it automatically, the same way it resolves any other typed constructor parameter:

```php
use EICC\Utils\Container;

class Feature extends BaseFeature
{
    private Container $applicationContainer;

    public function __construct(Container $applicationContainer, /* ...your services... */)
    {
        $this->applicationContainer = $applicationContainer;
    }

    #[EventListener('POST_LOOP', priority: 100)]
    public function handlePostLoop(Event $event): void
    {
        $outputDir = $this->applicationContainer->getVariable('OUTPUT_DIR');
        // ...
    }
}
```

(Property named `$applicationContainer` rather than `$container` deliberately — `BaseFeature` already declares a `protected Container $container`, populated via `setContainer()` for backward compatibility. Constructor-injecting under a different name avoids relying on load-order between construction and `setContainer()`.)

If your *services* need `Container` too, inject it into them the same way — `FeatureFactory` resolves it recursively through the whole constructor chain.

---

## Building Services in `register()`

If your old `register()` built services manually:

```php
// Old
public function register(EventManager $eventManager, Container $container): void
{
    parent::register($eventManager, $container);
    $logger = $container->get('logger');
    $this->service = new MyService($logger);
}
```

Prefer constructor injection instead — it's simpler and `FeatureFactory` already does this work:

```php
// New
public function __construct(MyService $service)
{
    $this->service = $service;
}
```

This only works if `MyService`'s own constructor takes typed, container-resolvable parameters (`Log`, `Container`, other services). If a service needs a *computed* value that isn't itself a container entry — a resolved file path, a value pulled out of `site_config` — you still build it manually inside `register()`, same as before; just drop the `Container` parameter from `register()`'s own signature and call `parent::register($eventManager)`.

---

## Symfony 8 Command Changes

Unrelated to the event system, but likely to bite at the same time if you register CLI commands: Symfony's console component (now `^8.0`) removed two things Feature packages commonly used.

**`Application::add()` is gone** — use `addCommand()`:

```php
// Old
$event->application->add(new MyCommand());

// New
$event->application->addCommand(new MyCommand());
```

**`protected static $defaultName` is no longer read at all** (it silently produces a "cannot have an empty name" error) — use the `#[AsCommand]` attribute:

```php
// Old
class MyCommand extends Command
{
    protected static $defaultName = 'my:command';
}

// New
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'my:command', description: '...')]
class MyCommand extends Command
{
}
```

---

## A Complete Before/After

A small real Feature, condensed from an actual migration:

**Before:**

```php
class Feature extends BaseFeature
{
    protected Log $logger;

    protected array $eventListeners = [
        'CREATE' => ['method' => 'handleCreate', 'priority' => 10]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);
        $this->logger = $container->get('logger');
    }

    public function handleCreate(Container $container, array $parameters): array
    {
        $buildId = uniqid();
        $container->setVariable('build_id', $buildId);
        $this->logger->log('INFO', "Build ID set: {$buildId}");
        return $parameters;
    }
}
```

**After:**

```php
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\Utils\Container;
use EICC\Utils\Log;

class Feature extends BaseFeature
{
    private Container $applicationContainer;
    protected Log $logger;

    public function __construct(Container $applicationContainer, Log $logger)
    {
        $this->applicationContainer = $applicationContainer;
        $this->logger = $logger;
    }

    #[EventListener('CREATE', priority: 10)]
    public function handleCreate(Event $event): void
    {
        $buildId = uniqid();
        $this->applicationContainer->setVariable('build_id', $buildId);
        $this->logger->log('INFO', "Build ID set: {$buildId}");
    }
}
```

No `register()` override at all — `BaseFeature`'s default handles it.

---

## Verifying the Migration

1.  Run your Feature's tests. If it has none, write some — `UnitTestCase` + `FeatureFactory` is the pattern; see [Testing Your Code](development/testing.html).
2.  Run a real `site:render` with your Feature enabled and diff the output against a pre-migration build. It should be byte-identical (aside from anything you deliberately changed).
3.  If your Feature is an external Composer package, bump its own `require.eicc/staticforge` constraint to `^3.0` and its own version to `3.0.0` before releasing, so anyone still on 2.x doesn't accidentally pull a copy that will fatal against their older core.
