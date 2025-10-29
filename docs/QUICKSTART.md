# Quick Start Guide

Get StaticForge running in 5 minutes.

## Prerequisites

- PHP 8.4+
- Composer
- MySQL/MariaDB 11.3+
- Lando (for development)

---

## Installation

### 1. Clone or Create Project

```bash
# Via Composer (when published)
composer create-project staticforge/staticforge my-site

# Or clone repository
git clone https://github.com/staticforge/staticforge.git my-site
cd my-site
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Configure Environment

```bash
cp .env.example .env
```

Edit `.env` with your settings (defaults work for Lando):

```bash
CONTENT_PATH=content/
OUTPUT_PATH=public/
TEMPLATE_NAME=sample

DB_HOST=database
DB_NAME=lamp
DB_USER=lamp
DB_PASS=lamp
```

### 4. Start Development Environment

```bash
lando start
```

### 5. Run Database Migrations

```bash
lando php migrations/run_migrations.php
```

### 6. Add Sample Menu (Optional)

```bash
lando mysql lamp < migrations/sample_menu.sql
```

---

## Your First Page

### Create Content File

Create `content/hello.md`:

```markdown
---
title = "Hello World"
description = "My first StaticForge page"
tags = [staticforge, tutorial]
---

# Hello World!

Welcome to **StaticForge** - a PHP-based static site generator.

## Features

- Markdown and HTML support
- Twig templating
- Categories and tags
- Menu management
- Event-driven architecture
```

### Generate Site

```bash
lando php bin/console.php render:site
```

### View Your Page

Open in browser:
```
https://static-forge.lndo.site/hello.html
```

Or open `public/hello.html` directly.

---

## Create Your Homepage

Edit `content/index.md`:

```markdown
---
title = "Welcome"
description = "StaticForge site"
---

# Welcome to My Site

This is my homepage built with StaticForge.

## Latest Posts

- [Hello World](/hello.html)
- More coming soon!
```

Rebuild:
```bash
lando php bin/console.php render:site --clean
```

---

## Add a Category

### 1. Create Category in Database

```bash
lando mysql lamp
```

```sql
INSERT INTO categories (slug, title, description)
VALUES ('tutorials', 'Tutorials', 'Step-by-step tutorials');
```

### 2. Tag Content with Category

Edit `content/hello.md`:

```markdown
---
title = "Hello World"
description = "My first StaticForge page"
category = tutorials
tags = [staticforge, tutorial]
---
```

### 3. Rebuild

```bash
lando php bin/console.php render:site
```

### 4. View Category Page

Open: `https://static-forge.lndo.site/category/tutorials/index.html`

---

## Add Navigation Menu

### 1. Create Menu

```bash
lando mysql lamp
```

```sql
-- Create menu
INSERT INTO menus (id, name, description)
VALUES (1, 'Primary', 'Main navigation');

-- Add menu items
INSERT INTO menu_items (menu_id, label, url, position, parent_id) VALUES
(1, 'Home', '/index.html', 1, NULL),
(1, 'Tutorials', '/category/tutorials/index.html', 2, NULL),
(1, 'About', '/about.html', 3, NULL);
```

### 2. Create About Page

Create `content/about.md`:

```markdown
---
title = "About"
description = "About this site"
---

# About

This site is built with StaticForge.
```

### 3. Rebuild

```bash
lando php bin/console.php render:site
```

Your menu now appears on all pages!

---

## Change Theme

### 1. Update Configuration

Edit `.env`:

```bash
TEMPLATE_NAME=terminal
```

### 2. Rebuild with Clean

```bash
lando php bin/console.php render:site --clean
```

The `terminal` theme gives a retro terminal aesthetic.

### 3. Try Other Themes

Available themes:
- `sample` - Clean, modern design
- `terminal` - Retro terminal style
- `vaulttech` - Vault-Tec inspired (Fallout theme)

---

## Development Workflow

### Quick Commands

