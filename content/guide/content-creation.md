---
title: Content Creation
description: 'How to use the CLI to quickly generate content files.'
template: docs
---

# Content Creation

## Overview
StaticForge provides a convenient CLI command to generate new content files with pre-configured frontmatter. This ensures your files are correctly formatted and placed in the right directories without manual copy-pasting.

## The `make:content` Command

Use `make:content` to create a new Markdown file.

```bash
php vendor/bin/staticforge.php make:content "My Post Title"
```

### Options

| Option | Shorthand | Description | Default |
| :--- | :--- | :--- | :--- |
| `--type` | `-t` | Specify a subdirectory/category (e.g., `blog`, `docs`) | `(root content dir)` |
| `--date` | `-d` | Set a custom publish date (YYYY-MM-DD) | `(Today)` |
| `--draft` | `-D` | Mark the content as a draft | `false` |

### Examples

**Create a standard page:**
```bash
php vendor/bin/staticforge.php make:content "About Us"
# Creates: content/about-us.md
```

**Create a blog post:**
```bash
php vendor/bin/staticforge.php make:content "Release Notes v1.0" --type=blog
# Creates: content/blog/release-notes-v1-0.md
# Adds 'category: blog' to frontmatter
```

**Create a draft documentation page:**
```bash
php vendor/bin/staticforge.php make:content "Advanced Guide" --type=docs --draft
# Creates: content/docs/advanced-guide.md
# Adds 'draft: true' to frontmatter
```

## Structure of Generated Files

The command generates a file with valid YAML frontmatter and a starting header:

```markdown
---
title: "Release Notes v1.0"
date: "2026-02-12"
category: "blog"
---

# Release Notes v1.0

Write your content here...
```
