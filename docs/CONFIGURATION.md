---
title: 'Configuration Guide'
template: docs
menu: '1.2, 2.2'
category: docs
---
# Configuration Guide

Learn how to configure StaticForge to work exactly the way you want. This guide covers environment settings, directory structure, and built-in features.

---

## Environment Configuration

StaticForge uses a `.env` file for all configuration. This keeps your settings separate from your code and makes it easy to use different settings for development and production.

### Setting Up Your Configuration

1. Copy the example file:
   ```bash
   cp .env.example .env
   ```

2. Open `.env` in your text editor

3. Adjust the settings to match your needs

Here's what a typical `.env` file looks like:

```bash
# StaticForge Environment Configuration

# Site Information
SITE_NAME="My Static Site"
SITE_TAGLINE="Built with ❤️ and PHP"
SITE_BASE_URL="https://example.com"
TEMPLATE="staticforce"

# Directory Paths (relative to project root)
SOURCE_DIR="content"
OUTPUT_DIR="output"
TEMPLATE_DIR="templates"
FEATURES_DIR="src/Features"

# Optional Configuration
LOG_LEVEL="INFO"
LOG_FILE="logs/staticforge.log"

# SFTP Upload Configuration
UPLOAD_URL="https://www.mysite.com"
SFTP_HOST="example.com"
SFTP_PORT=22
SFTP_USERNAME="your-username"
SFTP_PASSWORD="your-password"
SFTP_REMOTE_PATH="/var/www/html"
```

---

## Required Settings

These settings **must** be present in your `.env` file or StaticForge won't run.

### SITE_NAME

**What it does:** The name of your website

**Where it appears:**
- Browser tab titles
- Template header areas
- Footer copyright notices

**Example:**
```bash
SITE_NAME="My Awesome Blog"
```

**Template usage:**
```twig
<title>{{ title }} - {{ site_name }}</title>
<h1>{{ site_name }}</h1>
```

---

### SITE_BASE_URL

**What it does:** The full URL where your site will be hosted

**Why it matters:**
- Sets the `<base>` tag in HTML for relative links
- Used for generating absolute URLs
- Critical for production deployments

**Examples:**
```bash
# For local development
SITE_BASE_URL="http://localhost:8000/"

# For production
SITE_BASE_URL="https://www.mysite.com/"
```

**Important:** Always include the trailing slash!

**Template usage:**
```twig
<base href="{{ site_base_url }}">
```

---

### SOURCE_DIR

**What it does:** Where StaticForge looks for your content files

**Default:** `content`

**Examples:**
```bash
SOURCE_DIR="content"      # Standard
SOURCE_DIR="src/pages"    # Custom location
SOURCE_DIR="docs"         # For documentation sites
```

**What goes here:** Your `.md` and `.html` files with frontmatter

---

### OUTPUT_DIR

**What it does:** Where StaticForge writes the generated HTML files

**Default:** `output` (common alternatives: `public`, `dist`, `build`)

**Examples:**
```bash
OUTPUT_DIR="output"   # Clean, simple
OUTPUT_DIR="public"   # Traditional web server convention
OUTPUT_DIR="dist"     # Common for build systems
```

**Important:** This is the directory you'll:
- Point your web server at
- Upload to your hosting provider
- Serve with `php -S localhost:8000 -t output/`

---

### TEMPLATE_DIR

**What it does:** Where your Twig templates live

**Default:** `templates`

**Example:**
```bash
TEMPLATE_DIR="templates"
```

**Directory structure:**
```
templates/
├── sample/          # Each subdirectory is a theme
├── terminal/
├── vaulttech/
└── staticforce/
```

You typically won't change this unless you have a custom project structure.

---

### TEMPLATE

**What it does:** Which theme to use from your TEMPLATE_DIR

**Default:** `sample`

**Built-in options:**
- `sample` - Clean, modern design
- `terminal` - Retro terminal aesthetic
- `vaulttech` - Vintage tech theme
- `staticforce` - Documentation-focused

**Example:**
```bash
TEMPLATE="terminal"
```

**How it works:** StaticForge looks for `templates/{TEMPLATE}/base.html.twig`

---

### FEATURES_DIR

**What it does:** Where StaticForge finds feature plugins

**Default:** `src/Features`

**Example:**
```bash
FEATURES_DIR="src/Features"
```

