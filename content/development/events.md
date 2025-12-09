---
menu: '4.1.3'
name: 'Core Events Reference'
template: docs
---
# Events Reference

This document provides a complete reference of all events fired by the StaticForge system, including Core events and events fired by built-in Features.

## Event Pipeline Overview

StaticForge executes events in a specific order during site generation:

```
1. CREATE        - Feature initialization
2. PRE_GLOB      - Before file discovery
3. [File Discovery]
4. POST_GLOB     - After file discovery
5. PRE_LOOP      - Before processing loop
6. [For each file:]
   - PRE_RENDER  - Before rendering file
   - RENDER      - During rendering
     - MARKDOWN_CONVERTED - After markdown conversion (if applicable)
   - POST_RENDER - After rendering file
7. POST_LOOP     - After processing all files
8. DESTROY       - Final cleanup
```

---

## Event Definitions

### CONSOLE_INIT

**Fired**: During application bootstrap, before any command is executed.
**Purpose**: Allow features to register custom CLI commands.
**Data**:
```php
[
  'application' => Symfony\Component\Console\Application // The console application instance
]
```
**Use Cases**:
- Registering new console commands (e.g., `media:inspect`)
- Configuring the console application

**Example**:
```php
public function registerCommands(array $data): array
{
    $app = $data['application'];
    $app->add(new MyCustomCommand());
    return $data;
}
```

**Registered By**: `bin/staticforge.php`
**Execution**: Linear

---

### CREATE

**Fired**: At the start of site generation, after features are registered
**Purpose**: Initialize feature state, validate configuration
**Data**: `[]` (empty array)
**Use Cases**:
- Initialize feature state variables
- Validate feature configuration
- Set up data structures
- Log feature initialization

**Example**:
```php
public function onCreate(array $data): array
{
  $this->processedCount = 0;
  $this->cache = [];
  $this->container->get('logger')->log('INFO', 'MyFeature initialized');
  return $data;
}
```

**Registered By**: Features during `register()` method
**Execution**: Linear, in feature registration order with priority sorting

---

### PRE_GLOB

**Fired**: Before content file discovery begins
**Purpose**: Pre-discovery setup, configuration
**Data**: `[]` (empty array)
**Use Cases**:
- Log discovery start
- Prepare file filters
- Set up discovery configuration
- Initialize file tracking

**Example**:
```php
public function onPreGlob(array $data): array
{
  $this->discoveryStartTime = microtime(true);
  $this->container->get('logger')->log('INFO', 'Starting file discovery');
  return $data;
}
```

**Registered By**: Features during `register()` method
**Execution**: Linear, priority-sorted

---

### POST_GLOB

**Fired**: After content files are discovered, before processing begins
**Purpose**: Review/filter discovered files, prepare for processing
**Data**:
```php
[
  'files' => array  // Array of discovered file paths
]
```

**Use Cases**:
- Filter files (remove drafts, private files)
- Log file count
- Build file index
- Validate file structure

**Example**:
```php
public function onPostGlob(array $data): array
{
  $files = $data['files'] ?? [];
  $this->container->get('logger')->log('INFO', 'Discovered ' . count($files) . ' files');

  // Filter out draft files
  $data['files'] = array_filter($files, function($file) {
    return !str_contains($file, 'draft');
  });

  return $data;
}
```

**Registered By**: Features during `register()` method
**Execution**: Linear, priority-sorted
**Returns**: Modified data array (features can filter the files array)

---

### PRE_LOOP

**Fired**: After POST_GLOB, before file processing loop starts
**Purpose**: Final preparation before individual file processing
**Data**:
```php
[
  'files' => array  // Final array of files to process
]
```

**Use Cases**:
- Initialize processing counters
- Start performance timers
- Pre-load shared resources
- Log processing start

**Example**:
```php
public function onPreLoop(array $data): array
{
  $this->processedCount = 0;
  $this->startTime = microtime(true);
  $this->errors = [];
  return $data;
}
```

**Registered By**: Features during `register()` method
**Execution**: Linear, priority-sorted

---

### PRE_RENDER

**Fired**: Before each individual file is rendered
**Purpose**: Modify file metadata, prepare content, enrich data
**Data**:
```php
[
  'file' => string,        // Source file path (e.g., 'content/blog/post.md')
  'metadata' => array,     // Parsed frontmatter metadata
  'content' => string,     // File content (without frontmatter)
  'outputPath' => string   // Destination path (e.g., 'public/blog/post.html')
]
```

**Use Cases**:
- Add computed metadata (reading time, word count)
- Modify frontmatter data
- Collect data from files (for indexes, sitemaps)
- Validate required metadata
- Pre-process content

