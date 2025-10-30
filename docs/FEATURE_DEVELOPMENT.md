---
template = "docs"
menu = 1.4
---

# Feature Development Guide

This guide explains how to create custom features for StaticForge's event-driven architecture.

> **Quick Reference**: See [Core Events Reference](EVENTS.md) for complete documentation of all core events.

## Table of Contents
- [Understanding Features](#understanding-features)
- [Event System Overview](#event-system-overview)
- [Creating a Feature](#creating-a-feature)
- [Event Hooks Reference](#event-hooks-reference)
- [Best Practices](#best-practices)
- [Examples](#examples)

---

## Understanding Features

Features in StaticForge are self-contained modules that extend the static site generator's functionality. They work by listening to events in the site generation pipeline and responding to them.

### Core Concepts

1. **Features implement `FeatureInterface`**: All features must implement the `FeatureInterface` which defines:
   - `register(EventManager $eventManager)`: Register event listeners
   - `getName()`: Return unique feature identifier

2. **Features extend `BaseFeature`**: The `BaseFeature` abstract class provides:
   - Event manager access
   - Container access for dependency injection
   - Common utility methods

3. **Features are event-driven**: Features respond to events fired during site generation:
   - Content discovery events
   - Processing events
   - Rendering events
   - Completion events

---

## Event System Overview

StaticForge uses a priority-based event system where:

- Events are fired at specific points in the generation pipeline
- Multiple features can listen to the same event
- Priority determines execution order (0-999, higher = later)
- Event data can be modified and passed between listeners

### Event Flow

```
Application Start
  ↓
CREATE (initialize features)
  ↓
PRE_GLOB (before file discovery)
  ↓
POST_GLOB (after file discovery, before processing)
  ↓
PRE_LOOP (before processing loop starts)
  ↓
For each file:
  ↓
  PRE_RENDER (before rendering)
  ↓
  RENDER (during rendering)
  ↓
  POST_RENDER (after rendering)
  ↓
POST_LOOP (after all files processed)
  ↓
DESTROY (cleanup)
```

---

## Creating a Feature

### Step 1: Create Feature Directory

```bash
mkdir -p src/Features/YourFeature
```

### Step 2: Create Feature Class

```php
<?php

namespace StaticForge\Features\YourFeature;

use StaticForge\Core\BaseFeature;
use StaticForge\Core\EventManager;

class Feature extends BaseFeature
{
  /**
   * Register event listeners
   */
  public function register(EventManager $eventManager): void
  {
    // Register listeners with priority (0-999)
    $eventManager->on('PRE_RENDER', [$this, 'onPreRender'], 500);
    $eventManager->on('POST_RENDER', [$this, 'onPostRender'], 500);
  }

  /**
   * Get feature name
   */
  public function getName(): string
  {
    return 'your-feature';
  }

  /**
   * Handle PRE_RENDER event
   */
  public function onPreRender(array $data): array
  {
    // Access file metadata
    $file = $data['file'];
    $metadata = $data['metadata'] ?? [];

    // Process and modify data
    $metadata['custom_field'] = $this->processFile($file);

    // Return modified data
    return array_merge($data, ['metadata' => $metadata]);
  }

  /**
   * Handle POST_RENDER event
   */
  public function onPostRender(array $data): array
  {
    // Access rendered content
    $content = $data['content'];

    // Post-process content if needed
    $data['content'] = $this->enhanceContent($content);

    return $data;
  }

  /**
   * Custom processing method
   */
  private function processFile(string $file): string
  {
    // Your custom logic
    return 'processed';
  }

  /**
   * Custom enhancement method
   */
  private function enhanceContent(string $content): string
  {
    // Your custom logic
    return $content;
  }
}
```

### Step 3: Register in FeatureManager

Features are auto-discovered from `src/Features/*/Feature.php`, so no manual registration needed.

---

## Event Hooks Reference

### CREATE
**When**: Application initialization, before any processing
**Purpose**: Initialize feature state, set up resources
**Data**: `[]` (empty array)
**Returns**: `[]` (ignored)

**Example**:
```php
public function onCreate(array $data): array
{
  $this->cache = [];
  $this->logger = $this->container->get('logger');
  return $data;
}
```

---

### PRE_GLOB
**When**: Before file discovery
**Purpose**: Prepare for file discovery, set up filters
**Data**: `['pattern' => string]`
**Returns**: Modified data array

**Example**:
```php
public function onPreGlob(array $data): array
{
  // Could modify the glob pattern if needed
  $this->log('Starting file discovery');
  return $data;
}
```

---

### POST_GLOB
**When**: After file discovery, before processing
**Purpose**: Review discovered files, prepare processing
**Data**: `['files' => array]`
**Returns**: Modified data array (can filter files)

**Example**:
```php
public function onPostGlob(array $data): array
{
  $files = $data['files'] ?? [];
  $this->log('Found ' . count($files) . ' files');

  // Could filter files
  $data['files'] = array_filter($files, function($file) {
    return !str_contains($file, 'draft');
  });

  return $data;
}
```

---

### PRE_LOOP
**When**: Before processing loop starts
**Purpose**: Final preparation before file processing
**Data**: `['files' => array]`
**Returns**: Modified data array

**Example**:
```php
public function onPreLoop(array $data): array
{
  $this->processedCount = 0;
  $this->startTime = microtime(true);
  return $data;
}
```

---

### PRE_RENDER
**When**: Before each file is rendered
**Purpose**: Modify file metadata, prepare for rendering
**Data**:
```php
[
  'file' => string,           // Source file path
  'metadata' => array,        // Parsed frontmatter
  'content' => string,        // File content (without frontmatter)
  'outputPath' => string      // Destination path
]
```
**Returns**: Modified data array

**Example**:
```php
public function onPreRender(array $data): array
{
  $metadata = $data['metadata'] ?? [];

  // Add reading time
  $content = $data['content'];
  $wordCount = str_word_count(strip_tags($content));
  $metadata['reading_time'] = ceil($wordCount / 200);

  // Add last modified date
  $metadata['modified'] = date('Y-m-d', filemtime($data['file']));

  return array_merge($data, ['metadata' => $metadata]);
}
```

---

### RENDER
**When**: During file rendering
**Purpose**: Transform content, inject data
**Data**:
```php
[
  'file' => string,
  'metadata' => array,
  'content' => string,        // Content to render
  'outputPath' => string,
  'template' => string        // Template name
]
```
**Returns**: Modified data array

**Example**:
```php
public function onRender(array $data): array
{
  $content = $data['content'];

  // Process shortcodes
  $content = preg_replace_callback(
    '/\[youtube id="([^"]+)"\]/',
    function($matches) {
      return $this->renderYoutubeEmbed($matches[1]);
    },
    $content
  );

  $data['content'] = $content;
  return $data;
}
```

---

### POST_RENDER
**When**: After file is rendered
**Purpose**: Post-process output, collect data for indexes
**Data**:
```php
[
  'file' => string,
  'metadata' => array,
  'content' => string,        // Rendered HTML
  'outputPath' => string
]
```
**Returns**: Modified data array

**Example**:
```php
public function onPostRender(array $data): array
{
  // Collect for search index
  $this->searchIndex[] = [
    'url' => $data['outputPath'],
    'title' => $data['metadata']['title'] ?? '',
    'content' => strip_tags($data['content'])
  ];

  return $data;
}
```

---

### POST_LOOP
**When**: After all files processed
**Purpose**: Generate indexes, create aggregate pages
**Data**: `[]`
**Returns**: `[]` (ignored)

**Example**:
```php
public function onPostLoop(array $data): array
{
  // Generate search index file
  $indexPath = $this->container->get('outputPath') . '/search.json';
  file_put_contents($indexPath, json_encode($this->searchIndex));

  $this->log('Created search index with ' . count($this->searchIndex) . ' entries');

  return $data;
}
```

---

### DESTROY
**When**: Application shutdown
**Purpose**: Cleanup, final logging
**Data**: `[]`
**Returns**: `[]` (ignored)

**Example**:
```php
public function onDestroy(array $data): array
{
  $elapsed = microtime(true) - $this->startTime;
  $this->log("Processed {$this->processedCount} items in {$elapsed}s");

  // Cleanup resources
  $this->cache = null;

  return $data;
}
```

---

## Best Practices

### 1. Use Appropriate Priority

```php
// Early processing (lower priority number)
$eventManager->on('PRE_RENDER', [$this, 'extract'], 100);

// Normal processing
$eventManager->on('PRE_RENDER', [$this, 'process'], 500);

// Late processing (higher priority number)
$eventManager->on('PRE_RENDER', [$this, 'finalize'], 900);
```

**Common Priorities:**
- `100-200`: Data extraction and parsing
- `300-400`: Data transformation
- `500-600`: Default processing
- `700-800`: Enhancement and enrichment
- `900-999`: Finalization and cleanup

### 2. Return Modified Data

Always return the data array, even if unchanged:

```php
public function onEvent(array $data): array
{
  // Process data

  return $data;  // Always return
}
```

### 3. Use Container for Dependencies

```php
public function register(EventManager $eventManager): void
{
  // Get dependencies from container
  $this->logger = $this->container->get('logger');
  $this->config = $this->container->get('config');

  $eventManager->on('CREATE', [$this, 'onCreate']);
}
```

### 4. Handle Missing Data Gracefully

```php
public function onPreRender(array $data): array
{
  $metadata = $data['metadata'] ?? [];
  $content = $data['content'] ?? '';

  if (empty($content)) {
    return $data;  // Skip processing
  }

  // Process...

  return $data;
}
```

### 5. Log Important Operations

```php
use EiccUtils;

public function onCreate(array $data): array
{
  EiccUtils::log('YourFeature initialized');
  return $data;
}

public function onPostLoop(array $data): array
{
  EiccUtils::log("YourFeature processed {$this->count} items");
  return $data;
}
```

### 6. Use INI Format for Frontmatter

```php
private function parseIniFrontmatter(string $content): array
{
  if (!preg_match('/^---\n(.*?)\n---\n/s', $content, $matches)) {
    return [];
  }

  $frontmatter = $matches[1];
  $metadata = [];

  foreach (explode("\n", $frontmatter) as $line) {
    if (strpos($line, '=') !== false) {
      list($key, $value) = explode('=', $line, 2);
      $key = trim($key);
      $value = trim($value);

      // Handle arrays: key = [item1, item2]
      if (preg_match('/^\[(.*)\]$/', $value, $arrayMatch)) {
        $items = array_map('trim', explode(',', $arrayMatch[1]));
        $metadata[$key] = $items;
      } else {
        // Remove quotes
        $metadata[$key] = trim($value, '"\'');
      }
    }
  }

  return $metadata;
}
```

### 7. Test Your Feature

Create unit tests in `tests/Unit/Features/YourFeature/`:

```php
<?php

namespace Tests\Unit\Features\YourFeature;

use PHPUnit\Framework\TestCase;
use StaticForge\Features\YourFeature\Feature;

class FeatureTest extends TestCase
{
  public function testFeatureName(): void
  {
    $feature = new Feature();
    $this->assertEquals('your-feature', $feature->getName());
  }

  public function testPreRender(): void
  {
    $feature = new Feature();
    $data = [
      'file' => 'test.md',
      'content' => 'test content',
      'metadata' => []
    ];

    $result = $feature->onPreRender($data);

    $this->assertArrayHasKey('metadata', $result);
    // Add assertions for your feature's behavior
  }
}
```

---

## Examples

### Example 1: Reading Time Calculator

```php
<?php

namespace StaticForge\Features\ReadingTime;

use StaticForge\Core\BaseFeature;
use StaticForge\Core\EventManager;

class Feature extends BaseFeature
{
  public function register(EventManager $eventManager): void
  {
    $eventManager->on('PRE_RENDER', [$this, 'addReadingTime'], 500);
  }

  public function getName(): string
  {
    return 'reading-time';
  }

  public function addReadingTime(array $data): array
  {
    $content = $data['content'] ?? '';
    $metadata = $data['metadata'] ?? [];

    // Calculate reading time (200 words per minute)
    $wordCount = str_word_count(strip_tags($content));
    $minutes = ceil($wordCount / 200);

    $metadata['reading_time'] = $minutes;
    $metadata['word_count'] = $wordCount;

    return array_merge($data, ['metadata' => $metadata]);
  }
}
```

### Example 2: Table of Contents Generator

```php
<?php

namespace StaticForge\Features\TableOfContents;

use StaticForge\Core\BaseFeature;
use StaticForge\Core\EventManager;

class Feature extends BaseFeature
{
  public function register(EventManager $eventManager): void
  {
    $eventManager->on('RENDER', [$this, 'generateToc'], 500);
  }

  public function getName(): string
  {
    return 'table-of-contents';
  }

  public function generateToc(array $data): array
  {
    $content = $data['content'] ?? '';
    $metadata = $data['metadata'] ?? [];

    // Only generate TOC if requested
    if (empty($metadata['toc'])) {
      return $data;
    }

    // Extract headings
    preg_match_all('/<h([2-3])>(.*?)<\/h\1>/', $content, $matches, PREG_SET_ORDER);

    if (empty($matches)) {
      return $data;
    }

    // Build TOC HTML
    $toc = '<nav class="toc"><ul>';
    foreach ($matches as $match) {
      $level = $match[1];
      $text = strip_tags($match[2]);
      $id = $this->slugify($text);

      // Add ID to heading in content
      $content = str_replace($match[0],
        "<h{$level} id=\"{$id}\">{$match[2]}</h{$level}>",
        $content
      );

      $toc .= "<li class=\"toc-h{$level}\"><a href=\"#{$id}\">{$text}</a></li>";
    }
    $toc .= '</ul></nav>';

    // Add TOC to metadata for template access
    $metadata['toc_html'] = $toc;

    return array_merge($data, [
      'content' => $content,
      'metadata' => $metadata
    ]);
  }

  private function slugify(string $text): string
  {
    return strtolower(preg_replace('/[^a-z0-9]+/', '-', $text));
  }
}
```

### Example 3: Related Posts

```php
<?php

namespace StaticForge\Features\RelatedPosts;

use StaticForge\Core\BaseFeature;
use StaticForge\Core\EventManager;

class Feature extends BaseFeature
{
  private array $allPosts = [];

  public function register(EventManager $eventManager): void
  {
    $eventManager->on('POST_RENDER', [$this, 'collectPost'], 500);
    $eventManager->on('POST_LOOP', [$this, 'findRelated'], 500);
  }

  public function getName(): string
  {
    return 'related-posts';
  }

  public function collectPost(array $data): array
  {
    $metadata = $data['metadata'] ?? [];
    $tags = $metadata['tags'] ?? [];

    if (!empty($tags)) {
      $this->allPosts[] = [
        'url' => $data['outputPath'],
        'title' => $metadata['title'] ?? '',
        'tags' => is_array($tags) ? $tags : explode(',', $tags)
      ];
    }

    return $data;
  }

  public function findRelated(array $data): array
  {
    foreach ($this->allPosts as $i => $post) {
      $related = [];

      foreach ($this->allPosts as $j => $candidate) {
        if ($i === $j) continue;

        // Find common tags
        $common = array_intersect($post['tags'], $candidate['tags']);

        if (!empty($common)) {
          $related[] = [
            'url' => $candidate['url'],
            'title' => $candidate['title'],
            'score' => count($common)
          ];
        }
      }

      // Sort by score and take top 5
      usort($related, fn($a, $b) => $b['score'] - $a['score']);
      $this->allPosts[$i]['related'] = array_slice($related, 0, 5);
    }

    // Save related posts data
    $outputPath = $this->container->get('outputPath');
    file_put_contents(
      $outputPath . '/related.json',
      json_encode($this->allPosts)
    );

    return $data;
  }
}
```

---

## Debugging Features

### Enable Verbose Logging

```bash
lando php bin/console.php render:site -v
```

### Add Debug Logging

```php
use EiccUtils;

public function onPreRender(array $data): array
{
  EiccUtils::log('PreRender: ' . ($data['file'] ?? 'unknown'));
  EiccUtils::log('Metadata: ' . json_encode($data['metadata'] ?? []));

  // Your processing

  return $data;
}
```

### Check Event Order

```php
public function register(EventManager $eventManager): void
{
  $eventManager->on('PRE_RENDER', function($data) {
    error_log('YourFeature: PRE_RENDER triggered');
    return $data;
  }, 500);
}
```

---

## Common Patterns

### Pattern 1: Collect-Process-Output

1. **PRE_RENDER/POST_RENDER**: Collect data from each file
2. **POST_LOOP**: Process collected data
3. **POST_LOOP**: Generate output files

Used by: Categories, Tags, Related Posts

### Pattern 2: Enrich-and-Pass

1. **PRE_RENDER**: Add metadata
2. **RENDER**: Template uses enriched metadata

Used by: Reading Time, Last Modified

### Pattern 3: Transform Content

1. **RENDER**: Parse and transform content
2. **POST_RENDER**: Post-process HTML

Used by: Markdown Renderer, Shortcodes

---

## Next Steps
- [QuickStart Guide](QUICK_START_GUIDE.html)
- [Configuration Guide](CONFIGURATION.html)
- [Template Development](TEMPLATE_DEVELOPMENT.html)
- Feature Development
- [Core Events](EVENTS.html)
- [Additional Commands](ADDITIONAL_COMMANDS.html)
