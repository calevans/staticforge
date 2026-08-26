---
title: 'The Nervous System: Events'
description: 'Reference for the Event Manager system, available hooks, and the event-driven architecture of StaticForge.'
template: docs
menu: '4.1.3'
og_image: "Neural network firing synapses, abstract visualization of event-driven architecture, glowing nodes connecting, reactive system, vibrant cyan and magenta, --ar 16:9"
---

# The Nervous System: Events

If the Container is the "Toolbox" of StaticForge, the **Event Manager** is its nervous system. It sends signals throughout the application, telling different parts of the system when to wake up and do their job.

Instead of one giant script that does everything (read files -> parse markdown -> write html), StaticForge is a collection of small, independent features that listen for specific signals.

---

## Quick Reference

Already know the system and just need to look something up? Here's every event in one table. Everything below this expands on it with examples.

| Event | Fired When | Event Class | Payload |
| :--- | :--- | :--- | :--- |
| `CREATE` | Application boot, before anything else | `Event` | none |
| `PRE_GLOB` | Just before file discovery scans `content/` | `Event` | none |
| `POST_GLOB` | Right after file discovery, with the full file list known | `Event` | none |
| `PRE_LOOP` | Before the per-file render loop starts | `Event` | none |
| `PRE_RENDER` | Before a single file is converted | `RenderEvent` | `filePath`, `fileUrl`, `metadata`, `extra` |
| `RENDER` | Converting a single file (Markdown → HTML, etc.) | `RenderEvent` | same as above, plus `renderedContent`/`outputPath` as they're set |
| `MARKDOWN_CONVERTED` | Right after Markdown → HTML, before templating | `RenderEvent` | scoped to one file; only `renderedContent`/`metadata` survive back out |
| `POST_RENDER` | After a file's final HTML is generated, before it's written | `RenderEvent` | full set, `renderedContent`/`outputPath` populated |
| `POST_LOOP` | After every file has been processed | `Event` | none |
| `DESTROY` | Application shutdown | `Event` | none |
| `CONSOLE_INIT` | CLI bootstrap, so Features can register commands | `ConsoleInitEvent` | `application` (the Symfony `Application`) |
| `COLLECT_MENU_ITEMS` | During `POST_GLOB`, fired by MenuBuilder | `CollectMenuItemsEvent` | `menuData` (mutable) |
| `ROBOTS_TXT_BUILDING` | Before `robots.txt` is written | `RobotsTxtBuildingEvent` | `rules` (mutable) |
| `RSS_BUILDER_INIT` | Once per category feed, before items are added | `RssBuilderInitEvent` | `builder`, `categoryMetadata` |
| `RSS_ITEM_BUILDING` | Once per item, while building an RSS feed | `RssItemBuildingEvent` | `item` (a `FeedItem`), `file` |
| `SEO_AUDIT_PAGE` | Once per page during `audit:seo` | `SeoAuditPageEvent` | `crawler`, `filename`, `issues` (mutable) |
| `UPLOAD_CHECK_FILE` | Before each file upload during `site:upload` | `UploadCheckFileEvent` | `path`, `localPath`, `targetPath`, hashes, `skipUpload`/`handled` (mutable) |

---

## How It Works (The Radio Station Analogy)

Think of the Event Manager as a radio station.

1.  **The Station (Event Manager)** broadcasts a signal: *"Attention everyone! We are about to start rendering files! (PRE_RENDER)"*
2.  **The Listeners (Features)** are tuned in.
    *   The **Markdown Feature** hears this and thinks, "Not my job yet."
    *   The **Reading Time Feature** hears this and says, "Ooh! That's me! I need to count the words before we render!"

This architecture allows you to add new functionality without ever touching the core code. You just add a new listener.

---

## The Event Lifecycle (The Broadcast Schedule)

Here is the sequence of signals that go out every time you build your site.

### 1. The Setup Phase
*   **CREATE**: "The application is alive." (Used to set up initial variables and feature defaults)

### 2. The Discovery Phase
*   **PRE_GLOB**: "We are about to look for files."
*   **POST_GLOB**: "We found all the files! Here is the list." (Used to build menus, sitemaps, and category lists)

### 3. The Processing Phase (The Loop)
*   **PRE_LOOP**: "Starting the file processing loop."
*   **PRE_RENDER**: "About to render **one specific file**." (Used to modify frontmatter or add computed data)
*   **RENDER**: "Rendering the file now." (Used to convert Markdown to HTML)
*   **POST_RENDER**: "File is rendered." (Used to minify HTML or add analytics tags)

### 4. The Cleanup Phase
*   **POST_LOOP**: "All files are done." (Used to generate RSS feeds or search indexes)
*   **DESTROY**: "Shutting down." (Used to close connections or write logs)

### 5. The Deployment Phase
*   **UPLOAD_CHECK_FILE**: "Checking a specific file before upload."
    *   **Triggered By**: `site:upload` command.
    *   **Purpose**: Allows external tools to control the upload process.
    *   **Event class**: `UploadCheckFileEvent`, with read-only `$path`, `$localPath`, `$targetPath`, `$currentHash`, `$remoteHash`, and `$shouldUpload` describing the file.
    *   **Action** (set these two mutable properties):
        *   Set `$event->handled = true` if you uploaded it yourself (e.g., to S3).
        *   Set `$event->skipUpload = true` to ignore the file entirely.

---

## Deep Dive: Common Events

### POST_GLOB (The Planner)
This is where you see the "Big Picture." You have a list of every file in the system, but nothing has been rendered yet.
*   **Use it for:** Building menus, creating tag clouds, or generating "Next/Previous" links.

