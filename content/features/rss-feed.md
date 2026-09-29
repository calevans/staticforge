---
title: 'RSS Feed'
description: 'How to use the RSS Feed feature to syndicate content and generate category-based feeds.'
template: docs
menu: '3.1.10'
og_image: "Radio tower broadcasting digital waves, RSS symbol glowing orange, global syndication network, connection lines, --ar 16:9"
---
# RSS Feed

**What it does:** Automatically generates RSS feeds for each category

**Events:**
- `POST_RENDER` (priority 40) - Collects categorized files
- `POST_LOOP` (priority 90) - Generates RSS XML files
- `RSS_ITEM_BUILDING` - Fired for each item before adding to feed

**How to use:** Just add a `category` to your frontmatter - RSS feeds are generated automatically!

## Basic Usage

StaticForge automatically generates an RSS feed for every category on your site. Any content file (post, page, etc.) that has a `category` defined in its frontmatter will be included in that category's feed.

### 1. Categorize Your Content

Add a `category` field to your content's frontmatter.

```markdown
---
title: "Getting Started with PHP"
category: "Tutorials"
description: "A beginner-friendly introduction to PHP programming"
author: "Jane Doe"
date: "2024-01-15"
---

# Getting Started with PHP

This tutorial will teach you the basics of PHP...
```

### 2. Generate Your Site

Run the build command:

```bash
php bin/staticforge.php site:render
```

### 3. Find Your Feeds

StaticForge creates an `rss.xml` file in each category's directory.

```
public/
  tutorials/
    getting-started-with-php.html
    rss.xml                        ← RSS feed for Tutorials category
  news/
    latest-updates.html
    rss.xml                        ← RSS feed for News category
```

Your feed URLs will be:
- `https://yoursite.com/tutorials/rss.xml`
- `https://yoursite.com/news/rss.xml`

## Metadata

You can control how your content appears in the feed using frontmatter.

| Frontmatter Field | RSS Element | Required | Default |
|-------------------|-------------|----------|---------|
| `title` | `<title>` | Yes | "Untitled" |
| `description` | `<description>` | No | Auto-extracted from content (first 200 chars) |
| `author` | `<author>` | No | Not included |
| `date` or `published_date` | `<pubDate>` | No | File modification time |
| `category` | Determines feed | Yes | File not included |

## Adding RSS Links to Your Site

In your category index or base template:

```twig
<!-- Link to RSS feed in <head> -->
{% if category %}
<link rel="alternate" type="application/rss+xml"
      title="{{ site_name }} - {{ category }}"
      href="/{{ category|lower|replace({' ': '-'}) }}/rss.xml" />
{% endif %}

<!-- Display RSS link in content -->
{% if category %}
<a href="/{{ category|lower|replace({' ': '-'}) }}/rss.xml" class="rss-link">
  Subscribe to {{ category }} RSS Feed
</a>
{% endif %}
```

## Best Practices

1. **Always add dates:** Use `published_date` for consistent sorting
2. **Write good descriptions:** Either in frontmatter or first paragraph
3. **Include author emails:** Use email format for `author` field (RSS spec)
4. **Use consistent categories:** Keep category names standardized

## Testing Your RSS Feed

