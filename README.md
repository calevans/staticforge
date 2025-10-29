# StaticForge

A PHP-based static site generator that processes content files through an event-driven pipeline to produce deployment-ready static websites.

## Documentation

- **[Quick Start Guide](docs/QUICKSTART.md)** - Get running in 5 minutes
- **[Configuration Guide](docs/CONFIGURATION.md)** - All configuration options
- **[Feature Development](docs/FEATURE_DEVELOPMENT.md)** - Create custom features
- **[Technical Documentation](documents/technical.md)** - Architecture details
- **[Design Documentation](documents/design.md)** - Design decisions

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

### Theme Structure

Each theme directory in `templates/` should contain:

- `base.html.twig` - Base template with site layout
- `index.html.twig` - Home page template
- `menu1.html.twig` - Primary navigation menu
- `menu2.html.twig` - Secondary/footer navigation menu
- `partials/` - Reusable template components
- `placeholder.jpg` - (Optional) Default thumbnail for category index pages

### Placeholder Images for Category Index Pages

The `placeholder.jpg` file is used by the CategoryIndex feature when generating category index pages.

**When is it used?**

When a page in a category doesn't have a hero image (first `<img>` tag in the content), the CategoryIndex feature will use `placeholder.jpg` as the thumbnail.

**Specifications:**
- **Recommended size**: 300x200 pixels (matches the thumbnail size)
- **Format**: JPEG
- **Location**: `templates/{theme_name}/placeholder.jpg`

**Image Handling Behavior:**

1. If a category page has a hero image:
   - Local images are resized to 300x200 and cached in `public/images/`
   - External images are downloaded, resized to 300x200, and cached in `public/images/cache/`

2. If no hero image exists:
   - CategoryIndex looks for `templates/{theme_name}/placeholder.jpg`
   - If found, uses it as the thumbnail
   - If not found, generates a gray 300x200 placeholder automatically

**Customization:**

Create a custom `placeholder.jpg` for your theme to match your site's aesthetic:
- Brand colors with your logo
- A "no image" icon in your theme style
- A terminal-style placeholder for terminal theme

The placeholder will be visible in category index pages whenever content doesn't include an image.

## Menu System

StaticForge includes a powerful menu builder that generates semantic HTML menus from your content files.

### Adding Pages to Menus

Add a `menu:` entry to your content's frontmatter:

**Markdown files:**
```markdown
---
title: Home
menu: 1
---
```

**HTML files:**
```html
<!-- INI
title: Home
menu: 1
-->
```

### Menu Positioning

**Simple menu items:**
```yaml
menu: 1        # First item in menu 1
menu: 2        # First item in menu 2
```

**Dropdown menus:**
```yaml
menu: 2.0      # Dropdown title (not clickable)
menu: 2.1      # First item in dropdown
menu: 2.2      # Second item in dropdown
```

**Three-level menus:**
```yaml
menu: 1.2.0    # Nested dropdown title
menu: 1.2.1    # First item in nested dropdown
menu: 1.2.2    # Second item in nested dropdown
```

*Note: Only 3 levels are supported.*

### Generated HTML Structure

**Simple menu:**
```html
<ul class="menu menu-1">
  <li class="menu-1">
    <a href="/home.html">Home</a>
  </li>
</ul>
```

**Dropdown menu:**
```html
<ul class="menu menu-2">
  <li class="dropdown menu-2-0">
    <span class="dropdown-title">Services</span>
    <ul class="dropdown-menu menu-2-submenu">
      <li class="menu-2-1">
        <a href="/web-dev.html">Web Development</a>
      </li>
      <li class="menu-2-2">
        <a href="/mobile.html">Mobile Apps</a>
      </li>
    </ul>
  </li>
</ul>
```

### CSS Class Reference

**Top-level classes:**
- `menu` - Base class for all menus
- `menu-{N}` - Specific menu number (e.g., `menu-1`, `menu-2`)

**Dropdown classes:**
- `dropdown` - Dropdown container
- `menu-{N}-{P}` - Dropdown at position P in menu N
- `dropdown-title` - Non-clickable dropdown label
- `dropdown-menu` - Container for dropdown items
- `menu-{N}-submenu` - Submenu within menu N

**Item classes:**
- `menu-{N}` - Simple menu item in menu N
- `menu-{N}-{P}` - Item at position P within menu N

### Using Menus in Templates

**In PHP:**
```php
$features = $container->getVariable('features');
$menuHtml = $features['MenuBuilder']['html'][1]; // Get menu 1
```

**In Twig (future):**
```twig
{{ features.MenuBuilder.html.1|raw }}
```

### Example CSS for Dropdown Menus

The included templates (terminal, sample, vaulttech) already have full dropdown support. If you're creating a custom template, here's a minimal example:

```css
/* Base menu styles - works in nav, footer, sidebar, anywhere */
.menu {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    gap: 20px;
}

/* Dropdown container */
.menu .dropdown {
    position: relative;
}

/* Dropdown trigger (non-clickable title) */
.menu .dropdown-title {
    cursor: pointer;
    padding: 10px 15px;
    display: inline-block;
}

/* Dropdown panel (hidden by default) */
.menu .dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background: #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    min-width: 200px;
    z-index: 1000;
}

/* Show dropdown on hover */
.menu .dropdown:hover .dropdown-menu {
    display: block;
}

/* Dropdown menu items */
.menu .dropdown-menu li {
    display: block;
}

.menu .dropdown-menu a {
    display: block;
    padding: 10px 15px;
}
```