```bash
# Full site rebuild (clean)
lando php bin/console.php render:site --clean

# Rebuild single file for testing
lando php bin/console.php render:page content/hello.md

# Rebuild with verbose output
lando php bin/console.php render:site -v

# Use different theme without changing .env
lando php bin/console.php render:site --template=terminal
```

### Watch for Changes (Manual)

```bash
# In one terminal, watch for changes
watch -n 2 'lando php bin/console.php render:site'

# Edit content files
# Site rebuilds every 2 seconds
```

### Run Tests

```bash
# All tests
lando phpunit

# Specific test
lando phpunit tests/Unit/Core/EventManagerTest.php

# With coverage
lando phpunit --coverage-html coverage/
```

### Code Quality

```bash
# Check coding standards
lando phpcs src/

# Auto-fix coding standards
lando phpcbf src/
```

---

## Common Tasks

### Add Blog Posts

1. Create `content/blog/` directory
2. Add posts: `content/blog/my-post.md`
3. Use category: `category = blog`
4. Rebuild: `lando php bin/console.php render:site`

### Add Images

1. Create `content/images/` directory
2. Add images
3. Reference in Markdown: `![Alt text](/images/photo.jpg)`
4. Images copy to `public/images/`

### Add CSS/JavaScript

1. Create `content/assets/` directory:
   ```
   content/assets/
   ├── css/
   │   └── custom.css
   └── js/
       └── custom.js
   ```

2. Reference in template (`templates/custom/base.html.twig`):
   ```html
   <link rel="stylesheet" href="/assets/css/custom.css">
   <script src="/assets/js/custom.js"></script>
   ```

3. Rebuild - assets copy to `public/assets/`

### Create Custom Template

1. Copy existing theme:
   ```bash
   cp -r templates/sample templates/mytheme
   ```

2. Edit `templates/mytheme/base.html.twig`

3. Update `.env`:
   ```
   TEMPLATE_NAME=mytheme
   ```

4. Rebuild:
   ```bash
   lando php bin/console.php render:site --clean
   ```

---

## Next Steps

### Learn More

- **[README.md](../README.md)** - Full documentation
- **[FEATURE_DEVELOPMENT.md](FEATURE_DEVELOPMENT.md)** - Create custom features
- **[CONFIGURATION.md](CONFIGURATION.md)** - Complete configuration reference
- **[documents/technical.md](../documents/technical.md)** - Architecture details

### Build Your Site

1. Plan your content structure
2. Create categories
3. Build navigation menu
4. Customize theme
5. Add content
6. Deploy to production

### Deploy

StaticForge generates static HTML. Deploy anywhere:

- **GitHub Pages**: Push `public/` to gh-pages branch
- **Netlify**: Connect repository, build with `php bin/console.php render:site`
- **S3**: Sync `public/` to S3 bucket
- **Traditional hosting**: Upload `public/` contents via FTP

---

## Troubleshooting

### Site doesn't rebuild

**Check**:
```bash
lando php bin/console.php render:site -v
```

Look for errors in output.

### Menu not showing

**Verify database**:
```bash
lando mysql lamp -e "SELECT * FROM menu_items;"
```

**Check template includes**:
```twig
{{ menu1|raw }}
```

### Template not found

**Check `.env`**:
```bash
TEMPLATE_NAME=sample
```

**Verify directory exists**:
```bash
ls templates/sample/base.html.twig
```

### Database connection failed

**Restart Lando**:
```bash
lando restart
```

**Check `.env` matches Lando config**:
```bash
DB_HOST=database  # Not localhost for Lando
```

---

## Getting Help

- **Documentation**: [docs/](.)
- **Examples**: [tests/fixtures/](../tests/fixtures/)
- **Issues**: [GitHub Issues](https://github.com/staticforge/staticforge/issues)

---

## What's Next?

You now have a working StaticForge site!

**Explore**:
1. Add more content
2. Customize your theme
3. Create categories and tags
4. Build custom features
5. Deploy your site

**Happy building!** 🚀
