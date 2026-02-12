---
title: Estimated Reading Time
description: 'Automatically calculate and display reading time for your content.'
template: docs
---
# Estimated Reading Time

**What it does:** Calculates the estimated reading time for content files (Markdown/HTML) and exposes this data to Twig templates.

**Events:** `PRE_RENDER` (priority 50)

**How to use:** Enable the feature and use the `{{ reading_time_label }}` variable in your templates.

---

## Configuration

StaticForge enables this feature by default. You can configure it in your `siteconfig.yaml` file to adjust the words-per-minute (WPM) or the label text.

```yaml
reading_time:
  wpm: 200              # Words per minute (default: 200)
  exclude:              # Paths to ignore
    - /contact
    - /search
  label_singular: "min read"
  label_plural: "min read"
```

## Template variables

The feature automatically injects the following variables into your file's metadata, available in your Twig templates:

*   `reading_time_minutes` (int): The raw number of minutes (e.g., `5`).
*   `reading_time_label` (string): The formatted label (e.g., `"5 min read"`).

### Example usage

```twig
<article>
    <header>
        <h1>{{ title }}</h1>
        <div class="meta">
            <span>By {{ author }}</span>
            <span>&bull;</span>
            <span>{{ reading_time_label }}</span>
        </div>
    </header>

    <div class="content">
        {{ content }}
    </div>
</article>
```

## How calculation works

1.  **Strips HTML**: All HTML tags are removed from the content to count only the actual text.
2.  **Counts Words**: Uses a standard word count algorithm.
3.  **Calculates Time**: Divides total words by the configured `wpm` (default 200).
4.  **Rounds Up**: Always rounds up the nearest minute (e.g., 20 seconds is "1 min read").