**Example**:
```php
public function onPreRender(array $data): array
{
  $metadata = $data['metadata'] ?? [];
  $content = $data['content'] ?? '';

  // Add reading time
  $wordCount = str_word_count(strip_tags($content));
  $metadata['reading_time'] = ceil($wordCount / 200);

  // Add last modified date
  $metadata['modified'] = date('Y-m-d', filemtime($data['file']));

  // Collect for index
  $this->allPosts[] = [
    'title' => $metadata['title'] ?? 'Untitled',
    'url' => $data['outputPath'],
    'date' => $metadata['date'] ?? null
  ];

  return array_merge($data, ['metadata' => $metadata]);
}
```

**Registered By**: Features during `register()` method
**Execution**: Fires once per file, priority-sorted
**Returns**: Modified data array (metadata changes affect rendering)

---

### RENDER

**Fired**: During file rendering, after PRE_RENDER
**Purpose**: Transform content, apply formatting, inject data
**Data**:
```php
[
  'file' => string,        // Source file path
  'metadata' => array,     // Metadata (potentially modified by PRE_RENDER)
  'content' => string,     // Content to render
  'outputPath' => string,  // Destination path
  'template' => string     // Template name (from metadata or default)
]
```

**Use Cases**:
- Transform Markdown to HTML
- Process shortcodes
- Apply syntax highlighting
- Inject dynamic content
- Transform content format

**Example**:
```php
public function onRender(array $data): array
{
  $content = $data['content'] ?? '';

  // Process shortcodes
  $content = preg_replace_callback(
    '/\[youtube\s+id="([^"]+)"\]/',
    function($matches) {
      $id = $matches[1];
      return '<div class="video-embed">
        <iframe src="https://www.youtube.com/embed/' . $id . '"></iframe>
      </div>';
    },
    $content
  );

  $data['content'] = $content;
  return $data;
}
```

**Registered By**: Features during `register()` method
**Execution**: Fires once per file, priority-sorted
**Returns**: Modified data array (content changes affect final output)

---

### POST_RENDER

**Fired**: After file rendering is complete
**Purpose**: Post-process HTML, collect rendered data, cleanup
**Data**:
```php
[
  'file' => string,           // Source file path
  'metadata' => array,        // Final metadata
  'content' => string,        // Rendered HTML content
  'outputPath' => string,     // Destination path
  'html' => string,           // Complete rendered HTML (after template)
  'template' => string        // Template used
]
```

**Use Cases**:
- Minify HTML
- Add analytics tags
- Process final HTML
- Collect statistics
- Update counters

**Example**:
```php
public function onPostRender(array $data): array
{
  $html = $data['html'] ?? '';

  // Add analytics before </body>
  $analytics = '<script>/* analytics code */</script>';
  $html = str_replace('</body>', $analytics . '</body>', $html);

  $this->processedCount++;
  $this->container->get('logger')->log('INFO', 'Rendered: ' . ($data['file'] ?? 'unknown'));

  $data['html'] = $html;
  return $data;
}
```

**Registered By**: Features during `register()` method
**Execution**: Fires once per file, priority-sorted
**Returns**: Modified data array (html changes affect final file output)

---

### POST_LOOP

**Fired**: After all files have been processed
**Purpose**: Generate indexes, create supplementary files, aggregate data
**Data**:
```php
[
  'filesProcessed' => int  // Total number of files processed
]
```

**Use Cases**:
- Generate category/tag indexes
- Create sitemaps
- Build search indexes
- Generate RSS feeds
- Create archive pages
- Write collected data to files

**Example**:
```php
public function onPostLoop(array $data): array
{
  // Generate category index from collected posts
  $outputPath = $this->container->get('OUTPUT_DIR');

  foreach ($this->categorizedPosts as $category => $posts) {
    $html = $this->renderCategoryIndex($category, $posts);
    $categoryFile = $outputPath . '/category/' . $category . '.html';

    if (!is_dir(dirname($categoryFile))) {
      mkdir(dirname($categoryFile), 0755, true);
    }

    file_put_contents($categoryFile, $html);
  }

  $this->container->get('logger')->log('INFO', 'Generated category indexes for ' . count($this->categorizedPosts) . ' categories');

  return $data;
}
```

**Registered By**: Features during `register()` method
**Execution**: Fires once after all files processed, priority-sorted
**Returns**: Modified data array

---

### DESTROY

**Fired**: At the end of site generation, final cleanup
**Purpose**: Release resources, final logging, cleanup
**Data**: `[]` (empty array)

**Use Cases**:
- Close file handles
- Log final statistics
- Clean up temporary files
- Release resources
- Final validation

**Example**:
```php
public function onDestroy(array $data): array
{
  $elapsed = microtime(true) - $this->startTime;

  $this->container->get('logger')->log('INFO', sprintf(
    'MyFeature complete: %d files in %.2fs',
    $this->processedCount,
    $elapsed
  ));

  // Cleanup
  $this->cache = [];
  $this->processedCount = 0;

  return $data;
}
```

