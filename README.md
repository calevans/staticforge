# StaticForge

A PHP-based static site generator that processes content files through an event-driven pipeline to produce deployment-ready static websites.

## Installation

```bash
git clone <repository-url> my-site
cd my-site
lando composer install
```

## Quick Start

1. **Copy environment configuration:**
   ```bash
   cp .env.example .env
   ```

2. **Create your content directory structure:**
   ```bash
   mkdir -p content templates output
   ```

3. **Create a basic template:**
   Create `templates/base.html.twig`:
   ```html
   <!DOCTYPE html>
   <html>
   <head>
       <title>{{ title | default('My Site') }}</title>
   </head>
   <body>
       <main>
           {{ content | raw }}
       </main>
   </body>
   </html>
   ```

4. **Create your first content file:**
   Create `content/index.html`:
   ```html
   <!-- INI
   title: Welcome to My Site
   template: index
   -->
   <h1>Hello World!</h1>
   <p>This is my first StaticForge page.</p>
   ```

5. **Generate your site:**
   ```bash
   lando php bin/console.php render:site
   ```

Your static site will be generated in the `output/` directory.

## Directory Structure

- **`content/`** - Put your content files here (.html, .md, .pdf)
- **`templates/`** - Put your Twig template files here
- **`output/`** - Generated static site appears here
- **`.env`** - Configuration file (copy from `.env.example`)

## Content File Format

Content files support front matter in INI format within HTML comments:

```html
<!-- INI
title: Page Title
template: index
date: 2025-10-27
category: blog
tags: php, static-site, generator
-->
<h1>Your content here</h1>
<p>This content will be processed and rendered using the specified template.</p>
```

### Template Selection

- **`template: index`** → Uses `templates/sample/index.html.twig`
- **`template: landing`** → Uses `templates/sample/landing.html.twig`
- **No template specified** → Defaults to `templates/sample/base.html.twig`

The template directory (`sample`) is configured via the `TEMPLATE` setting in `.env`.

## Templates

Templates use Twig templating engine. Available variables:
- `{{ content }}` - The processed content
- `{{ title }}` - Page title from front matter
- Any custom variables from front matter

## Development

### Using Lando (Recommended)

```bash
# Start development environment
lando start

# Run tests
lando phpunit

# Check code style
lando phpcs src/

# Fix code style
lando phpcbf

# Run CLI commands
lando php bin/console.php list
```

### Requirements

- PHP 8.4+
- Twig templating engine
- Composer for dependency management

## Architecture

StaticForge uses an event-driven architecture with features that can be enabled/disabled:

- **Core Application**: Manages the processing pipeline
- **Event Manager**: Handles inter-feature communication
- **Feature Manager**: Loads and manages features
- **File Discovery**: Finds and categorizes content files
- **HTML Renderer**: Processes HTML content with Twig templates

## License

See LICENSE file for details.
