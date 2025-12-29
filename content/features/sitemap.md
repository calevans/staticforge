---
title: 'Sitemap Generator'
template: docs
menu: '3.1.12'
---
# Sitemap Generator

**What it does:** Automatically generates a `sitemap.xml` file for search engines.

**File types:** Generates `sitemap.xml`

**Events:**
- `POST_RENDER` (priority 100): Collects URLs
- `POST_LOOP` (priority 100): Generates XML file

**How it works:**

1. Listens as each page is rendered
2. Collects the URL and last modification date
3. Generates a standard XML sitemap at the end of the build process
4. Saves the file to `output/sitemap.xml`

## Configuration

The Sitemap Generator uses the `SITE_URL` (or `SITE_BASE_URL`) from your `.env` file to generate absolute URLs.

```bash
# .env
SITE_URL="https://example.com"
```

## Output Example

The generated `sitemap.xml` looks like this:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://example.com/index.html</loc>
    <lastmod>2023-11-25</lastmod>
  </url>
  <url>
    <loc>https://example.com/about.html</loc>
    <lastmod>2023-11-24</lastmod>
  </url>
</urlset>
```

## Customizing Last Modified Date

By default, the generator uses the file's modification time. You can override this by adding a `date` field to your content's frontmatter:

```markdown
---
title: "My Page"
date: "2023-12-01"
---
```

---

[← Back to Features Overview](index.html)
