---
title: 'Architecture & Data Flow'
description: 'Deep dive into StaticForge internal architecture, data flow pipeline, and core design principles.'
template: docs
menu: '4.1.1'
---
# Under the Hood: Architecture & Data Flow

Welcome to the engine room. If you're looking to understand how StaticForge actually *works*—how it takes a pile of Markdown files and turns them into a polished website—you're in the right place.

This document breaks down the internal architecture, the data flow, and the "why" behind our design decisions.

---

## Core Philosophy

We built StaticForge on four simple architectural pillars. These aren't just rules; they're the reason the system is fast and predictable.

1.  **Single Source of Truth**: We parse your file metadata once, and only once. We don't re-read files a dozen times.
2.  **Event-Driven Processing**: Everything is an event. Features sit back and wait for the right moment to jump in and do their job.
3.  **Immutable Discovery**: We figure out what files exist and what their URLs are *before* we start rendering. This prevents broken links and circular dependencies.
4.  **Lazy Rendering**: We don't do the heavy lifting of converting Markdown to HTML until we absolutely have to.

---

## Phase 1: The Discovery Phase (Getting the Lay of the Land)

Before we render a single pixel, StaticForge needs to know what it's working with. We call this the **Discovery Phase**.

Located in `src/Core/FileDiscovery.php`, this component acts like a surveyor:

1.  **Scans**: It recursively walks through your `content/` directory finding every `.md` and `.html` file.
2.  **Parses**: It reads the Frontmatter (that block at the top of your files) to extract metadata like titles, categories, and tags.
3.  **Maps**: It calculates the final URL for every file based on its filename and category.

### The Result: `discovered_files`

Once the surveyor is done, we have a master map called `discovered_files`. It looks something like this:

```php
[
    [
        'path' => 'content/tutorials/intro.md',
        'url' => '/tutorials/intro.html',
        'metadata' => [
            'title' => 'Introduction to PHP',
            'category' => 'tutorials',
            'menu' => '1.1',
            'tags' => 'php,beginner',
            // ... all other frontmatter fields
        ]
    ],
    // ... more files
]
```

This map is stored in the **Container**, meaning every feature in the system can look at it to see the "big picture" of your site.

---

## Phase 2: The Event Lifecycle (The Assembly Line)

Think of StaticForge as an assembly line. Your content moves down the line, and different stations (Features) add things to it, polish it, or move it around.

Here is the sequence of events that happens every time you run `site:render`:

### The Flow

```
CREATE
  ↓
PRE_GLOB (prepare for discovery)
  ↓
POST_GLOB (The Planning Stage)
  ├─ MenuBuilder (priority 100)
  ├─ ChapterNav (priority 150)
  ├─ Tags (priority 150)
  ├─ CategoryIndex (priority 200)
  └─ Categories (priority 250) - Applies category templates
  ↓
PRE_RENDER (before each file)
  ↓
RENDER (The Heavy Lifting)
  ├─ MarkdownRenderer
  └─ HtmlRenderer
  ↓
POST_RENDER (after each file)
  └─ Categories - Moves files to category directories
  ↓
POST_LOOP (The Wrap Up)
  ├─ RSSFeed (priority 90)
  ├─ RobotsTxt (priority 100)
  └─ TemplateAssets (priority 100)
  ↓
DESTROY (cleanup)
```

### The Priority System

You'll notice "Priority" numbers above. These determine who goes first. Higher numbers run **later**.

*   **Priority 100**: Runs early (e.g., MenuBuilder).
*   **Priority 250**: Runs late (e.g., Categories applying templates).

This ensures that when the Categories feature runs, it already knows about the Menus and Tags.

---

## Deep Dive: The Planning Stage (POST_GLOB)

The `POST_GLOB` event is the most critical part of the process. This is where the "magic" happens before we write a single HTML file.

At this stage, we have a list of all your files, but we haven't processed them yet. This is the perfect time for features to:

1.  **Analyze the whole site**: Look at all the files to build menus, tag lists, or category indexes.
2.  **Modify Metadata**: Change the title, layout, or output path of a file based on rules.

### Example: How Categories Work

The Categories feature is a great example of this. It listens to `POST_GLOB` with a high priority (250) so it runs *after* most other things.

1.  It looks at every file in the list.
2.  If a file has `category: blog`, it changes the file's `layout` to `blog-post`.
3.  It changes the file's `outputPath` to include the category folder (e.g., `/blog/my-post.html`).

This is why `POST_GLOB` is so powerful. It lets you change the destiny of a file before it's even rendered.

---

## Metadata Flow (The Data Journey)

Every file in your `content/` directory starts with some basic data (frontmatter) and picks up more as it travels through the system.

1.  **Frontmatter**: The data you write at the top of your Markdown file.
    ```yaml
    title: My Post
    layout: default
    ```
2.  **Discovery**: StaticForge adds system data like `sourcePath` and `filename`.
3.  **Features**: Features add their own data.
    *   `MenuBuilder` adds `menu_structure`.
    *   `Tags` adds `tag_list`.
4.  **Rendering**: When the template engine (Twig) runs, it gets a merged array of **all** this data.

So, inside your template, you have access to everything: what you wrote, what the system found, and what the features calculated.

---

## Building Your Own Features

Features are the plugins of StaticForge. They are self-contained classes that hook into the system to do cool stuff.

To build a feature, you just need to:

1.  **Extend `BaseFeature`**: This gives you access to the Container and the Event Manager.
2.  **Listen for Events**: Tell the system "Hey, wake me up when `POST_GLOB` happens."
3.  **Do Your Thing**: Write your logic to modify files or add data.

### A Simple Example

Here is a feature that runs during the planning stage (`POST_GLOB`) to look at files.

```php
class MyFeature extends BaseFeature
{
    // Tell the system we want to run during the Planning Stage
    protected array $eventListeners = [
        'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 150]
    ];

    public function handlePostGlob(Container $container, array $parameters): array
    {
        // Get the list of all files
        $discoveredFiles = $container->getVariable('discovered_files') ?? [];

        foreach ($discoveredFiles as $fileData) {
            // Look at the metadata
            $metadata = $fileData['metadata'];

            // Do something useful!
        }

        return $parameters;
    }
}
```

### Sharing Data with Templates

If your feature calculates something useful (like a list of related posts), you can pass it to your templates.

```php
// Inside your handlePostGlob method...
$parameters['features']['MyFeature'] = [
    'related_posts' => $relatedPosts
];
```

Then in your Twig template:

```twig
{% for post in features.MyFeature.related_posts %}
    <a href="{{ post.url }}">{{ post.title }}</a>
{% endfor %}
```

---

## Why We Built It This Way

We designed StaticForge with a few key goals:

1.  **Single Parsing**: We only read your files once. This makes the system fast.
2.  **Memory Efficient**: We keep the metadata in memory so we don't have to read the disk over and over.
3.  **Predictable**: Because of the priority system, you always know what order things will happen in.
4.  **Extensible**: You can add new features without touching the core code. Just listen for an event and go!

---

[← Back to Documentation](index.html)