**What's in here:** Each subdirectory contains a feature like:
- `MenuBuilder/` - Builds navigation menus
- `Categories/` - Organizes content by category
- `MarkdownRenderer/` - Converts Markdown to HTML
- `HtmlRenderer/` - Processes HTML files

You won't typically change this setting.

---

## Optional Settings

These settings enhance StaticForge but aren't required to run.

### SITE_TAGLINE

**What it does:** A short phrase that describes your site

**Example:**
```bash
SITE_TAGLINE="Built with ❤️ and PHP"
```

**Template usage:**
```twig
<p>{{ site_tagline }}</p>
```

---

### LOG_LEVEL

**What it does:** Controls how much detail goes in the log file

**Options:**
- `DEBUG` - Everything (very verbose)
- `INFO` - Normal operations (recommended for development)
- `WARNING` - Only warnings and errors
- `ERROR` - Only errors
- `CRITICAL` - Only critical failures

**Example:**
```bash
LOG_LEVEL="INFO"
```

**Tip:** Use `DEBUG` when troubleshooting, `INFO` for development, and `WARNING` or `ERROR` for production.

---

### LOG_FILE

**What it does:** Where to save the log file

**Default:** `staticforge.log` (in the project root)

**Example:**
```bash
LOG_FILE="logs/staticforge.log"
```

**Note:** The directory must exist or StaticForge will create it.

---

## Directory Structure

Understanding how StaticForge organizes files helps you structure your content effectively.

### Input Structure (SOURCE_DIR)

```
content/
├── index.md              # Homepage (→ output/index.html)
├── about.md              # About page (→ output/about.html)
├── contact.html          # HTML page (→ output/contact.html)
├── blog/                 # Subdirectory
│   ├── post-1.md        # (→ output/blog/post-1.html)
│   └── post-2.md        # (→ output/blog/post-2.html)
└── docs/
    └── guide.md         # (→ output/docs/guide.html)
```

**Key points:**
- StaticForge recursively searches all subdirectories
- Directory structure IS preserved in output (unless using categories)
- Both `.md` and `.html` files are processed
- Files with `.md` extension become `.html` in output

---

### Output Structure (OUTPUT_DIR)

**Without categories:**
```
output/
├── index.html           # From content/index.md
├── about.html           # From content/about.md
├── blog/
│   ├── post-1.html
│   └── post-2.html
└── docs/
    └── guide.html
```

**With categories:**
```
output/
├── index.html
├── web-dev/             # Category subdirectory
│   ├── index.html      # Category index page
│   ├── article-1.html  # Has category = "web-dev"
│   └── article-2.html
└── tutorials/           # Another category
    ├── index.html
    └── beginner.html
```

---

## Built-in Features

StaticForge comes with several powerful features out of the box:

- **Markdown Renderer** - Converts `.md` files to HTML
- **HTML Renderer** - Processes `.html` files with templates
- **Menu Builder** - Automatically creates navigation menus
- **Categories** - Organizes content into subdirectories
- **Category Index** - Creates index pages for categories
- **Tags** - Extracts and manages content tags

For detailed information about each feature, including examples and template usage, see the **[Built-in Features Guide](FEATURES.html)**.

### Quick Feature Reference

**Using features in your content:**

```markdown
---
title = "My Page"
menu = 1.1              # Add to navigation menu
category = "blog"       # Organize into category
tags = ["php", "web"]   # Add tags
---
```

**Disabling features:**

Delete or rename the feature's directory:

```bash
# Disable a feature
rm -rf src/Features/Categories

# Or disable temporarily
mv src/Features/Categories src/Features/Categories.disabled
```

**Creating custom features:**

See the [Feature Development Guide](FEATURE_DEVELOPMENT.html) for step-by-step instructions.

---

## Production vs Development Settings

Here are recommended settings for different environments:

### Development `.env`

```bash
SITE_NAME="My Site (DEV)"
SITE_BASE_URL="http://localhost:8000/"
TEMPLATE="terminal"
OUTPUT_DIR="output"
LOG_LEVEL="DEBUG"
LOG_FILE="logs/dev.log"
```

### Production `.env`

```bash
SITE_NAME="My Awesome Site"
SITE_BASE_URL="https://www.mysite.com/"
TEMPLATE="staticforce"
OUTPUT_DIR="public"
LOG_LEVEL="ERROR"
LOG_FILE="logs/production.log"
```

**Tip:** Use version control to track your `.env.example` but add `.env` to `.gitignore` to keep environment-specific settings out of your repository.