### PRE_RENDER (The Editor)
This happens right before a single file is turned into HTML. You have access to its raw content and metadata.
*   **Use it for:** Calculating reading time, fixing typos, or adding default images.

### POST_RENDER (The Polisher)
The HTML is generated but not saved to disk yet.
*   **Use it for:** Minifying CSS/JS, injecting Google Analytics scripts, or adding copyright notices.

---

## Creating Your Own Listener

To make your feature listen for an event, put a `#[EventListener]` attribute on the method itself — no separate registration array needed.

```php
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;

class MyFeature extends BaseFeature
{
    // The event name and priority live right on the handler
    #[EventListener('PRE_RENDER', priority: 500)]
    public function addReadingTime(RenderEvent $event): void
    {
        // At PRE_RENDER the file hasn't been converted to HTML yet, so read
        // the raw source directly off disk
        $content = file_get_contents($event->filePath) ?: '';

        // Calculate reading time
        $wordCount = str_word_count(strip_tags($content));
        $minutes = ceil($wordCount / 200);

        // Add it to the metadata — mutate the event directly, nothing to return
        $event->metadata['reading_time'] = $minutes . ' min read';
    }
}
```

---

## The Priority System (Who Goes First?)

Sometimes multiple features listen to the same event. Who goes first?

We use a **Priority Number** (0-999).
*   **Lower Numbers (0-100)**: Run First. (e.g., "System Critical" stuff)
*   **Higher Numbers (900-999)**: Run Last. (e.g., "Cleanup" stuff)
*   **Default**: 500.

**Example:**
If you want to *modify* the menu before it's used, you need to run **after** the MenuBuilder.
*   MenuBuilder runs at `POST_GLOB` priority **100**.
*   You should run at `POST_GLOB` priority **200**.

---

## Feature-Specific Events

Some features are so polite they even let you interrupt *them*.

### COLLECT_MENU_ITEMS
**Fired By:** MenuBuilder
**When:** During `POST_GLOB`
**Event class:** `CollectMenuItemsEvent`, with one mutable property: `$event->menuData`.
**Why:** You want to add a link to the menu that doesn't exist as a file (e.g., an external link to Twitter).

### MARKDOWN_CONVERTED
**Fired By:** MarkdownRenderer
**When:** During `RENDER`
**Event class:** `RenderEvent` — the same class PRE_RENDER/POST_RENDER use, scoped to just this file's conversion.
**Why:** You want to modify the HTML *after* Markdown has done its job but *before* it gets wrapped in a template. (e.g., Adding `class="table"` to all tables).

### RSS_ITEM_BUILDING
**Fired By:** RSSFeed
**When:** During `POST_LOOP`, once per item in each category feed
**Event class:** `RssItemBuildingEvent`, with a read-only `$event->item` (the `FeedItem` object — mutate its public properties directly, e.g. `$event->item->enclosure = [...]`) and a read-only `$event->file` (the raw discovered-file data).
**Why:** You want to add custom tags to your RSS feed (e.g., Podcast enclosures).

### RSS_BUILDER_INIT
**Fired By:** RSSFeed
**When:** During `POST_LOOP`, once per category feed, before any items are built
**Event class:** `RssBuilderInitEvent`, with a read-only `$event->builder` (the `RssBuilder` — call `$event->builder->addExtension(...)` to add custom XML namespaces) and a mutable `$event->categoryMetadata`.
**Why:** You want to register a feed-level XML extension (e.g., iTunes/Podcast namespaces) before any items are added.

### SEO_AUDIT_PAGE
**Fired By:** `audit:seo` command
**When:** Once per rendered page during the audit
**Event class:** `SeoAuditPageEvent`, with a read-only `$event->crawler` (a Symfony `DomCrawler` over the rendered HTML), a read-only `$event->filename`, and a mutable `$event->issues` array to append your own findings to.
**Why:** You want your feature to contribute its own checks to `audit:seo` (e.g., flagging missing Open Graph tags).

---

## Event Data Flow (Passing the Baton)

When an event fires, it builds **one typed `Event` object** — a plain `Event` for signals with no payload (CREATE, POST_GLOB, POST_LOOP, and the like), a `RenderEvent` for per-file signals (PRE_RENDER, RENDER, POST_RENDER, MARKDOWN_CONVERTED), or a purpose-built subclass like `RssItemBuildingEvent` or `SeoAuditPageEvent` for events that carry richer data — and hands that **same object** to every listener in priority order. Each listener mutates its public properties directly; there's nothing to return.

```php
// Listener 1 (Priority 100)
public function first(RenderEvent $event): void
{
    $event->metadata['title'] = "Hello";
}

// Listener 2 (Priority 200)
public function second(RenderEvent $event): void
{
    $event->metadata['title'] .= " World";
}

// Result: $event->metadata['title'] === "Hello World"
```

Because it's the same object passed by reference through the whole chain, there's no "forgot to return it" failure mode — a listener that does nothing simply leaves the event unchanged for the next one.

---

## Best Practices

1.  **Type your handler's parameter**: Use the real event class (`Event`, `RenderEvent`, `RssItemBuildingEvent`, etc.) — the `#[EventListener]` attribute doesn't enforce it, but a mismatched type will fatal the moment the event actually fires.
2.  **Don't Be Greedy**: Only listen to the events you actually need.
3.  **Check for Existence**: Don't assume `metadata['title']` exists. Always check `isset()` or use the null coalescing operator (`??`).

---

[← Back to Documentation](index.html)
