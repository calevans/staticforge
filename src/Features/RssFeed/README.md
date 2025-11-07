# RSS Feed Feature

This directory contains the RSS Feed feature for StaticForge.

## What It Does

The RSS Feed feature automatically generates RSS 2.0 feeds for categorized content. When you assign a category to your content files, they will be included in an RSS feed for that category.

## How It Works

1. **POST_RENDER Event (Priority 40)**: Collects all files that have a `category` in their frontmatter
2. **POST_LOOP Event (Priority 90)**: After all content is processed, generates `rss.xml` files in each category directory

## Output

For each category with content, an `rss.xml` file is created in that category's directory:

```
public/
  tutorials/
    article1.html
    article2.html
    rss.xml          ← RSS feed for Tutorials
  news/
    post1.html
    rss.xml          ← RSS feed for News
```

## RSS Feed Structure

Each RSS feed includes:
- **Channel metadata**: Site name, category name, description, language, build date
- **Items** (articles): Title, link, GUID, publication date, description, author (if provided)
- **Atom self-link**: For feed auto-discovery
- **Sorted by date**: Newest articles first

## Frontmatter Fields Used

| Field | Required | Purpose | Default |
|-------|----------|---------|---------|
| `title` | Yes | Article title | "Untitled" |
| `category` | Yes | Determines which feed | Not included in RSS if missing |
| `description` | No | Article description | Auto-extracted from content (200 chars) |
| `author` | No | Author email/name | Not included |
| `published_date` or `date` | No | Publication date | File modification time |

## Example Content File

```markdown
---
title = "Getting Started with PHP"
category = "Tutorials"
description = "Learn the basics of PHP programming"
author = "dev@example.com"
published_date = "2024-01-15"
---

# Getting Started with PHP

Your content here...
```

This will be included in `/tutorials/rss.xml`.

## RSS Feed URL

RSS feeds are accessible at:
```
https://yoursite.com/{category-slug}/rss.xml
```

Where `{category-slug}` is the lowercase, hyphenated version of your category name.

## Features

- ✅ Valid RSS 2.0 XML with Atom namespace
- ✅ Proper XML escaping for special characters
- ✅ Sorted by publication date (newest first)
- ✅ Auto-generated descriptions from content if not provided
- ✅ Support for author metadata
- ✅ Category-based feed organization
- ✅ Full URL generation for feed readers
- ✅ Standards-compliant date formatting (RFC 2822)

## Testing

Run the unit tests:
```bash
php vendor/bin/phpunit tests/Unit/Features/RssFeedFeatureTest.php
```

Run the integration tests:
```bash
php vendor/bin/phpunit tests/Integration/RssFeedIntegrationTest.php
```

## Validation

Validate your RSS feeds using:
- [W3C Feed Validator](https://validator.w3.org/feed/)
- Feed readers like Feedly, NewsBlur, or Inoreader

## Disabling

If you don't need RSS feeds, you can disable this feature by:

```bash
rm -rf src/Features/RssFeed
# or
mv src/Features/RssFeed src/Features/RssFeed.disabled
```

## Implementation Details

- **Class**: `EICC\StaticForge\Features\RssFeed\Feature`
- **Events**: `POST_RENDER` (40), `POST_LOOP` (90)
- **Dependencies**: None (uses built-in PHP XML functions)
- **Output Format**: RSS 2.0 with Atom extensions

## Code Quality

- ✅ Comprehensive unit tests (16 test cases)
- ✅ Integration tests (4 test scenarios)
- ✅ PSR-12 coding standards
- ✅ Full type declarations
- ✅ Proper PHPDoc comments
- ✅ No static methods or properties