1. Generate your site: `php bin/staticforge.php site:render`
2. Check the feed: `cat public/tutorials/rss.xml`
3. Validate it: Use [W3C Feed Validator](https://validator.w3.org/feed/)
4. Subscribe in a reader: Try Feedly, NewsBlur, or another RSS reader

## No Categories = No RSS

Files without a `category` are not included in any category RSS feed. This is intentional - only categorized content appears in category feeds. (To put an uncategorized page in the site-wide feeds, see below.)

---

## Site-Wide Feeds

**What it does:** Writes one feed for the whole site in RSS 2.0, Atom 1.0, and JSON Feed 1.1.
**Events:** `POST_RENDER` (priority 120) - Collects pages; `POST_LOOP` (priority 95) - Writes the feeds; `SITE_FEED_INIT` and `SITE_FEED_ITEM_BUILDING` - Fired for each feed and each item
**How to use:** Set `feed.enabled: true` in `siteconfig.yaml`.

The site-wide feeds sit beside the category feeds and do not replace them. Category `rss.xml` files, their `RSS_BUILDER_INIT` and `RSS_ITEM_BUILDING` events, and podcast feeds all work exactly as described above.

| Format | File | URL |
|--------|------|-----|
| RSS 2.0 | `feed.xml` | `https://yoursite.com/feed.xml` |
| Atom 1.0 | `feed.atom` | `https://yoursite.com/feed.atom` |
| JSON Feed 1.1 | `feed.json` | `https://yoursite.com/feed.json` |

### Enabling

Site feeds are off by default, so existing sites get no new files until they opt in. Sites created with `site:init` have them enabled from the start. To turn them on:

```yaml
feed:
  enabled: true
```

`SITE_BASE_URL` must be set in `.env`. If it is empty, no feeds are written and the build logs a warning. If your `content/` directory already contains a file with the same name (for example `content/feed.xml`), your file is kept and that format is skipped.

### Configuration

| Key | Default | Meaning |
|-----|---------|---------|
| `enabled` | `false` | Write the site feeds. |
| `limit` | `20` | Newest items to include in each site feed. Must be a positive integer. |
| `formats` | `[rss, atom, json]` | Which site feeds to write. |
| `exclude_categories` | `[]` | Categories to leave out of the site feeds. Names are turned into slugs, so `Podcast` and `podcast` are the same. |
| `category_formats` | `[rss]` | Formats written for each category. Add `atom` and/or `json` to get more than the existing `rss.xml`. |

A full example:

```yaml
feed:
  enabled: true
  limit: 20
  formats: [rss, atom, json]
  exclude_categories: [podcast]
  category_formats: [rss, atom, json]
```

Invalid values fall back to their defaults and log a warning during the build. `audit:config` reports them as configuration problems.

The feed title is `site.name` and the feed description is `site.tagline`, both from `siteconfig.yaml`. If there is no tagline, the description is "Latest posts from" followed by the site name.

### Which Pages Are Included

A page appears in the site feeds when it has a `date` in its frontmatter and either a `category` or `feed: true`.

```markdown
---
title: "About the Team"
date: "2024-03-01"
feed: true
---
```

Optional `updated` sets the entry's modified date; without it the `date` is used. A `date` that cannot be parsed leaves the page out and logs a warning.

These pages are never included:

- Pages with `draft: true`, `noindex: true`, `robots: no`, or `sitemap: false`
- Pages with `feed: false` (this applies to the site feeds only; category Atom and JSON feeds do not check it)
- The `404` page
- Generated pages: category listings, category definition files, and tag pages
- Pages in a category listed in `exclude_categories`
- Pages in a category whose definition file has `podcast: true`. The site feeds cannot carry podcast enclosures, so podcast categories keep their own feed
- Pages whose `draft`, `noindex`, `sitemap` or `robots` value is not a clear yes or no (for example a list). A hidden-page setting that cannot be read hides the page rather than publishing it
- Pages whose category has no letters or digits (for example one written only in Japanese or emoji). There is no safe feed address for it, so the page is left out and a warning is logged
- Pages that were not rendered with content markers, such as output from a custom renderer. They would publish the whole templated page, so they are skipped with a warning

Category Atom and JSON feeds only list dated pages: Atom requires a date on every entry, and StaticForge does not invent one. (The category `rss.xml` is different and still uses the file's modified time for pages without a date.)

Entries are sorted newest first by `date`, then cut to `limit`. Each entry carries the page title, the `description` (or the first 200 characters of the text), the page's HTML, the `author` if set, and the category and `tags` as tags. Links inside the HTML are made absolute. When the page uses content markers, only the article is included, not the surrounding theme.

### Category Atom and JSON Feeds

By default the only per-category feed is the existing `rss.xml`. Add formats to also write `/{category}/feed.atom` and `/{category}/feed.json`:

```yaml
feed:
  enabled: true
  category_formats: [rss, atom, json]
```

The `rss` entry is accepted but never rewrites `rss.xml`; that file always belongs to the category feed described above. Category Atom and JSON feeds follow the same inclusion rules as the site feeds, except that the item limit does not apply and `feed: true` alone does not add an uncategorized page. Excluded and podcast categories get none.

Tag feeds are not built.

### Autodiscovery Links

When site feeds are enabled, the bundled `staticforce` and `sample` themes add a `<link rel="alternate">` tag to every page's `<head>` for each site feed that is written, so browsers and readers can find them.

Custom themes get the same list in the `feed_links` template variable. Each entry has `type`, `title`, and `href`. The variable is not set when feeds are disabled or `SITE_BASE_URL` is empty, so keep the `{% if feed_links %}` guard.

```twig
{% if feed_links %}
  {% for l in feed_links %}
    <link rel="alternate" type="{{ l.type }}" title="{{ l.title }}" href="{{ l.href }}">
  {% endfor %}
{% endif %}
```

The types are `application/rss+xml`, `application/atom+xml`, and `application/feed+json`.

### Customizing Feeds From Code

Two events let your own Feature change the site and category Atom/JSON feeds before they are written: `SITE_FEED_INIT` for the feed header and `SITE_FEED_ITEM_BUILDING` for each entry. See [Events](../development/events.html) for their payloads.

---

[← Back to Features Overview](index.html)
