---
title: 'Getting Started with StaticForge'
description: 'Learn how to build static sites with StaticForge'
author: 'Your Name'
date: '2024-01-15'
category: tutorials
tags:
  - staticforge
  - tutorial
  - beginner
  - php
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

Create your first content file at `content/hello.md`:

```markdown
---
title = "Hello World"
description = "My first post"
tags = [hello, first-post]
---

# Hello World!

This is my first post with StaticForge.
```

## Generating Your Site

Run the render command:

```bash
php bin/console.php render:site
```

Your site is now in the `public/` directory!

## Next Steps

- Explore the [Configuration Guide](../CONFIGURATION.md)
- Learn about [custom features](../FEATURE_DEVELOPMENT.md)
- Check out more [examples](.)

Happy building! 🚀
