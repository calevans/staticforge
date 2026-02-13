---
title: 'Getting Started with StaticForge'
description: 'Step-by-step tutorial on building your first static website using StaticForge. Covers installation, configuration, and creating content.'
author: 'Your Name'
date: '2024-01-15'
category: tutorials
tags:
  - staticforge
  - tutorial
  - beginner
  - php
url: "https://calevans.com/staticforge/examples/tutorials/blog-post.html"
og_image: "Cozy writing desk with coffee and laptop, warm lighting, blog post creation, storytelling atmosphere, evening, --ar 16:9"
---
# Getting Started with StaticForge

Welcome to this comprehensive tutorial on building static sites with **StaticForge**, a powerful PHP-based static site generator.

## What You'll Learn

In this tutorial, you'll learn:

- How to install and configure StaticForge
- Creating content with Markdown
- Using frontmatter metadata
- Organizing content with categories
- Adding tags to your posts
- Customizing templates

## Prerequisites

Before you begin, make sure you have:

- PHP 8.4 or higher installed
- Composer for dependency management
- Basic knowledge of Markdown
- A text editor of your choice

## Installation

First, install StaticForge using Composer:

```bash
composer create-project staticforge/staticforge my-site
cd my-site
```

## Configuration

Copy the example environment file:

```bash
cp .env.example .env
```

Edit `.env` to configure your site:

```bash
CONTENT_PATH=content/
OUTPUT_PATH=public/
TEMPLATE_NAME=staticforce
```

## Creating Content

Create your first content file using the CLI:

```bash
php vendor/bin/staticforge.php make:content "Hello World"
```

This creates `content/hello-world.md` with the necessary frontmatter. Open it and add your content:

```markdown
---
title: "Hello World"
date: "2026-02-12"
---

# Hello World!

This is my first post with StaticForge.
```

## Generating Your Site

Run the render command:

```bash
php bin/staticforge.php site:render
```

Your site is now in the `public/` directory!

## Next Steps

- Explore the [Configuration Guide](/guide/configuration.html)
- Learn about [custom features](/development/features.html)
- Check out more [examples](/examples.html)

Happy building! 🚀
