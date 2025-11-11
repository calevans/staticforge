---
title = "Categories"
template = "docs"
menu = 1.3.5, 2.3.5
category = "docs"
---

# Categories

**What it does:** Organizes content into subdirectories based on category

**Events:** `POST_RENDER` (priority 100)

**How to use:** Add a `category` field to your frontmatter

## Example

```markdown
---
title = "Learning PHP Basics"
category = "tutorials"
---

# Learning PHP Basics

Welcome to our PHP tutorial series!
```

## What Happens

1. StaticForge sanitizes the category name:
   - `tutorials` → `tutorials`
   - `Web Development` → `web-development`
   - `PHP & MySQL` → `php-mysql`
   - `Cool_Stuff!` → `cool-stuff`

2. Creates the category directory: `output/tutorials/`

3. Moves your file there: `output/tutorials/learning-php-basics.html`

## Sanitization Rules

- Converts to lowercase
- Replaces spaces and special characters with hyphens
- Removes leading/trailing hyphens
- Keeps only letters, numbers, and hyphens

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

[← Back to Features Overview](FEATURES.html)
