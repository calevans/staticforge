---
title: Search
template: docs
menu: '3.1.6'
tags:
  - search
  - feature
---
# Search

**What it does:** Adds full-text search capability to your static site using MiniSearch.

**Events:** `POST_RENDER` (priority 100), `POST_LOOP` (priority 100)

**How to use:** Enable in `siteconfig.yaml` and include the search assets in your template.

---

## Overview

The Search feature brings powerful, client-side search functionality to your static site without requiring a backend server or database. It works by generating a comprehensive JSON index of your content during the build process and using the lightweight [MiniSearch](https://github.com/lucaong/minisearch) library to perform searches directly in the user's browser.

This approach ensures your site remains fast and completely static while still offering a dynamic search experience.

---

## How It Works

When you build your site, the Search feature performs two main tasks:

1.  **Indexing:** As each page is rendered, the feature collects its title, content, tags, and category. It strips out HTML tags to create a clean text representation of your content.
2.  **Generation:** After all pages are processed, it compiles this data into a `search.json` file and copies the necessary JavaScript libraries (`minisearch.min.js` and `search.js`) to your output directory.

---

## Configuration

You can configure the search behavior globally in your `siteconfig.yaml` file or on a per-page basis using frontmatter.

### Global Configuration

In `siteconfig.yaml`, you can control which paths are excluded from the search index. This is useful for hiding utility pages, tag archives, or other content you don't want appearing in search results.

```yaml
search:
  enabled: true
  exclude_paths:
    - /tags/
    - /categories/
    - /404.html
  exclude_content_in: []
```

*   **exclude_paths:** A list of URL paths to completely exclude from the index.
*   **exclude_content_in:** (Optional) A list of paths where content should be excluded, but the page might still be indexed (depending on implementation details, currently behaves similarly to exclude_paths).

### Per-Page Configuration

You can exclude individual pages from the search index by adding `search_index: false` to the page's frontmatter.

```markdown
---
title: "Hidden Page"
search_index: false
---
```

---

## Implementing Search in Your Template

To add the search bar to your site, you need to include the generated JavaScript assets and add the HTML markup for the search input.

### 1. Add the HTML

Add the following HTML where you want the search bar to appear (e.g., in your header or sidebar):

```html
<div class="search-container">
    <input type="text" id="search-input" placeholder="Search...">
    <div id="search-results"></div>
</div>
```

### 2. Include the Scripts

Add the following script tags to your template, typically before the closing `</body>` tag:

```html
<script src="/assets/js/minisearch.min.js"></script>
<script src="/assets/js/search.js"></script>
```

The `search.js` script automatically initializes MiniSearch, loads the `search.json` index, and handles user input to display results in the `#search-results` container.

---

## Customizing the Search Experience

The default `search.js` provides a basic implementation. You can customize the look and feel by styling the `#search-input` and `#search-results` elements with CSS.

If you need more advanced behavior (like custom result rendering or different search options), you can modify the `search.js` file or create your own script that utilizes the `minisearch.min.js` library and the generated `search.json` index.
