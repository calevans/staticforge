---
title = "Categories"
template = "docs"
menu = 1.3.5, 2.8
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

## Why Use Categories

- Keep related content together
- Create logical URL structures (`/blog/`, `/tutorials/`, `/docs/`)
- Organize large sites into sections
- Enable category-specific styling or templates

**Important:** This is the **only** way to create subdirectories in your output. Without categories, all pages go in the root.

---

[← Back to Features Overview](FEATURES.html)
