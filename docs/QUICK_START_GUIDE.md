---
template = "docs"
menu = 1.1, 2.1
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

### Step 1: Get StaticForge

Clone the repository into your project directory:

```bash
git clone https://github.com/calevans/staticforge.git my-awesome-site
cd my-awesome-site
```

Replace `my-awesome-site` with whatever you want to name your project!

### Step 2: Install Dependencies

StaticForge needs a few PHP libraries to work. Install them with Composer:

```bash
composer install
```

This will download everything StaticForge needs to generate your site.

### Step 3: Configure Your Site

Create your configuration file by copying the example:

```bash
cp .env.example .env
```

Now open `.env` in your text editor. You'll see settings like:

```bash
SITE_NAME="My Static Site"
SITE_TAGLINE="Built with ❤️ and PHP"
SITE_BASE_URL="https://example.com"
TEMPLATE="sample"
OUTPUT_DIR="output"
```

**What to change:**

- **SITE_NAME** - Your site's name (shows in browser tabs and templates)
- **SITE_TAGLINE** - A catchy tagline for your site
- **SITE_BASE_URL** - Your site's URL (use `http://localhost:8000/` for local development)
- **TEMPLATE** - Which theme to use (`sample`, `terminal`, `vaulttech`, or `staticforce`)
- **OUTPUT_DIR** - Where to put generated files (default is `output`)

The defaults work great for testing, so you can leave them as-is for now!

### Step 4: Start a Local Server (Optional)

Want to preview your site while you work? Start PHP's built-in web server:

```bash
php -S localhost:8000 -t output/
```

**Important:** Make sure the directory name matches your `OUTPUT_DIR` setting in `.env`!

Leave this running in your terminal, and your site will be available at `http://localhost:8000`.

---

## Creating Your First Page

Let's create a simple page to see how StaticForge works.

### Step 1: Create a Content File

StaticForge looks for content in the `content/` directory. Create a new file called `content/hello.md`:

```markdown
---
title = "My First Page"
description = "Learning how to use StaticForge"
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

- **Lines 1-4** (between `---`) - This is the **frontmatter**. It contains metadata about your page using `key = "value"` format.
- **Everything after** - This is your content, written in Markdown.

### Step 2: Generate Your Site

Now tell StaticForge to turn your content into HTML:

```bash
php bin/console.php render:site
```

You'll see:

```
StaticForge Site Generator

Starting site generation...

✅ Site generation completed successfully!
Time: 0.08s
```

StaticForge just:
1. Read your content file
2. Converted the Markdown to HTML
3. Applied your chosen template
4. Saved it as `output/hello.html` (or `public/hello.html` depending on your OUTPUT_DIR)

### Step 3: View Your Page

If you started the local server in Step 4 of Installation:

1. Open your browser
2. Go to `http://localhost:8000/hello.html`
3. See your beautiful new page! 🎉

**Not using the local server?** Just open `output/hello.html` (or `public/hello.html`) directly in your browser.

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
php bin/console.php render:site
```

Now visit `http://localhost:8000/about.html` to see it!

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

**Regenerate after every change:** StaticForge doesn't watch for changes. Run `php bin/console.php render:site` after editing content or templates.

**Try different templates:** Change `TEMPLATE` in `.env` to try out different themes - sample, terminal, vaulttech, or staticforce.

**Keep frontmatter simple:** Only add metadata you actually need. At minimum, just set a `title`.

**Use Markdown for content:** Markdown is easier to write and read than HTML. Save HTML for special layouts.

### Need Help?

- Check out the other documentation pages
- Look at the example content in the `content/` directory
- Explore the templates in `templates/` to see how they work

Happy site building! 🚀

