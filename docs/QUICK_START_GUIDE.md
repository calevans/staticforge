---
template: docs
menu: '1.1, 2.1'
category: docs
hero: assets/images/sf_quickstart_hero.jpg
---
# Quick Start Guide

Get your static site up and running in minutes! This guide will walk you through installing StaticForge and creating your first page.

## What You'll Need

Before you start, make sure you have:

- **PHP 8.4 or higher** installed on your system
- **Composer** (PHP's package manager)
- A text editor (VS Code, Sublime, or your favorite)
- A command line/terminal

> **Using Lando?** We've included a `.lando.yml` file for Lando users, but this guide focuses on the standard PHP setup. Check the project README for Lando-specific instructions.

---

## Installation

### Step 1: Install StaticForge

Create a new directory for your project and install StaticForge:

```bash
mkdir my-static-site
cd my-static-site
composer require eicc/staticforge
```

### Step 2: Initialize Your Project

Run the initialization command to set up the directory structure, configuration, and templates:

```bash
php vendor/bin/staticforge.php init
```

This command will:
- Create necessary directories (`content/`, `templates/`, `public/`, etc.)
- Create a default `.env` configuration file
- Install bundled templates
- Create a sample homepage

### Step 3: Configure Your Site (Optional)

The `init` command created a `.env` file with default settings that work out of the box. You can edit it to customize your site:

```bash
SITE_NAME="My Static Site"
SITE_TAGLINE="Built with ❤️ and PHP"
SITE_BASE_URL="http://localhost:8000"
TEMPLATE="staticforce"
```

**Key Settings:**
- **SITE_NAME**: Your site's name
- **SITE_BASE_URL**: Your site's URL (use `http://localhost:8000` for local development)
- **TEMPLATE**: The theme to use (`staticforce`, `sample`, `terminal`, or `vaulttech`)

### Step 4: Generate Your Site

Build your static site using the render command:

```bash
php vendor/bin/staticforge.php site:render
```

You'll see output confirming the generation. Your site is now in the `public/` directory!

### Step 5: View Your Site

Start the built-in development server to preview your site:

```bash
php vendor/bin/staticforge.php site:devserver
```

Open your browser to `http://localhost:8000` to see your new site!

---

## Creating Your First Page

StaticForge includes a starter homepage (`content/index.md`), but let's create a new page to see how it works.

### Step 1: Create a Content File

StaticForge looks for content in the `content/` directory. Create a new file called `content/hello.md`:

```markdown
---
title: "My First Page"
description: "Learning how to use StaticForge"
menu: 2
---

# Hello, World!

Welcome to my **StaticForge** site! This is my first page.

## What I'm Learning

- How to write content in Markdown
- How to set page metadata
- How to generate a static site

Pretty cool, right?
```

**Understanding the Structure:**

- **Lines 1-4** (between `---`) - This is the **frontmatter**. It contains metadata about your page using `key: "value"` YAML format.
- **Everything after** - This is your content, written in Markdown.

### Step 2: Regenerate Your Site

Now tell StaticForge to regenerate your site with the new page:

```bash
php bin/staticforge.php site:render
```

You'll see:

```
✓ Site generation complete!
  Generated 2 pages
```

StaticForge just:
1. Read your content files
2. Converted the Markdown to HTML
3. Applied your chosen template
4. Saved them in the `output/` directory

### Step 3: View Your Page

If you started the local server in Step 4:

1. Open your browser
2. Go to `http://localhost:8000/hello.html`
3. See your beautiful new page! 🎉

**Not using the local server?** Just open `output/hello.html` directly in your browser.

---

## Try Different Content Formats

StaticForge supports both Markdown and HTML. Let's try an HTML page.

Create `content/about.html`:

```html
<!-- INI
title = "About Me"
description = "Learn more about me"
-->

<h1>About Me</h1>

<p>I'm building a static site with StaticForge!</p>

<h2>Why StaticForge?</h2>
<ul>
  <li>It's fast</li>
  <li>It's simple</li>
  <li>It's built with PHP</li>
</ul>
```

**Notice the difference:**
- HTML files use `<!-- INI ... -->` for frontmatter (inside an HTML comment)
- The content is plain HTML instead of Markdown

Generate your site again:

```bash
php bin/staticforge.php site:render
```

Now visit `http://localhost:8000/about.html` to see it!

---

## Adding Images and Assets

You can add images, custom CSS, or JavaScript to your site by placing them in the `content/assets` directory.

1. Create the directory structure:
   ```bash
   mkdir -p content/assets/images
   ```

2. Add an image (e.g., `my-photo.jpg`) to `content/assets/images/`.

3. Reference it in your content:
   ```markdown
   ![My Photo](/assets/images/my-photo.jpg)
   ```

StaticForge will automatically copy everything from `content/assets` to `output/assets` when you build your site.

---

## Adding Pages to Your Menu

Want your pages to show up in the navigation menu? Add a `menu` value to the frontmatter:

```markdown
---
title = "Blog"
menu = 1.1
---

# My Blog

Check out my latest posts!
```

**How menu positioning works:**
- `1.1` - First item in menu 1
- `1.2` - Second item in menu 1
- `2.1` - First item in menu 2 (footer menu)
- `1.1.1` - First dropdown item under menu 1, position 1

**Want a page in multiple menus?** Just list them with commas:

```markdown
---
title = "Contact"
menu = 1.5, 2.3
---
```

This puts your Contact page in the main menu AND the footer menu!

Regenerate your site to see the menu update!

---

## Organizing with Categories

Keep related content together using categories:

```markdown
---
title = "My First Blog Post"
category = "blog"
---

# My First Blog Post

This is a blog post about StaticForge!
```

StaticForge automatically:
- Creates a `blog/` directory
- Moves the page to `output/blog/my-first-blog-post.html`
- Groups all blog posts together

---

## What's Next?

**Congratulations!** 🎉 You've created your first static site with StaticForge!


### Quick Tips

**Regenerate after every change:** StaticForge doesn't watch for changes. Run `php bin/staticforge.php site:render` after editing content or templates.

**Try different templates:** Change `TEMPLATE` in `.env` to try out different themes - staticforce (default), sample, terminal, or vaulttech.

**Keep frontmatter simple:** Only add metadata you actually need. At minimum, just set a `title`.

**Use Markdown for content:** Markdown is easier to write and read than HTML. Save HTML for special layouts.

### Need Help?

- Check out the other documentation pages
- Look at the example content in the `content/` directory
- Explore the templates in `templates/` to see how they work

Happy site building! 🚀

