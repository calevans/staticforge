---
menu: '4.1.3'
title: 'The Nervous System: Events'
description: 'Reference for the Event Manager system, available hooks, and the event-driven architecture of StaticForge.'
template: docs
---
# The Nervous System: Events

If the Container is the "Toolbox" of StaticForge, the **Event Manager** is its nervous system. It sends signals throughout the application, telling different parts of the system when to wake up and do their job.

Instead of one giant script that does everything (read files -> parse markdown -> write html), StaticForge is a collection of small, independent features that listen for specific signals.

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
*   **CONSOLE_INIT**: "We are booting up the command line." (Used to add new commands like `site:deploy`)
*   **CREATE**: "The application is alive." (Used to set up initial variables)

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

To make your feature listen for an event, you just need to register it in your Feature class.

```php
class MyFeature extends BaseFeature
{
    // 1. Register the listener
    protected array $eventListeners = [
        'PRE_RENDER' => ['method' => 'addReadingTime', 'priority' => 500]
    ];

    // 2. Define the method
    public function addReadingTime(Container $container, array $data): array
    {
        // Get the content
        $content = $data['content'];

        // Calculate reading time
        $wordCount = str_word_count(strip_tags($content));
        $minutes = ceil($wordCount / 200);

        // Add it to the metadata
        $data['metadata']['reading_time'] = $minutes . ' min read';

        // IMPORTANT: Always return the data!
        return $data;
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
**Why:** You want to add a link to the menu that doesn't exist as a file (e.g., an external link to Twitter).

### MARKDOWN_CONVERTED
**Fired By:** MarkdownRenderer
**When:** During `RENDER`
**Why:** You want to modify the HTML *after* Markdown has done its job but *before* it gets wrapped in a template. (e.g., Adding `class="table"` to all tables).

### RSS_ITEM_BUILDING
**Fired By:** RSSFeed
**When:** During `POST_LOOP`
**Why:** You want to add custom tags to your RSS feed (e.g., Podcast enclosures).

---

## Event Data Flow (The Bucket Brigade)

When an event fires, it passes a `$data` array to the first listener. That listener modifies it and passes it to the next one.

```php
// Listener 1 (Priority 100)
$data['title'] = "Hello";
return $data;

// Listener 2 (Priority 200)
$data['title'] .= " World";
return $data;

// Result: "Hello World"
```

**Critical Rule:** If you break the chain (by not returning `$data`), the next listener gets nothing, and the system crashes. **Always return the data.**

---

## Best Practices

1.  **Always Return Data**: The event chain is like a bucket brigade. If you don't pass the bucket (return `$data`), the fire doesn't get put out (the app crashes).
2.  **Don't Be Greedy**: Only listen to the events you actually need.
3.  **Check for Existence**: Don't assume `metadata['title']` exists. Always check `isset()` or use the null coalescing operator (`??`).

---

[← Back to Documentation](index.html)
