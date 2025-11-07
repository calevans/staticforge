---
title = "HTML Renderer"
template = "docs"
menu = 1.3.2, 2.3.2
---

# HTML Renderer

**What it does:** Processes `.html` files and wraps them in templates

**File types:** `.html`, `.htm`

**Events:** `RENDER` (priority 100)

**How it works:**

1. Reads frontmatter from `<!-- INI ... -->` comment block
2. Extracts the HTML content
3. Applies your chosen Twig template
4. Outputs the final HTML file

## Example

**Example input file (`content/about.html`):**

```html
<!-- INI
title = "About Us"
description = "Learn about our company"
template = "about-page"
-->

<div class="about-section">
  <h1>About Our Company</h1>
  <p>We build amazing websites with StaticForge!</p>

  <h2>Our Mission</h2>
  <p>To make static site generation accessible to everyone.</p>
</div>
```

## Key Points

- Use `<!-- INI ... -->` for frontmatter (not `---`)
- Write regular HTML for content
- Great for custom layouts or when you need precise HTML control
- Still gets wrapped in your template like Markdown files

## When to Use HTML Instead of Markdown

- Complex layouts requiring specific HTML structure
- Embedding custom JavaScript or CSS
- Pages with forms or interactive elements
- Landing pages with specific design requirements

---

[← Back to Features Overview](FEATURES.html)
