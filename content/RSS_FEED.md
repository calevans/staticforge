---
title: 'RSS Feed'
template: docs
menu: '1.3.10, 2.3.10'
category: docs
---
# RSS Feed

**What it does:** Automatically generates RSS feeds for each category

**Events:**
- `POST_RENDER` (priority 40) - Collects categorized files
- `POST_LOOP` (priority 90) - Generates RSS XML files

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
php bin/console.php site:render
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

## Podcasts

StaticForge supports generating iTunes-compatible podcast feeds. This is a specialized type of RSS feed designed for podcast players like [Apple Podcasts](https://podcasters.apple.com/support/823-podcast-requirements), [Spotify](https://support.spotify.com/us/podcasters/article/your-rss-feed/), and [Overcast](https://overcast.fm/podcasterinfo).

To create a podcast, you need to:
1.  Define a category as a podcast.
2.  Add episodes to that category.

### 1. Configure the Podcast Category

Create a category definition file (e.g., `content/podcast.md`) to tell StaticForge that this category is a podcast. You must set `rss_type: podcast` and provide the required iTunes metadata.

```markdown
---
type: category
rss_type: podcast
itunes_author: "Your Name"
itunes_summary: "A show about interesting things."
itunes_owner_name: "Your Name"
itunes_owner_email: "you@example.com"
itunes_image: "https://example.com/podcast-cover.jpg"
itunes_category: "Technology"
itunes_explicit: false
---
```

### 2. Create Episodes

Add episodes to the category. Unlike regular content, podcast episodes **must** include an `audio_file` (or `video_file`) in the frontmatter.

```markdown
---
title: "Episode 1: The Beginning"
category: podcast
audio_file: "/media/episode1.mp3"
itunes_duration: "30:00"
itunes_episode: 1
itunes_season: 1
itunes_explicit: false
---

Show notes for this episode...
```

### Asset Management for Episodes

StaticForge handles your media files automatically:

*   **Local Files:** If `audio_file` points to a local file (e.g., `/media/episode1.mp3`), StaticForge will:
    1. Look for the file in your `content` directory (e.g., `content/media/episode1.mp3`).
    2. Copy it to `public/assets/media/`.
    3. Automatically detect the file size and MIME type for the RSS feed.
*   **Remote Files:** If `audio_file` is a URL (starts with `http` or `https`), it is used as-is. You can optionally provide `audio_size` and `audio_type` manually if needed.

### Inspecting Media

You can use the `site:inspect-media` command to automatically analyze your media files (local or remote) and update your episode frontmatter with the correct size, type, and duration.

```bash
php bin/console.php site:inspect-media content/podcast/episode-01.md
```

This command will:
1.  Download the file (if remote) to a temporary location.
2.  Analyze it to find the exact file size, MIME type, and duration.
3.  Update your markdown file with `audio_size`, `audio_type`, and `itunes_duration`.

### Supported iTunes Tags

**Channel Level (Category Definition):**
*   `itunes_author`
*   `itunes_summary` (falls back to `description`)
*   `itunes_owner_name`
*   `itunes_owner_email`
*   `itunes_image`
*   `itunes_category` (Supports multiple categories and subcategories via `>`)
*   `itunes_explicit`
*   `itunes_type` (episodic or serial)
*   `copyright`

**Example Category Definition:**
```yaml
type: category
rss_type: podcast
itunes_author: "Jane Doe"
itunes_summary: "A show about tech."
itunes_owner_name: "Jane Doe"
itunes_owner_email: "jane@example.com"
itunes_image: "https://example.com/cover.jpg"
itunes_explicit: false
itunes_type: episodic
copyright: "© 2025 Jane Doe"
itunes_category:
  - "Technology"
  - "Arts > Visual Arts"
```

**Item Level (Episode):**
*   `itunes_duration`
*   `itunes_episode`
*   `itunes_season`
*   `itunes_explicit`
*   `itunes_image` (for episode-specific artwork)

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

1. Generate your site: `php bin/console.php site:render`
2. Check the feed: `cat public/tutorials/rss.xml`
3. Validate it: Use [W3C Feed Validator](https://validator.w3.org/feed/)
4. Subscribe in a reader: Try Feedly, NewsBlur, or another RSS reader

## No Categories = No RSS

Files without a `category` are not included in any RSS feed. This is intentional - only categorized content appears in feeds.

---

[← Back to Features Overview](FEATURES.html)
