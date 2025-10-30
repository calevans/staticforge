---
template = "docs"
menu = 1.1
---
# Quick Start Guide

Get StaticForge running in 2 minutes.

This guide assumes that your environment has PHP and Composer installed.


> **Note**: A `.lando.yml` file is provided if you prefer to use Lando for development. However, this guide does not cover Lando setup or any of the scripts included to make it easy.

---

## Installation

### 1. Clone the repository

To get started, clone the StaticForge repository into a directory for you new project.

```bash
git clone https://github.com/calevans/staticforge.git mysite.com
cd mysite.com
```

### 2. Install the dependencies

```bash
composer install
```
### 3. Create the environment file

```bash
cp .env.example .env
```
Now open the `.env` file and adjust any settings as needed. The defaults should work fine for most development setups.

### 4. Start the development server (Optional)

If you want to see your progress, use PHP's built-in web server to serve the `public/` directory:

```bash
php -S localhost:8000 -t public/
```

---

## Your First Page
Now let's create your first page. By default, StaticForge looks for content in the `content/` directory. You cn contol thay by setting the directory name you want in the `.env` file.

### 1. Create Content

Create `content/hello.md`:

```markdown
---
title = "Hello World"
---
# Hello World!

Welcome to StaticForge.
```

The firt setion of the file is called "front matter" and uses `key=value` syntax to define metadata about the page. In this example, the content below it is written in Markdown.


### 2. Generate

```bash
php bin/console.php render:site
```

This will render your site and put it inthe directory specified in the `.env` file (default is `public/`).

### 3. View

Visit `http://localhost:8000/hello.html`

**Congratulations!**<br /> You have created and rendered your first StaticForge page.



---

## Next Steps
- QuickStart Guide
- [Configuration Guide](CONFIGURATION.html)
- [Template Development](TEMPLATE_DEVELOPMENT.html)
- [Feature Development](FEATURE_DEVELOPMENT.html)
- [Core Events](EVENTS.html)
- [Additional Commands](ADDITIONAL_COMMANDS.html)
