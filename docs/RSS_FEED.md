---
title = "RSS Feed"
template = "docs"
menu = 1.3.9, 2.3.9
---

# RSS Feed

**What it does:** Automatically generates RSS feeds for each category

**Events:**
- `POST_RENDER` (priority 40) - Collects categorized files
- `POST_LOOP` (priority 90) - Generates RSS XML files

**How to use:** Just add a `category` to your frontmatter - RSS feeds are generated automatically!

## Example Content File

```markdown
---
title = "Getting Started with PHP"
category = "Tutorials"
description = "A beginner-friendly introduction to PHP programming"
author = "Jane Doe"
date = "2024-01-15"
---

# Getting Started with PHP

This tutorial will teach you the basics of PHP...
```

## What Happens

1. Files with categories are collected during rendering
2. After all files are processed, RSS feeds are generated
3. Each category gets its own `rss.xml` file in its directory
4. Feeds are sorted by date (newest first)
5. Feeds include title, description, author, and publication date

## Output Location

```
public/
  tutorials/
    getting-started-with-php.html
    rss.xml                        ← RSS feed for Tutorials category
  news/
    latest-updates.html
    rss.xml                        ← RSS feed for News category
```

## RSS Feed URL

Your RSS feed will be available at:
```
https://yoursite.com/tutorials/rss.xml
https://yoursite.com/news/rss.xml
```

## Metadata Used in RSS Feeds

| Frontmatter Field | RSS Element | Required | Default |
|-------------------|-------------|----------|---------|
| `title` | `<title>` | Yes | "Untitled" |
| `description` | `<description>` | No | Auto-extracted from content |
| `author` | `<author>` | No | Not included |
| `date` or `published_date` | `<pubDate>` | No | File modification time |
| `category` | Determines which feed | Yes | File not included |

## Auto-Generated Descriptions

If you don't provide a `description` in the frontmatter, StaticForge will:
1. Strip HTML tags from your content
2. Take the first 200 characters
3. Add "..." if truncated

## Example with All Metadata

```markdown
---
title = "Advanced PHP Techniques"
category = "Tutorials"
description = "Learn advanced PHP patterns and best practices"
author = "john.doe@example.com"
published_date = "2024-03-20"
---

Content here...
```

## RSS Feed Structure

```xml
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>Your Site - Tutorials</title>
    <link>https://yoursite.com/tutorials/</link>
    <description>Tutorials articles from Your Site</description>
    <language>en-us</language>
    <lastBuildDate>Thu, 07 Nov 2024 12:00:00 +0000</lastBuildDate>
    <atom:link href="https://yoursite.com/tutorials/rss.xml" rel="self" type="application/rss+xml" />
    
    <item>
      <title>Advanced PHP Techniques</title>
      <link>https://yoursite.com/tutorials/advanced-php-techniques.html</link>
      <guid>https://yoursite.com/tutorials/advanced-php-techniques.html</guid>
      <pubDate>Wed, 20 Mar 2024 00:00:00 +0000</pubDate>
      <description>Learn advanced PHP patterns and best practices</description>
      <author>john.doe@example.com</author>
    </item>
  </channel>
</rss>
```

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

1. Generate your site: `php bin/console.php render:site`
2. Check the feed: `cat public/tutorials/rss.xml`
3. Validate it: Use [W3C Feed Validator](https://validator.w3.org/feed/)
4. Subscribe in a reader: Try Feedly, NewsBlur, or another RSS reader

## No Categories = No RSS

Files without a `category` are not included in any RSS feed. This is intentional - only categorized content appears in feeds.

---

[← Back to Features Overview](FEATURES.html)
