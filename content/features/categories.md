---
title: Categories
description: 'How to use the Categories feature to organize content, apply directory-specific templates, and structure your site.'
template: docs
menu: '3.1.3'
---
# Categories

**What it does:** Organizes content into subdirectories and applies category-specific templates

**Events:** `POST_GLOB` (priority 250), `POST_RENDER` (priority 100)

**How to use:** Add a `category` field to your frontmatter, or create category definition files

---

## Basic Usage

Add a `category` field to your content frontmatter:

```markdown
---
title: "Learning PHP Basics"
category: "tutorials"
---

# Learning PHP Basics

Welcome to our PHP tutorial series!
```

---

## Category Definition Files

Create a category definition file to specify templates for all content in that category:

**File:** `content/tutorials.md`

```markdown
---
type: category
template: tutorial
---
```

Now all files with `category: "tutorials"` will automatically use the `tutorial.html.twig` template.

---

## Template Inheritance

Categories feature applies templates via POST_GLOB event with the following priority:

1. **File frontmatter template** - If file has `template = "xyz"`, use it
2. **Category template** - If file belongs to category with defined template, use it
3. **Default template** - Falls back to base template

This happens automatically during the discovery phase, before rendering.

---

## What Happens During POST_GLOB

1. **Scan for category definitions** - Finds files with `type = "category"`
2. **Extract category templates** - Stores mapping of category slug → template name
3. **Apply to content files** - Iterates all discovered files and applies category templates

---

## What Happens During POST_RENDER

1. StaticForge sanitizes the category name:
   - `tutorials` → `tutorials`
   - `Web Development` → `web-development`
   - `PHP & MySQL` → `php-mysql`
   - `Cool_Stuff!` → `cool-stuff`

2. Creates the category directory: `output/tutorials/`

3. Moves your file there: `output/tutorials/learning-php-basics.html`

---

## Sanitization Rules

- Converts to lowercase
- Replaces spaces and special characters with hyphens
- Removes leading/trailing hyphens
- Keeps only letters, numbers, and hyphens

---

## Double-Nesting Prevention

StaticForge automatically prevents double-nesting when your source directory structure matches your category name:

- Source file: `docs/configuration.md` with `category = "docs"`
- Output: `public/docs/configuration.html` (not `public/docs/docs/configuration.html`)

This smart detection ensures clean URL structures when you organize both your source files and categories logically.

## Why Use Categories

- Keep related content together
- Create logical URL structures (`/blog/`, `/tutorials/`, `/docs/`)
- Organize large sites into sections
- Enable category-specific styling or templates

**Important:** This is the **only** way to create subdirectories in your output. Without categories, all pages go in the root.

---

[← Back to Features Overview](index.html)