**Registered By**: Features during `register()` method
**Execution**: Linear, priority-sorted
**Returns**: Modified data array (typically ignored)

---

## Feature Events

These events are fired by specific built-in features. They are only available if the corresponding feature is enabled.

### COLLECT_MENU_ITEMS

**Fired By**: [Menu Builder](../features/menu-builder.html) feature during `POST_GLOB` processing
**Purpose**: Allow other features to inject items into the menu structure before HTML generation
**Data**:
```php
[
  'menu_data' => array  // The current menu structure array
]
```

**Use Cases**:
- Add dynamic pages (like category indexes) to menus
- Inject external links
- Modify existing menu items

**Example**:
```php
public function onCollectMenuItems(Container $container, array $parameters): array
{
    $menuData = $parameters['menu_data'];
    $menuData[1]['direct'][] = [
        'title' => 'My Dynamic Page',
        'url' => '/dynamic-page/',
        'position' => '1.99'
    ];
    $parameters['menu_data'] = $menuData;
    return $parameters;
}
```
**Registered By**: `MenuBuilder` feature
**Execution**: Linear, priority-sorted

---

### MARKDOWN_CONVERTED

**Fired By**: [Markdown Renderer](../features/markdown-renderer.html) feature during `RENDER` processing
**Purpose**: Allow modification of HTML content after Markdown conversion but before template rendering
**Data**:
```php
[
  'html_content' => string, // The converted HTML
  'metadata' => array,      // File metadata
  'file_path' => string     // Source file path
]
```

**Use Cases**:
- Generate Table of Contents
- Add anchor links to headings
- Process shortcodes in HTML
- Syntax highlighting

**Example**:
```php
public function onMarkdownConverted(Container $container, array $parameters): array
{
    $html = $parameters['html_content'];
    // Add class to all tables
    $html = str_replace('<table>', '<table class="table">', $html);
    $parameters['html_content'] = $html;
    return $parameters;
}
```

**Registered By**: `MarkdownRenderer` feature
**Execution**: Linear, priority-sorted

---

## Event Priority

Events support priority ordering (0-999):
- **Lower numbers execute first** (e.g., 100 runs before 500)
- **Higher numbers execute last** (e.g., 900 runs after 500)
- Default priority if not specified: 500

### Common Priority Conventions

| Range | Purpose | Examples |
|-------|---------|----------|
| 0-99 | Critical early processing | Core initialization |
| 100-199 | Data extraction/parsing | Frontmatter parsing, file reading |
| 200-299 | Early transformation | Content normalization |
| 300-499 | Standard processing | Default feature behavior |
| 500-699 | Enhancement/enrichment | Adding metadata, calculations |
| 700-899 | Finalization | Cleanup, optimization |
| 900-999 | Last-chance processing | Final validation, logging |

**Example**:
```php
public function register(EventManager $eventManager): void
{
  // Parse content early
  $eventManager->on('PRE_RENDER', [$this, 'parse'], 150);

  // Transform at normal priority
  $eventManager->on('RENDER', [$this, 'transform'], 500);

  // Finalize late
  $eventManager->on('POST_RENDER', [$this, 'finalize'], 850);
}
```

---

## Event Data Flow

Features can modify event data and pass changes to subsequent listeners:

```php
// Feature A (priority 100)
public function onPreRender(array $data): array
{
  $metadata = $data['metadata'] ?? [];
  $metadata['processed_by_a'] = true;
  return array_merge($data, ['metadata' => $metadata]);
}

// Feature B (priority 200)
public function onPreRender(array $data): array
{
  $metadata = $data['metadata'] ?? [];
  // Can see changes from Feature A
  if ($metadata['processed_by_a'] ?? false) {
    $metadata['enhanced'] = true;
  }
  return array_merge($data, ['metadata' => $metadata]);
}
```

**Important**: Always return the data array, even if unchanged!

---

## Best Practices

### 1. Always Return Data
```php
// ✅ Correct
public function onEvent(array $data): array
{
  // ... processing
  return $data;
}

// ❌ Wrong
public function onEvent(array $data): array
{
  // ... processing
  // Missing return!
}
```

### 2. Handle Missing Data
```php
public function onPreRender(array $data): array
{
  $metadata = $data['metadata'] ?? [];
  $content = $data['content'] ?? '';

  if (empty($content)) {
    return $data;  // Skip if no content
  }

  // ... process
  return $data;
}
```

### 3. Preserve Existing Data
```php
public function onPreRender(array $data): array
{
  $metadata = $data['metadata'] ?? [];

  // Add new fields, don't replace entire metadata
  $metadata['new_field'] = 'value';

  return array_merge($data, ['metadata' => $metadata]);
}
```

### 4. Log Important Operations
```php
public function onPostLoop(array $data): array
{
  $this->container->get('logger')->log('INFO', "Processed {$this->count} items");
  return $data;
}
```