---
title: "Welcome to StaticForge"
template: "sample"
menu: 1
---

# Welcome to StaticForge

Congratulations! You've successfully installed StaticForge, a PHP-based static site generator with an extensible, event-driven architecture.

## What is StaticForge?

StaticForge transforms your content files (Markdown, HTML) into a complete static website that can be deployed anywhere. It's built by PHP developers, for PHP developers, making it easy to extend and customize.

## Quick Start

Your site is already set up and ready to generate! Here's what to do next:

1. **Edit your site configuration** - Open `.env` and customize your site settings
2. **Add your content** - Create `.md` or `.html` files in the `content/` directory
3. **Generate your site** - Run: `php bin/console.php render:site`
4. **View your site** - Open files in the `output/` directory

## Available Templates

StaticForge comes with 4 pre-built templates. Switch between them by editing the `TEMPLATE` variable in your `.env` file:

- **sample** (current) - Clean, minimal design
- **staticforce** - Professional documentation theme
- **terminal** - Retro terminal-inspired design
- **vaulttech** - Fallout-inspired post-apocalyptic theme

## Next Steps

- Read the [Quick Start Guide](docs/QUICK_START_GUIDE.md) for detailed instructions
- Learn about [Features](docs/FEATURES.md) like menus, tags, and categories
- Explore [Template Development](docs/TEMPLATE_DEVELOPMENT.md) to create custom themes
- Check out [Feature Development](docs/FEATURE_DEVELOPMENT.md) to extend StaticForge

## Documentation

All documentation is in the `docs/` directory:

- **[Quick Start Guide](docs/QUICK_START_GUIDE.md)** - Get up and running
- **[Configuration Guide](docs/CONFIGURATION.md)** - All configuration options
- **[Features](docs/FEATURES.md)** - Built-in features explained
- **[Template Development](docs/TEMPLATE_DEVELOPMENT.md)** - Create custom templates
- **[Feature Development](docs/FEATURE_DEVELOPMENT.md)** - Build custom features

## Get Help

- **Documentation**: Check the `docs/` folder
- **Examples**: See `docs/examples/` for sample content
- **Issues**: Report bugs on GitHub

Happy site building! 🚀
