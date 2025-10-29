# Content Examples

This directory contains example content files demonstrating StaticForge features.

## Files

- **blog-post.md** - Standard blog post with tags and category
- **landing-page.html** - HTML landing page with frontmatter
- **documentation-page.md** - Technical documentation example
- **portfolio-item.md** - Portfolio/project showcase
- **simple-page.md** - Minimal example

## Usage

Copy these files to your `content/` directory and customize:

```bash
cp docs/examples/blog-post.md content/blog/my-first-post.md
```

Then regenerate your site:

```bash
lando php bin/console.php render:site
```

## Format Notes

All examples use INI frontmatter format:

```
---
key = "value"
array = [item1, item2, item3]
---
```

For HTML files, use HTML comments:

```html
<!-- INI
key = "value"
-->
```
