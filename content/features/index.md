---
title: 'Built-in Features'
template: docs
menu: '3.1'
---
# Built-in Features

StaticForge comes with several powerful features that add functionality to your site. Each feature is documented in detail on its own page.

## What Are Features?

Features are plugins that extend StaticForge's capabilities. They listen to events during site generation and perform specific tasks like converting Markdown to HTML, building menus, or organizing content by category.

**Good to know:**
- All features are optional - you can disable any feature by deleting its directory
- Features are loaded automatically from `src/Features/`
- You can create your own custom features (see [Feature Development](../development/features.html))

---

## Content Processing Features

These features handle converting your content files into HTML.

### [Markdown Renderer](markdown-renderer.html)

Converts `.md` files to HTML using Markdown syntax. Perfect for writing blog posts, articles, and documentation in a simple, readable format.

[Read more about Markdown Renderer →](markdown-renderer.html)

### [HTML Renderer](html-renderer.html)

Processes `.html` files and wraps them in templates. Ideal for custom layouts, landing pages, or when you need precise HTML control.

[Read more about HTML Renderer →](html-renderer.html)

---

## Interactive Features

These features add interactivity to your static pages.

### [Forms](forms.html)

Embed contact forms and other input forms using simple shortcodes. Supports configuration via `siteconfig.yaml`, AJAX submission, and Altcha spam protection.

[Read more about Forms →](forms.html)

### [Search](search.html)

Adds full-text search capability to your site using MiniSearch. Generates a client-side index and provides a fast, static search experience.

[Read more about Search →](search.html)

---

## Organization Features

These features help you organize and structure your content.

### [Menu Builder](menu-builder.html)

Automatically creates navigation menus from your content using a simple dot-notation system. Supports multiple menus, dropdowns, and flexible positioning.

[Read more about Menu Builder →](menu-builder.html)

### [Chapter Navigation](https://github.com/calevans/staticforge-chapternav)

Generates sequential prev/next navigation links for documentation pages. Perfect for tutorials, guides, and any content that follows a specific order.

[Read more about Chapter Navigation →](https://github.com/calevans/staticforge-chapternav)

### [Categories](categories.html)

Organizes content into subdirectories based on category metadata. The only way to create subdirectories in your output.

[Read more about Categories →](categories.html)

### [Category Index Pages](category-index.html)

Creates index pages that list all files in each category. Automatically generates organized directory listings with pagination support.

[Read more about Category Index Pages →](category-index.html)

### [Tags](tags.html)

Extracts tags from frontmatter and makes them available site-wide. Great for SEO, tag clouds, and content filtering.

[Read more about Tags →](tags.html)

---

## SEO & Search Engine Features

These features help optimize your site for search engines.

### [Robots.txt Generator](robots-txt.html)

Automatically generates a `robots.txt` file to control search engine crawling. Keep private pages out of search results effortlessly.

[Read more about Robots.txt Generator →](robots-txt.html)

### [RSS Feed](rss-feed.html)

Automatically generates RSS feeds for each category. Enable readers to subscribe to your content updates.

[Read more about RSS Feed →](rss-feed.html)

### [Sitemap Generator](sitemap.html)

Automatically generates a `sitemap.xml` file for search engines. Critical for SEO to help search engines index your site correctly.

[Read more about Sitemap Generator →](sitemap.html)

---

## Managing Features

### Disabling Features

Don't need a feature? Just delete or rename its directory:

```bash
# Disable categories completely
rm -rf src/Features/Categories

# Temporarily disable (can re-enable by renaming back)
mv src/Features/Categories src/Features/Categories.disabled
```

StaticForge will continue working without that feature.

### Which Features Can I Disable?

**You can safely disable:**
- RssFeed - if you don't need RSS/Atom syndication
- Categories - if you don't need subdirectories
- CategoryIndex - if you don't want category listing pages
- Tags - if you don't use tags
- MenuBuilder - if you build menus manually
- ChapterNav - if you don't need sequential navigation

**Don't disable these (unless you know what you're doing):**
- MarkdownRenderer - needed to process `.md` files
- HtmlRenderer - needed to process `.html` files

### Creating Custom Features

Want to add your own functionality? See the [Feature Development Guide](../development/features.html) for step-by-step instructions on creating custom features.

---

## Feature Comparison Table

| Feature | Input Required | Output Created | Use Case |
|---------|---------------|----------------|----------|
| **[Markdown Renderer](markdown-renderer.html)** | `.md` files | HTML files | Writing content in Markdown |
| **[HTML Renderer](html-renderer.html)** | `.html` files | HTML files | Custom layouts, precise HTML control |
| **[Menu Builder](menu-builder.html)** | `menu` in frontmatter | Navigation HTML | Automatic menu generation |
| **[Chapter Navigation](https://github.com/calevans/staticforge-chapternav)** | `menu` in frontmatter | Prev/Next links | Sequential page navigation |
| **[Categories](categories.html)** | `category` in frontmatter | Subdirectories | Organizing content into sections |
| **[Category Index](category-index.html)** | Category `.md` file | Index page | Listing all category files |
| **[Tags](tags.html)** | `tags` in frontmatter | Meta tags, tag data | SEO, tag clouds, related content |
| **[Robots.txt Generator](robots-txt.html)** | `robots` in frontmatter | robots.txt file | SEO, search engine control |
| **[RSS Feed](rss-feed.html)** | `category` in frontmatter | `rss.xml` per category | Syndication, feed readers, notifications |
| **[Sitemap Generator](sitemap.html)** | `sitemap` in frontmatter | `sitemap.xml` file | SEO, search engine indexing |
| **[Search](search.html)** | Content files | `search.json` & assets | Client-side full-text search |

---

## External Features

Looking for more? Check out our [External Features](external-features.html) page for a list of community-maintained packages that extend StaticForge with specialized functionality like Podcasting and Media Inspection.

