# [StaticForge](https://calevans.com/staticforge)

A PHP-based static site generator that processes content files through an event-driven pipeline to produce deployment-ready static websites.

Copyright 2025, Cal Evans<br />
License: MIT<br />

## Documentation

Full documentation is available at **[https://calevans.com/staticforge](https://calevans.com/staticforge)**.

## Installation

Install StaticForge using Composer:

```bash
composer require eicc/staticforge
vendor/bin/staticforge-install-templates.php
```

The second command installs the default templates without overwriting any existing templates.

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

2. **Optional: Create `siteconfig.yaml`:**
   For static menus and site-wide settings.

3. **Generate your site:**
   ```bash
   php bin/staticforge.php site:render
   ```

4. **View your site:**
   Open `output/index.html` in your browser.

5. **Add more content:**
   Create `.md` or `.html` files in the `content/` directory and regenerate.

## Development

### Development Commands

```bash
# Run tests
phpunit

# Check code style
phpcs src/

# Fix code style
phpcbf

# Run CLI commands
php bin/staticforge.php list
```

### Requirements

- PHP 8.4+
- Twig templating engine
- Composer for dependency management

## License

See LICENSE file for details.
