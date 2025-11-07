---
title = "Built-in Features"
template = "docs"
menu = 1.3, 2.3
---

# Built-in Features

StaticForge comes with several powerful features that add functionality to your site. Learn how each one works and how to use them in your content.

## What Are Features?

Features are plugins that extend StaticForge's capabilities. They listen to events during site generation and perform specific tasks like converting Markdown to HTML, building menus, or organizing content by category.

**Good to know:**
- All features are optional - you can disable any feature by deleting its directory
- Features are loaded automatically from `src/Features/`
- You can create your own custom features (see [Feature Development](FEATURE_DEVELOPMENT.html))

---

## Content Processing Features

These features handle converting your content files into HTML.

### Markdown Renderer

**What it does:** Converts `.md` files to HTML using Markdown syntax

**File types:** `.md`

**Events:** `RENDER` (priority 100)

**How it works:**

1. Reads frontmatter between `---` markers
2. Converts Markdown content to HTML using CommonMark
3. Applies your chosen Twig template
4. Outputs the final HTML file

**Example input file (`content/blog-post.md`):**

```markdown
---
title = "My First Blog Post"
description = "An introduction to my blog"
---

# Welcome to My Blog

This is my **first post** using StaticForge!

## What I'll Write About

- Web development
- PHP tutorials
- Static site generation

Pretty *exciting*, right?
```

**What you get:**

- Frontmatter is extracted and available to templates
- Markdown is converted to semantic HTML
- The title becomes `{{ title }}` in your template
- Content is wrapped in your chosen template
- File saved as `output/blog-post.html`

**No configuration needed** - just create `.md` files and go!

---

### HTML Renderer

**What it does:** Processes `.html` files and wraps them in templates

**File types:** `.html`, `.htm`

**Events:** `RENDER` (priority 100)

**How it works:**

1. Reads frontmatter from `<!-- INI ... -->` comment block
2. Extracts the HTML content
3. Applies your chosen Twig template
4. Outputs the final HTML file

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

**Key points:**

- Use `<!-- INI ... -->` for frontmatter (not `---`)
- Write regular HTML for content
- Great for custom layouts or when you need precise HTML control
- Still gets wrapped in your template like Markdown files

**When to use HTML instead of Markdown:**
- Complex layouts requiring specific HTML structure
- Embedding custom JavaScript or CSS
- Pages with forms or interactive elements
- Landing pages with specific design requirements

---

## Organization Features

These features help you organize and structure your content.

### Menu Builder

**What it does:** Automatically creates navigation menus from your content

**Events:** `POST_GLOB` (priority 100)

**How to use:** Add a `menu` field to your frontmatter

**Menu positioning system:**

The `menu` value uses a dot-notation system: `menu.position.dropdown-position`

**Single menu position:**

```markdown
---
title = "Home"
menu = 1.1
---
```
Creates: First item in menu 1

**Multiple menu positions:**

Want a page to appear in multiple menus? Just list the positions separated by commas:

```markdown
---
title = "Privacy Policy"
menu = 1.5, 2.1
---
```
Creates: Item appears in menu 1 at position 5 AND menu 2 at position 1

```markdown
---
title = "Contact Us"
menu = 1.6, 2.3, 3.1
---
```
Creates: Item appears in three different menus

**Format options:**
```markdown
menu = 1.2, 2.3         # Recommended - simple and clean
menu = [1.2, 2.3]       # Also works - brackets optional
menu = ["1.2", "2.3"]   # Also works - quotes optional
```

**More examples:**

```markdown
---
title = "About"
menu = 1.2
---
```
Creates: Second item in menu 1

