---
title: 'Table of Contents'
template: docs
menu: '1.3.5, 2.3.5'
category: docs
---
# Table of Contents

**What it does:** Automatically generates a Table of Contents (TOC) for your pages based on headings.

**Events:** `MARKDOWN_CONVERTED` (priority 500)

**How to use:** The TOC is automatically generated for any Markdown file with `<h2>` or `<h3>` headings.

## How It Works

1.  **Listens for `MARKDOWN_CONVERTED`**: This feature waits until the Markdown Renderer has converted your content to HTML.
2.  **Parses HTML**: It scans the generated HTML for `<h2>` and `<h3>` tags.
3.  **Generates List**: It builds a nested HTML list (`<ul><li>...</li></ul>`) representing the document structure.
4.  **Injects Variable**: The generated HTML is available in your Twig templates as `{{ metadata.toc }}`.

## Usage in Templates

To display the Table of Contents in your template, simply check if it exists and then render it:

```twig
{% if metadata.toc %}
    <div class="toc">
        <div class="toc-title">Contents</div>
        {{ metadata.toc|raw }}
    </div>
{% endif %}
```

## Styling

The generated HTML uses the class `.toc-list` for the main `<ul>`. You can style it in your CSS:

```css
.toc-list {
    list-style: none;
    padding: 0;
}

.toc-list ul {
    padding-left: 1rem;
}
```

## Dependencies

This feature relies on the **Markdown Renderer** adding IDs to headings. The Markdown Renderer uses the `HeadingPermalinkExtension` to ensure every heading has a unique ID (e.g., `<h2 id="introduction">`).
