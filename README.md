# StaticForge

A PHP-based static site generator that processes content files through an event-driven pipeline to produce deployment-ready static websites.

## Documentation

- **[Quick Start Guide](docs/QUICK_START_GUIDE.md)** - Get running in 5 minutes
- **[Configuration Guide](docs/CONFIGURATION.md)** - All configuration options
- **[Feature Development](docs/FEATURE_DEVELOPMENT.md)** - Create custom features
- **[Bootstrap & Initialization](docs/BOOTSTRAP.md)** - How bootstrap works
- **[Technical Documentation](documents/technical.md)** - Architecture details
- **[Design Documentation](documents/design.md)** - Design decisions

## Installation

### Quick Install (Recommended)

Install StaticForge with a single command using Composer:

```bash
composer create-project eicc/staticforge my-site
cd my-site
```

That's it! Your site is ready to use. StaticForge automatically:
- Creates a `.env` configuration file
- Sets up the `output/` directory
- Includes starter content and 4 pre-built templates

### Library Installation

To add StaticForge to an existing project:

```bash
composer require eicc/staticforge
vendor/bin/install-templates.php
```

This will install the default templates without overwriting any existing templates.

### Development Installation

If you want to contribute to StaticForge development:

```bash
git clone https://github.com/calevans/staticforge.git my-site
cd my-site
composer install
cp .env.example .env
```

## Quick Start

Your StaticForge installation comes ready to use! Here's how to get started:

1. **Edit your site configuration:**
   Open `.env` and customize your site name, tagline, and other settings.

2. **Generate your site:**
   ```bash
   php bin/console.php render:site
   ```

3. **View your site:**
   Open `output/index.html` in your browser.

4. **Add more content:**
   Create `.md` or `.html` files in the `content/` directory and regenerate.

### Available Templates

StaticForge includes 4 pre-built templates. Switch between them by editing the `TEMPLATE` variable in `.env`:

- **sample** - Clean, minimal design (default)
- **staticforce** - Professional documentation theme
- **terminal** - Retro terminal-inspired design
- **vaulttech** - Fallout-inspired post-apocalyptic theme

You can delete unused templates from the `templates/` directory to reduce clutter.

### Example: Creating a New Page

Create `content/about.md`:
```markdown
---
title = "About Us"
menu = 2
---

# About Us

This is our about page!
```

Then regenerate your site:
```bash
php bin/console.php render:site
```

## Deployment

Generate and upload your site in two commands:

```bash
# Generate static files
php bin/console.php render:site

# Upload to your server via SFTP
php bin/console.php site:upload
```

See [Additional Commands](docs/ADDITIONAL_COMMANDS.md) for SFTP configuration details.

## Command Reference

### Generate Your Site

```bash
php bin/console.php render:site
```

Options:
- `--clean` - Remove output directory before generation

### Upload to Server

```bash
php bin/console.php site:upload
```

See [Additional Commands](docs/ADDITIONAL_COMMANDS.md) for SFTP configuration.

## Directory Structure

- **`content/`** - Put your content files here (.html, .md, .pdf)
- **`templates/`** - Put your Twig template files here
- **`output/`** - Generated static site appears here
- **`.env`** - Configuration file (copy from `.env.example`)

## Content File Format

Content files support front matter in INI format within HTML comments:

```html
<!-- INI
title = "Page Title"
template = "index"
date = "2025-10-27"
category = "blog"
tags = "php, static-site, generator"
-->
<h1>Your content here</h1>
<p>This content will be processed and rendered using the specified template.</p>
```

### Template Selection

- **`template = "index"`** → Uses `templates/sample/index.html.twig`
- **`template = "landing"`** → Uses `templates/sample/landing.html.twig`
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
title = "Home"
menu = 1
---
```

**HTML files:**
```html
<!-- INI
title = "Home"
menu = 1
-->
```

### Menu Positioning

**Simple menu items:**
```ini
menu = 1        # First item in menu 1
menu = 2        # First item in menu 2
```

**Dropdown menus:**
```ini
menu = 2.0      # Dropdown title (not clickable)
menu = 2.1      # First item in dropdown
menu = 2.2      # Second item in dropdown
```

**Three-level menus:**
```ini
menu = 1.2.0    # Nested dropdown title
menu = 1.2.1    # First item in nested dropdown
menu = 1.2.2    # Second item in nested dropdown
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

### Production Build

Full site generation with clean output:
```bash
php bin/console.php render:site --clean -v
```

### Deployment to Production

StaticForge includes built-in SFTP upload for easy deployment:

```bash
# 1. Generate your site
php bin/console.php site:render --clean

# 2. Upload to production server
php bin/console.php site:upload
```

Configure SFTP in your `.env` file:
```bash
SFTP_HOST="example.com"
SFTP_USERNAME="your-username"
SFTP_PASSWORD="your-password"  # OR use SSH key
SFTP_REMOTE_PATH="/var/www/html"
```

For detailed SFTP configuration and usage, see [docs/ADDITIONAL_COMMANDS.md](docs/ADDITIONAL_COMMANDS.md).

### Debugging

Use verbose mode to troubleshoot issues:
```bash
php bin/console.php render:site -vvv  # Debug level verbosity
```

---

## Performance Notes

- `render:site` processes all files and runs all features (menus, tags, categories)
- Verbose mode adds minimal overhead
- Average processing time shown in verbose output helps identify bottlenecks

---

## Exit Codes

- `0` - Success
- `1` - Failure (check error messages and logs)

---

## Tips

1. **Enable verbose for debugging**: `-v` shows what's happening
2. **Clean builds**: Use `--clean` when changing templates or structure
3. **Template switching**: Test different themes without modifying `.env`

---

## Integration with Build Tools

You can integrate these commands into your build pipeline:

**package.json scripts:**
```json
{
  "scripts": {
    "build": "php bin/console.php render:site --clean",
    "dev": "php bin/console.php render:site -v",
    "deploy": "php bin/console.php site:upload"
  }
}
```

**Makefile:**
```makefile
.PHONY: build dev deploy

build:
	php bin/console.php render:site --clean

dev:
	php bin/console.php render:site -v

deploy:
	php bin/console.php render:site --clean && php bin/console.php site:upload
```

**Shell script:**
```bash
#!/bin/bash
# deploy.sh
php bin/console.php site:render --clean
php bin/console.php site:upload
```

**Alternative with rsync:**
```bash
#!/bin/bash
# deploy-rsync.sh
php bin/console.php site:render --clean
if [ $? -eq 0 ]; then
    rsync -avz public/ user@server:/var/www/html/
fi
```
