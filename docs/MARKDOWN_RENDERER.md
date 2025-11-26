---
title: 'Markdown Renderer'
template: docs
menu: '1.3.07, 2.3.07'
category: docs
---
# Markdown Renderer

**What it does:** Converts `.md` files to HTML using Markdown syntax

**File types:** `.md`

**Events:** `RENDER` (priority 100)

**How it works:**

1. Reads frontmatter between `---` markers
2. Converts Markdown content to HTML using CommonMark
3. Applies your chosen Twig template
4. Outputs the final HTML file

## Example

**Example input file (`content/blog-post.md`):**

```markdown
---
title: "My First Blog Post"
description: "An introduction to my blog"
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

## Draft Content

You can mark a file as a draft to exclude it from the build (unless `SHOW_DRAFTS=true` is set in `.env`).

```markdown
---
title: "Work In Progress"
draft: true
---
```

This is useful for working on content that isn't ready to be published yet.

---

[← Back to Features Overview](FEATURES.html)
