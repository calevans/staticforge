---
template: docs
menu: '1.5, 2.5'
category: docs
---
# Feature Development Guide

This guide explains how to create custom features for StaticForge's event-driven architecture.

> **Quick Reference**: See [Core Events Reference](EVENTS.md) for complete documentation of all core events.

---

## Understanding Features

Features in StaticForge are self-contained modules that extend the static site generator's functionality. They work by listening to events in the site generation pipeline and responding to them.

### Core Concepts

1. **Features implement `FeatureInterface`**: All features must implement the `FeatureInterface` which defines:
   - `register(EventManager $eventManager, Container $container)`: Register event listeners
   - Feature identification via `$name` property

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

## Library vs User Features

StaticForge supports two types of features with a priority-based loading system:

### Library Features (Built-in)
- **Location**: `vendor/eicc/staticforge/src/Features/`
- **Namespace**: `EICC\StaticForge\Features\{FeatureName}\Feature`
- **Examples**: MarkdownRenderer, HtmlRenderer, MenuBuilder, Categories, Tags, ChapterNav
- **Always available** when StaticForge is installed
- **Lower priority** - can be overridden by user features

### User Features (Custom)
- **Location**: `src/Features/` (or custom path via `FEATURES_DIR`)
- **Namespace**: **Must use your own namespace** (e.g., `App\Features\{FeatureName}\Feature`)
- **Higher priority** - override library features with the same name
- **Custom functionality** specific to your project

### Feature Loading Order
1. **Library features** are loaded first (from vendor directory)
2. **User features** are loaded second (can override library features)
3. **Conflict resolution** by feature `$name` property, not class name
4. **Override logging** when user features replace library features

---

## Creating Custom Features

### Project Setup

1. **Create features directory**:
   ```bash
   mkdir -p src/Features/MyCustomFeature
   ```

2. **Setup autoloading** in your `composer.json`:
   ```json
   {
     "autoload": {
       "psr-4": {
         "App\\": "src/"
       }
     }
   }
   ```

3. **Update autoloader**:
   ```bash
   composer dump-autoload
   ```

### Basic Feature Structure

Create `src/Features/MyCustomFeature/Feature.php`:

```php
<?php

namespace App\Features\MyCustomFeature;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'MyCustomFeature'; // Unique identifier

    protected array $eventMethods = [
        'RENDER' => ['method' => 'handleRender', 'priority' => 10],
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);

        // Custom initialization logic here
        $this->logger->log('INFO', 'MyCustomFeature registered');
    }

    public function handleRender(array $parameters): void
    {
        // Custom render logic here
        $this->logger->log('INFO', 'MyCustomFeature handling RENDER event');
    }
}
```

### Feature Override System

To override a library feature, create a user feature with the **same `$name` property**:

```php
<?php

namespace App\Features\MarkdownRenderer;

use EICC\StaticForge\Core\BaseRendererFeature;
use EICC\StaticForge\Core\FeatureInterface;

class Feature extends BaseRendererFeature implements FeatureInterface
{
    protected string $name = 'MarkdownRenderer'; // Same name = override

    // Your custom implementation
    public function handleRender(array $parameters): void
    {
        // Custom markdown rendering logic
        // This will replace the library's MarkdownRenderer
    }
}
```

### Feature Dependencies

If your feature relies on another feature being enabled (e.g., a navigation feature that depends on `MenuBuilder`), you can enforce this dependency using the `requireFeatures` method.

```php
public function handleEvent(Container $container, array $parameters): array
{
    // Check if required features are enabled
    if (!$this->requireFeatures(['MenuBuilder'])) {
        // Gracefully exit if dependency is missing
        return $parameters;
    }

    // Proceed with logic that depends on MenuBuilder
    // ...
}
```

If a required feature is disabled, `requireFeatures` will:
1. Log a warning explaining which dependency is missing.
2. Return `false`.

This allows your feature to degrade gracefully rather than crashing the application.

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

> **Note**: For a complete and detailed list of all events, including parameters and examples, please refer to the [Events Reference](EVENTS.md).

The following is a high-level overview of the event pipeline:

1. **CREATE**: Application initialization
2. **PRE_GLOB**: Before file discovery
3. **POST_GLOB**: After file discovery (includes `COLLECT_MENU_ITEMS`)
4. **PRE_LOOP**: Before processing loop
5. **PRE_RENDER**: Before rendering a file
6. **RENDER**: During rendering (includes `MARKDOWN_CONVERTED`)
7. **POST_RENDER**: After rendering a file
8. **POST_LOOP**: After processing all files
9. **DESTROY**: Final cleanup

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
public function onCreate(array $data): array
{
  // Assuming $this->logger was set in register()
  $this->logger->log('INFO', 'YourFeature initialized');
  // OR directly from container
  // $this->container->get('logger')->log('INFO', 'YourFeature initialized');
  return $data;
}

public function onPostLoop(array $data): array
{
  $this->logger->log('INFO', "YourFeature processed {$this->count} items");
  return $data;
}
```

### 6. Use YAML Frontmatter (Parsed in Core)

**Note:** Frontmatter is now parsed automatically by `FileDiscovery` in Core. Features receive metadata via the `discovered_files` container variable. You don't need to parse frontmatter yourself.

**Accessing metadata in your feature:**

```php
public function handlePostGlob(Container $container, array $parameters): array
{
  $discoveredFiles = $container->getVariable('discovered_files') ?? [];

  foreach ($discoveredFiles as $fileData) {
    $filePath = $fileData['path'];
    $url = $fileData['url'];
    $metadata = $fileData['metadata']; // Parsed YAML frontmatter

    // Access specific metadata
    $title = $metadata['title'] ?? 'Untitled';
    $category = $metadata['category'] ?? null;
    $tags = $metadata['tags'] ?? [];

    // Process as needed
  }

  return $parameters;
}
```

**If you must parse frontmatter manually:**

```php
use Symfony\Component\Yaml\Yaml;

private function parseYamlFrontmatter(string $content): array
{
  if (!preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
    return [];
  }

  try {
    $metadata = Yaml::parse($matches[1]);
    return is_array($metadata) ? $metadata : [];
  } catch (\Exception $e) {
    // Handle parse error
    return [];
  }
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
  $this->logger->log('INFO', 'PreRender: ' . ($data['file'] ?? 'unknown'));
  $this->logger->log('DEBUG', 'Metadata: ' . json_encode($data['metadata'] ?? []));

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

