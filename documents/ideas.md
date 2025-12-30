# Feature Idea: Estimated Reading Time Plugin

## Overview
A standalone external plugin for StaticForge that calculates the estimated reading time for content files (Markdown/HTML) and exposes this data to Twig templates. This allows theme developers to display "5 min read" on blog posts or documentation pages.

## Technical Specification

### 1. Package Details
- **Type**: External Composer Package
- **Suggested Name**: `calevans/staticforge-reading-time`
- **Namespace**: `EICC\StaticForge\Plugins\ReadingTime`

### 2. Event Hook
- **Event**: `PRE_RENDER`
- **Priority**: `50` (Should run after content is loaded but before final rendering)

### 3. Logic Flow
1.  **Listener**: The feature registers a listener for the `PRE_RENDER` event.
2.  **Filter**: Check if the file is a supported type (e.g., `.md`, `.html`). Skip if it's a binary or excluded path.
3.  **Extraction**: Retrieve the raw content from the `File` object.
4.  **Calculation**:
    - Strip HTML tags (if present).
    - Count words in the text.
    - Formula: `minutes = ceil(word_count / words_per_minute)`
    - Default `words_per_minute` = 200.
5.  **Injection**:
    - Inject the data into the file's metadata (frontmatter) so it's accessible in Twig.
    - **Variables**:
        - `reading_time_minutes`: (int) e.g., `5`
        - `reading_time_label`: (string) e.g., `"5 min read"`

### 4. Configuration (`siteconfig.yaml`)
The plugin should support optional configuration via the `site_config` container variable.

```yaml
reading_time:
  wpm: 200              # Words per minute (default: 200)
  exclude:              # Paths to ignore
    - /contact
    - /search
  label_singular: "min read"
  label_plural: "min read"
```

### 5. Implementation Details
- **Class**: `Feature` implements `FeatureInterface`.
- **Service**: `ReadingTimeCalculator` (Pure PHP class, easy to test).
    - Method: `calculate(string $content, int $wpm): int`
- **Dependencies**: None (uses standard PHP string functions).

### 6. Usage in Templates
```twig
<article>
    <h1>{{ title }}</h1>
    <span class="meta">{{ reading_time_label }}</span>
    {{ content }}
</article>
```

### 7. Development Steps
1.  Create package structure.
2.  Implement `ReadingTimeCalculator` with unit tests.
3.  Implement `Feature` class to hook into `PRE_RENDER`.
4.  Register service in `Container`.
5.  Test integration with a sample StaticForge site.
