# StaticForge - PHP Static Site Generator

StaticForge is now available as a Composer library! This makes it easy to install, update, and use in your projects.

## Installation

```bash
composer require eicc/staticforge
```

## Quick Start

1. **Initialize a new project:**
   ```bash
   vendor/bin/staticforge init
   ```

2. **Edit your configuration:**
   Edit the generated `.env` file with your site settings.

3. **Add content:**
   Create Markdown files in the `content/` directory.

4. **Build your site:**
   ```bash
   vendor/bin/staticforge render:site
   ```

Your static site will be generated in the `public/` directory.

## Project Structure

After running `staticforge init`, you'll have:

```
your-project/
├── .env                 # Site configuration
├── content/            # Your content files (Markdown, HTML)
│   └── index.md        # Sample homepage
├── templates/          # Twig templates (copied from StaticForge)
│   ├── staticforce/    # Modern documentation theme
│   ├── sample/         # Basic theme
│   ├── terminal/       # Terminal-style theme
│   └── vaulttech/      # Retro gaming theme
├── public/             # Generated static site
├── config/             # Additional configuration files
└── logs/               # Build logs
```

## Available Commands

- `staticforge init` - Initialize a new project structure
- `staticforge render:site` - Build your static site
- `staticforge upload:site` - Upload site via SFTP (requires configuration)

## Configuration

The `.env` file controls your site settings:

```env
# Site Information
SITE_NAME="My StaticForge Site"
SITE_BASE_URL="/"
SITE_DESCRIPTION="A static site built with StaticForge"

# Paths
SOURCE_DIR="content"
TEMPLATE_DIR="templates"
OUTPUT_DIR="public"

# Template Selection
DEFAULT_TEMPLATE="staticforce"

# Features
ENABLE_FEATURES="MarkdownRenderer,HtmlRenderer,MenuBuilder,Categories,Tags,ChapterNav"
```

## Features

- **Markdown Support** - CommonMark with table extensions
- **Twig Templating** - Powerful template engine
- **Multiple Themes** - Choose from built-in themes or create your own
- **Automatic Navigation** - Menu generation from content structure
- **Categories & Tags** - Organize your content
- **Chapter Navigation** - Previous/Next links for documentation
- **Event-Driven Architecture** - Extensible with custom features

## Updating StaticForge

Since StaticForge is now a Composer dependency, updates are simple:

```bash
composer update eicc/staticforge
```

## Development

For StaticForge development, clone the repository and use:

```bash
git clone https://github.com/calevans/staticforge.git
cd staticforge
composer install
php bin/staticforge.php --version
```

## Migration from create-project

If you previously used `composer create-project eicc/staticforge`, you can migrate:

1. Create a new directory for your site
2. Run `composer require eicc/staticforge`
3. Run `vendor/bin/staticforge init`
4. Copy your content from the old project to the new `content/` directory
5. Copy any custom templates to the new `templates/` directory
6. Update your `.env` file with your previous configuration

---

**Happy building!** 🚀