**Key points:**
- Scope all dropdown styles to `.menu` so they work anywhere
- Use `.menu .dropdown:hover .dropdown-menu` for hover functionality
- Set `position: relative` on `.dropdown` and `position: absolute` on `.dropdown-menu`
- Add `z-index: 1000` to ensure dropdowns appear above other content


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

---

# CLI Commands

This section describes the available CLI commands for StaticForge.

## Available Commands

### render:site

Generate the complete static site from all content files.

**Usage:**
```bash
php bin/console.php render:site [options]
```

**Options:**
- `-c, --clean` - Clean output directory before generation
- `-t, --template=TEMPLATE` - Override the template theme (e.g., sample, terminal, vaulttech)
- `-v, --verbose` - Enable verbose output with detailed progress and statistics

**Examples:**

Basic site generation:
```bash
php bin/console.php render:site
```

Clean build with verbose output:
```bash
php bin/console.php render:site --clean -v
```

Use different template:
```bash
php bin/console.php render:site --template=vaulttech
```

**Verbose Output Includes:**
- Configuration settings (content dir, output dir, template, etc.)
- Event pipeline steps
- Files processed count
- Active features list
- Generation time and performance metrics

---

### render:page

Render a single file or files matching a pattern. Useful for quick testing or partial site updates.

**Usage:**
```bash
php bin/console.php render:page [options] [--] <pattern>
```

**Arguments:**
- `pattern` - File path or glob pattern to render (e.g., `content/about.md` or `content/*.md`)

**Options:**
- `-c, --clean` - Clean output directory before generation
- `-t, --template=TEMPLATE` - Override the template theme
- `-v, --verbose` - Enable verbose output with file list

**Examples:**

Render a single file:
```bash
php bin/console.php render:page content/about.md
```

Render all markdown files:
```bash
php bin/console.php render:page "content/*.md"
```

Render all HTML files with verbose output:
```bash
php bin/console.php render:page "content/*.html" -v
```

Render specific category with custom template:
```bash
php bin/console.php render:page "content/business/*" --template=terminal
```

**Pattern Resolution:**
- Absolute paths work directly
- Relative paths are resolved from content directory
- Glob patterns (`*`, `?`) are supported
- File extension can be omitted (will try `.md` and `.html`)

**Verbose Output Includes:**
- Pattern resolution details
- List of matched files
- File processing count

---

## Global Options

All commands support these Symfony Console options:

- `-h, --help` - Display help for the command
- `-q, --quiet` - Do not output any message
- `-V, --version` - Display application version
- `--ansi|--no-ansi` - Force (or disable) ANSI output
- `-n, --no-interaction` - Do not ask any interactive question
- `-v|vv|vvv, --verbose` - Increase verbosity (normal, verbose, debug)

---

## Use Cases

### Development Workflow

Quick iteration on a single page:
```bash
# Edit content/about.md
php bin/console.php render:page content/about.md
# Check public/about.html
```

### Content Preview

Preview all pages in a category before full build:
```bash
php bin/console.php render:page "content/blog/*.md" -v
```

### Template Testing

Test a new template on a subset of pages:
```bash
php bin/console.php render:page "content/index.md" --template=experimental
```

### Production Build

Full site generation with clean output:
```bash
php bin/console.php render:site --clean -v
```

### Debugging

Use verbose mode to troubleshoot issues:
```bash
php bin/console.php render:site -vvv  # Debug level verbosity
```

---

## Performance Notes

- `render:page` only processes matched files, making it faster for testing
- `render:site` processes all files and runs all features (menus, tags, categories)
- Both commands support the same template and clean options
- Verbose mode adds minimal overhead
- Average processing time shown in verbose output helps identify bottlenecks

---

## Exit Codes

- `0` - Success
- `1` - Failure (check error messages and logs)

---

## Tips

1. **Use patterns for batch operations**: `content/blog/*.md` renders all blog posts
2. **Test before full build**: Use `render:page` to verify changes quickly
3. **Enable verbose for debugging**: `-v` shows what's happening
4. **Clean builds**: Use `--clean` when changing templates or structure
5. **Template switching**: Test different themes without modifying `.env`

---

## Integration with Build Tools

You can integrate these commands into your build pipeline:

**package.json scripts:**
```json
{
  "scripts": {
    "build": "php bin/console.php render:site --clean",
    "dev": "php bin/console.php render:site -v",
    "preview": "php bin/console.php render:page 'content/*.md'"
  }
}
```

**Makefile:**
```makefile
.PHONY: build dev preview

build:
	php bin/console.php render:site --clean

dev:
	php bin/console.php render:site -v

preview:
	php bin/console.php render:page "content/*.md"
```

**Shell script:**
```bash
#!/bin/bash
# deploy.sh
php bin/console.php render:site --clean --template=production
if [ $? -eq 0 ]; then
    rsync -avz public/ user@server:/var/www/html/
fi
```
