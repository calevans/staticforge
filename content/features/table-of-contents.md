---
title: 'Table of Contents'
template: docs
menu: '3.1.13'
---
# Table of Contents

**What it does:** Automatically generates a Table of Contents (TOC) for your pages based on headings.

**Events:** `MARKDOWN_CONVERTED` (priority 500)

**How to use:** The TOC is automatically generated for any Markdown file with `<h2>` or `<h3>` headings.

## How It Works

1.  **Listens for `MARKDOWN_CONVERTED`**: This feature waits until the Markdown Renderer has converted your content to HTML.
2.  **Parses HTML**: It scans the generated HTML for `<h2>` and `<h3>` tags.
3.  **Generates List**: It builds a nested HTML list (`<ul><li>...</li></ul>`) representing the document structure.
4.  **Injects Variable**: The generated HTML is available in your Twig templates as `{{ toc }}`.

## Usage in Templates

To display the Table of Contents in your template, use the `{{ toc }}` variable.

```twig
<!-- Right Sidebar (Table of Contents) -->
<aside class="toc">
    {% if toc %}
        <div class="toc-title">On This Page</div>
        {{ toc|raw }}
    {% endif %}
</aside>
```

## Styling

The generated HTML uses the class `.toc-list` for the main `<ul>`. Here is the CSS used in the `staticforce` template to style it:

```css
/* Table of Contents */
.toc {
    position: sticky;
    top: 100px;
}

.toc-title {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--gray-500);
    margin-bottom: var(--spacing-md);
}

.toc-list {
    list-style: none;
    padding: 0;
}

.toc-list li {
    margin-bottom: var(--spacing-xs);
}

.toc-list a {
    display: block;
    padding: var(--spacing-sm);
    color: var(--gray-600);
    text-decoration: none;
    font-size: 0.85rem;
    border-left: 2px solid transparent;
    transition: all var(--transition-base);
}

.toc-list a:hover {
    color: var(--primary);
    border-left-color: var(--primary);
    transform: translateX(3px);
}

/* Nested Levels (h3) */
.toc-list ul {
    padding-left: var(--spacing-lg);
    list-style: none;
}

.toc-list ul a {
    font-size: 0.8rem;
}
```

## Dependencies

This feature relies on the **Markdown Renderer** adding IDs to headings. The Markdown Renderer uses the `HeadingPermalinkExtension` to ensure every heading has a unique ID (e.g., `<h2 id="introduction">`).
