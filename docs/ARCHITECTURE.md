---
title: 'Architecture & Data Flow'
template: docs
menu: '1.1.5, 2.1.5'
category: docs
---
# Architecture & Data Flow

This document explains StaticForge's internal architecture and how data flows through the system.

---

## Core Principles

StaticForge follows these architectural principles:

1. **Single Source of Truth** - Metadata parsed once during discovery
2. **Event-Driven Processing** - Features listen to lifecycle events
3. **Immutable Discovery** - File metadata established early, passed by reference
4. **Lazy Rendering** - Content only rendered when needed

---

## The Discovery Phase

The discovery phase is where StaticForge scans your content directory and prepares all file metadata **before any rendering happens**.

### FileDiscovery Component

Located in `src/Core/FileDiscovery.php`, this component:

1. **Scans directories** - Recursively finds all `.md` and `.html` files
2. **Parses frontmatter** - Extracts INI-style metadata from each file
3. **Generates URLs** - Creates final URLs based on filename and category
4. **Builds discovered_files** - Creates array of file data objects

### discovered_files Structure

After discovery, the system has a `discovered_files` array with this structure:

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

This structure is **stored in the container** and accessible to all features.

---

## Event Lifecycle

StaticForge processes content through a series of events. Features subscribe to these events to add functionality.

### Event Flow

```
CREATE
  ↓
PRE_GLOB (prepare for discovery)
  ↓
POST_GLOB (after discovery, before rendering)
  ├─ MenuBuilder (priority 100)
  ├─ ChapterNav (priority 150)
  ├─ Tags (priority 150)
  ├─ CategoryIndex (priority 200)
  └─ Categories (priority 250) - Applies category templates
  ↓
PRE_RENDER (before each file)
  ↓
RENDER (render file to HTML)
  ├─ MarkdownRenderer
  └─ HtmlRenderer
  ↓
POST_RENDER (after each file)
  └─ Categories - Moves files to category directories
  ↓
POST_LOOP (after all files rendered)
  ├─ RSSFeed (priority 90)
  ├─ RobotsTxt (priority 100)
  └─ TemplateAssets (priority 100)
  ↓
DESTROY (cleanup)
```

### Priority System

Higher priority numbers run **later** in the event. This ensures proper dependency order:

- **Priority 100** - MenuBuilder (runs first, no dependencies)
- **Priority 150** - ChapterNav, Tags (depend on discovery only)
- **Priority 200** - CategoryIndex (may depend on tags)
- **Priority 250** - Categories (runs last, applies templates)

---

## POST_GLOB Event Details

The POST_GLOB event is critical - this is where features process the discovered files **before any rendering**.

### What Happens During POST_GLOB

1. **MenuBuilder** scans for `menu` metadata, builds menu structures
2. **ChapterNav** finds sequential content, builds prev/next links
3. **Tags** collects all tags, builds tag index
4. **CategoryIndex** finds category files, prepares category pages
5. **Categories** applies category templates to file metadata

### Categories Template Application

The Categories feature at priority 250 runs **after** all other POST_GLOB handlers:

```php
// In Categories::handlePostGlob()

// 1. Find category definition files (type="category")
foreach ($discoveredFiles as $fileData) {
    if ($metadata['type'] === 'category') {
        $categoryTemplates[$slug] = $metadata['template'];
    }
}

// 2. Apply category templates to content files
foreach ($discoveredFiles as $fileData) {
    if (isset($metadata['category'])) {
        $categorySlug = slugify($metadata['category']);

        if (isset($categoryTemplates[$categorySlug])) {
            // Modify the metadata
            $metadata['template'] = $categoryTemplates[$categorySlug];
            $fileData['metadata'] = $metadata;
        }
    }
}

// 3. Update discovered_files in container
$container->updateVariable('discovered_files', $updatedFiles);
```

This ensures all files have their final template assigned **before rendering begins**.

---

## Metadata Flow

```
┌─────────────────┐
│  Content File   │
│  (with INI)     │
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│  FileDiscovery  │ ← Parses frontmatter
│  parseIniContent│ ← Generates URL
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│ discovered_files│ ← Stored in Container
│  [{path, url,   │
│    metadata}]   │
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│  POST_GLOB      │ ← Features process metadata
│  - MenuBuilder  │ ← Categories applies templates
│  - Categories   │
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│  RENDER Event   │ ← Renderers use metadata
│  - Markdown →   │ ← Template already assigned
│  - HTML        │
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│  Output HTML    │
└─────────────────┘
```

---

## Feature Architecture

Features are self-contained components that:

1. **Extend BaseFeature** - Get container and event manager access
2. **Register event listeners** - Declare which events they handle
3. **Process data** - Manipulate discovered_files or render output
4. **Expose data** - Store results in `features` array for templates

### Feature Data Exposure

Features can expose data to templates by storing it in the event parameters:

```php
public function handlePostGlob(Container $container, array $parameters): array
{
    // Process data
    $menuData = $this->buildMenus();

    // Expose to templates
    $parameters['features'][$this->getName()] = [
        'files' => $menuData,
        'html' => $renderedHtml
    ];

    return $parameters;
}
```

Templates then access this via:

```twig
{{ features.MenuBuilder.html.1|raw }}
{% for item in features.MenuBuilder.files[1] %}
  {{ item.title }}
{% endfor %}
```

---

## Benefits of This Architecture

### Single Parsing

- Frontmatter parsed **once** during discovery
- No need to re-read files during rendering
- Consistent metadata across all features

### Performance

- Metadata cached in memory (container)
- Features share the same data structure
- No duplicate file I/O

### Extensibility

- New features just subscribe to events
- No need to modify core rendering logic
- Features can depend on each other via priority

### Predictability

- Template assignment happens at fixed point (POST_GLOB)
- URL generation consistent across all features
- Clear data flow from discovery → processing → rendering

---

## Adding New Features

To add a new feature that processes metadata:

1. Extend `BaseFeature`
2. Register for `POST_GLOB` event
3. Access `discovered_files` from container
4. Process the data
5. Optionally expose results via `features` array

Example:

```php
class MyFeature extends BaseFeature
{
    protected array $eventListeners = [
        'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 150]
    ];

    public function handlePostGlob(Container $container, array $parameters): array
    {
        $discoveredFiles = $container->getVariable('discovered_files') ?? [];

        foreach ($discoveredFiles as $fileData) {
            // Access pre-parsed metadata
            $metadata = $fileData['metadata'];
            $url = $fileData['url'];

            // Do something useful
        }

        return $parameters;
    }
}
```

---

[← Back to Documentation](index.html)
