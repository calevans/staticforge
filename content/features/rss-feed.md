---
title: 'RSS Feed'
description: 'How to use the RSS Feed feature to syndicate content and generate category-based feeds.'
template: docs
menu: '3.1.10'
url: "https://calevans.com/staticforge/features/rss-feed.html"
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

Files without a `category` are not included in any RSS feed. This is intentional - only categorized content appears in feeds.

---

[← Back to Features Overview](index.html)