```markdown
---
title = "Services"
menu = 1.3.0
---
```
Creates: Dropdown title at position 3 in menu 1 (`.0` means it's the dropdown label)

```markdown
---
title = "Web Development"
menu = 1.3.1
---
```
Creates: First item inside the "Services" dropdown

**Visual example:**

```
Menu 1 (Main Navigation):
├─ Home (1.1)
├─ About (1.2)
├─ Services (1.3.0) ▼
│  ├─ Web Development (1.3.1)
│  ├─ Mobile Apps (1.3.2)
│  └─ Consulting (1.3.3)
├─ Contact (1.4)         # Also in menu 2
└─ Privacy (1.5)         # Also in menu 2

Menu 2 (Footer):
├─ Privacy (2.1)         # Same page as 1.5
├─ Terms (2.2)
└─ Contact (2.3)         # Same page as 1.4
```

**Using menus in templates:**

**Option 1 - Include the menu template:**
```twig
<nav>
  {% include 'menu1.html.twig' %}
</nav>
```

**Option 2 - Access the HTML directly:**
```twig
<nav>
  {{ features.MenuBuilder.html.1|raw }}
</nav>
```

**Tips:**
- Use commas to place a page in multiple menus
- If you don't specify a menu number (just `menu = 1`), the item appears but in no specific order
- Duplicate positions are allowed - the last one wins
- Position `0` is special - it's always a dropdown title, never a regular link
- No need for brackets or quotes (but they work if you prefer them)

---

### Chapter Navigation

**What it does:** Automatically generates sequential prev/next navigation links for documentation pages

**Events:** `POST_GLOB` (priority 150, runs after MenuBuilder)

**Configuration:** Set via `.env` file

```bash
# Which menus should have chapter navigation
CHAPTER_NAV_MENUS="2"

# Customize navigation symbols
CHAPTER_NAV_PREV_SYMBOL="←"
CHAPTER_NAV_NEXT_SYMBOL="→"
CHAPTER_NAV_SEPARATOR="|"
```

**Disabling chapter navigation:**

To completely disable chapter navigation processing, either:
- Set `CHAPTER_NAV_MENUS=""` (empty string)
- Don't include `CHAPTER_NAV_MENUS` in your `.env` file at all

When disabled, the feature skips all processing and adds no overhead to your build.

**How it works:**

Chapter Navigation uses the menu ordering from MenuBuilder to create sequential navigation between pages. Pages that appear in the configured menus automatically get prev/next links based on their menu position.

**Example setup:**

```markdown
---
title = "Quick Start Guide"
menu = 2.1
template = "docs"
---
```

```markdown
---
title = "Configuration Guide"
menu = 2.2
template = "docs"
---
```

```markdown
---
title = "Built-in Features"
menu = 2.3
template = "docs"
---
```

**Results:**
- **Quick Start Guide** (2.1): Shows only "Next →" link to Configuration Guide
- **Configuration Guide** (2.2): Shows "← Prev" to Quick Start and "Next →" to Features
- **Built-in Features** (2.3): Shows only "← Prev" link to Configuration Guide

**Multiple menus:**

If a page appears in multiple menus (e.g., `menu = 2.1, 3.2`), and both menus are configured for chapter navigation (`CHAPTER_NAV_MENUS="2,3"`), the page will have separate navigation for each menu context.

**Using in templates:**

The chapter navigation HTML is automatically generated. To display it in your template:

```twig
{# Include the snippet (recommended) #}
{% include '_chapter_nav.html.twig' %}
```

Or access the data directly:

```twig
{% if features.ChapterNav.pages[source_file] is defined %}
  {% for menu_num, nav_data in features.ChapterNav.pages[source_file] %}
    {{ nav_data.html|raw }}
  {% endfor %}
{% endif %}
```

**Navigation data structure:**

Each page gets:
- `prev` - Previous page data (title, url, file) or null
- `current` - Current page data
- `next` - Next page data or null
- `html` - Pre-generated HTML for the navigation

**Customization:**

The navigation includes CSS classes for styling:
- `.chapter-nav` - Container
- `.chapter-nav-prev` - Previous link
- `.chapter-nav-current` - Current page (not a link)
- `.chapter-nav-next` - Next link

**Tips:**
- Works best with sequential menu positions (2.1, 2.2, 2.3)
- Dropdown items (position ends in .0) are ignored
- Only processes menus specified in `CHAPTER_NAV_MENUS`
- Place the include above your footer for best UX
- Use different symbols for different themes (arrows, text, emoji)

---

### Categories

**What it does:** Organizes content into subdirectories based on category

**Events:** `POST_RENDER` (priority 100)

**How to use:** Add a `category` field to your frontmatter

**Example:**

```markdown
---
title = "Learning PHP Basics"
category = "tutorials"
---

# Learning PHP Basics

Welcome to our PHP tutorial series!
```

**What happens:**

1. StaticForge sanitizes the category name:
   - `tutorials` → `tutorials`
   - `Web Development` → `web-development`
   - `PHP & MySQL` → `php-mysql`
   - `Cool_Stuff!` → `cool-stuff`

2. Creates the category directory: `output/tutorials/`

3. Moves your file there: `output/tutorials/learning-php-basics.html`

**Sanitization rules:**
- Converts to lowercase
- Replaces spaces and special characters with hyphens
- Removes leading/trailing hyphens
- Keeps only letters, numbers, and hyphens

**Why use categories:**
- Keep related content together
- Create logical URL structures (`/blog/`, `/tutorials/`, `/docs/`)
- Organize large sites into sections
- Enable category-specific styling or templates

**Important:** This is the **only** way to create subdirectories in your output. Without categories, all pages go in the root.

---

### Category Index Pages

**What it does:** Creates index pages that list all files in each category

**Events:**
- `POST_GLOB` (priority 200)
- `PRE_RENDER` (priority 150)
- `POST_RENDER` (priority 50)
- `POST_LOOP` (priority 100)

**How to use:** Create a `.md` file named after your category

**Example - Create `content/tutorials.md`:**

```markdown
---
type = "category"
title = "Tutorials"
description = "Learn with our step-by-step guides"
template = "category-index"
menu = 1.3
---

Browse all our tutorials below. This text will be replaced with the file listing.
```

**What you get:**

StaticForge generates `output/tutorials/index.html` containing:
- All files with `category = "tutorials"`
- Sorted, styled listing
- Pagination (if you have many files)
- Your custom template styling

**Template variables available:**

```twig
{{ category }}           {# "tutorials" #}
{{ total_files }}        {# 23 #}
{{ files }}              {# Array of file objects #}

{% for file in files %}
  <article>
    <h2><a href="{{ file.url }}">{{ file.title }}</a></h2>

    {% if file.image %}
      <img src="{{ file.image }}" alt="{{ file.title }}">
    {% endif %}

    {% if file.metadata.description %}
      <p>{{ file.metadata.description }}</p>
    {% endif %}

    <time>{{ file.date }}</time>
  </article>
{% endfor %}
```

**File object properties:**
- `file.title` - The page title
- `file.url` - Relative URL to the page
- `file.image` - Hero/featured image (if any)
- `file.date` - Publication or modification date
- `file.metadata` - All frontmatter from the file

**Example category index template:**

```twig
{% extends "base.html.twig" %}

{% block content %}
<div class="category-page">
  <h1>{{ category|title }}</h1>
  <p class="count">{{ total_files }} articles</p>

  <div class="article-grid">
    {% for file in files %}
      <article class="card">
        <h2><a href="{{ file.url }}">{{ file.title }}</a></h2>
        <p>{{ file.metadata.description|default('') }}</p>
        <a href="{{ file.url }}" class="read-more">Read more →</a>
      </article>
    {% endfor %}
  </div>
</div>
{% endblock %}
```

---

### Tags

**What it does:** Extracts tags from frontmatter and makes them available site-wide

**Events:**
- `POST_GLOB` (priority 100)
- `POST_RENDER` (priority 100)

**How to use:** Add a `tags` field to your frontmatter

**Example:**

```markdown
---
title = "Introduction to PHP"
tags = ["php", "tutorial", "beginner", "web-development"]
---

# Introduction to PHP

Learn PHP from scratch!
```

**What happens:**

1. Tags are extracted from each file during processing
2. Tags are normalized (lowercase, sanitized)
3. Tags are added to the HTML as `<meta name="keywords">`
4. Tags are available to templates for tag clouds, filtering, etc.

**Using tags in templates:**

**Display tags on a page:**
```twig
{% if tags is iterable and tags|length > 0 %}
  <div class="tags">
    {% for tag in tags %}
      <span class="tag">{{ tag }}</span>
    {% endfor %}
  </div>
{% endif %}
```

**Access all site tags:**
```twig
{% if features.Tags.all_tags is defined %}
  <div class="tag-cloud">
    {% for tag, count in features.Tags.all_tags %}
      <a href="/tags/{{ tag }}.html" class="tag-{{ count }}">
        {{ tag }} ({{ count }})
      </a>
    {% endfor %}
  </div>
{% endif %}
```

**Tag format options:**

```markdown
# Array format (recommended)
tags = ["php", "tutorial", "beginner"]

# Comma-separated string (also works)
tags = "php, tutorial, beginner"
```

**Why use tags:**
- Improve SEO with keyword meta tags
- Create tag-based navigation
- Find related content
- Build tag clouds
- Enable filtering and search

---

### RSS Feed

**What it does:** Automatically generates RSS feeds for each category

**Events:**
- `POST_RENDER` (priority 40) - Collects categorized files
- `POST_LOOP` (priority 90) - Generates RSS XML files

**How to use:** Just add a `category` to your frontmatter - RSS feeds are generated automatically!

**Example content file:**

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

**What happens:**

1. Files with categories are collected during rendering
2. After all files are processed, RSS feeds are generated
3. Each category gets its own `rss.xml` file in its directory
4. Feeds are sorted by date (newest first)
5. Feeds include title, description, author, and publication date

**Output location:**

```
public/
  tutorials/
    getting-started-with-php.html
    rss.xml                        ← RSS feed for Tutorials category
  news/
    latest-updates.html
    rss.xml                        ← RSS feed for News category
```

**RSS feed URL:**

Your RSS feed will be available at:
```
https://yoursite.com/tutorials/rss.xml
https://yoursite.com/news/rss.xml
```

**Metadata used in RSS feeds:**

| Frontmatter Field | RSS Element | Required | Default |
|-------------------|-------------|----------|---------|
| `title` | `<title>` | Yes | "Untitled" |
| `description` | `<description>` | No | Auto-extracted from content |
| `author` | `<author>` | No | Not included |
| `date` or `published_date` | `<pubDate>` | No | File modification time |
| `category` | Determines which feed | Yes | File not included |

**Auto-generated descriptions:**

If you don't provide a `description` in the frontmatter, StaticForge will:
1. Strip HTML tags from your content
2. Take the first 200 characters
3. Add "..." if truncated

**Example with all metadata:**

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

**RSS feed structure:**

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

**Adding RSS links to your site:**

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

**Best practices:**

1. **Always add dates:** Use `published_date` for consistent sorting
2. **Write good descriptions:** Either in frontmatter or first paragraph
3. **Include author emails:** Use email format for `author` field (RSS spec)
4. **Use consistent categories:** Keep category names standardized

**Testing your RSS feed:**

1. Generate your site: `php bin/console.php render:site`
2. Check the feed: `cat public/tutorials/rss.xml`
3. Validate it: Use [W3C Feed Validator](https://validator.w3.org/feed/)
4. Subscribe in a reader: Try Feedly, NewsBlur, or another RSS reader

**No categories = no RSS:**

Files without a `category` are not included in any RSS feed. This is intentional - only categorized content appears in feeds.

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

Want to add your own functionality? See the [Feature Development Guide](FEATURE_DEVELOPMENT.html) for step-by-step instructions on creating custom features.

---

## Feature Comparison Table

| Feature | Input Required | Output Created | Use Case |
|---------|---------------|----------------|----------|
| **Markdown Renderer** | `.md` files | HTML files | Writing content in Markdown |
| **HTML Renderer** | `.html` files | HTML files | Custom layouts, precise HTML control |
| **Menu Builder** | `menu` in frontmatter | Navigation HTML | Automatic menu generation |
| **Chapter Navigation** | `menu` in frontmatter | Prev/Next links | Sequential page navigation |
| **Categories** | `category` in frontmatter | Subdirectories | Organizing content into sections |
| **Category Index** | Category `.md` file | Index page | Listing all category files |
| **Tags** | `tags` in frontmatter | Meta tags, tag data | SEO, tag clouds, related content |
| **RSS Feed** | `category` in frontmatter | `rss.xml` per category | Syndication, feed readers, notifications |

