---
title: 'Quick Start Guide'
description: 'Get up and running with StaticForge in minutes. Installation, project creation, and basic usage.'
template: docs
menu: '2.1.1'
hero: assets/images/sf_quickstart_hero.jpg
url: "https://calevans.com/staticforge/guide/quick-start.html"
og_image: "Sprinter at starting block ready to run, stopwatch, fast forward icon, motion blur, speed lines, getting started concept, --ar 16:9"
---
# Quick Start Guide

Ready to build something fast? You're in the right place. This guide will take you from zero to a fully functional static site in just a few minutes. We like to call it **The 2-Minute Install**.

## What You'll Need

Just a few things before we start:

- **PHP 8.4 or higher** installed on your system
- **Composer** (PHP's package manager)
- A text editor (VS Code, Sublime, or your favorite)
- A command line/terminal

> **Using Lando?** We've included a `.lando.yml` file for Lando users, but this guide focuses on the standard PHP setup. Check the project README for Lando-specific instructions.

---

## Installation

### Step 1: Get the Files

First, let's create a home for your new project and pull in StaticForge via Composer.

```bash
mkdir my-static-site
cd my-static-site
composer require eicc/staticforge
```

### Step 2: Initialize Your Project

Now, let's set the stage. Run the initialization command to set up your directory structure, configuration, and templates:

```bash
php vendor/bin/staticforge.php site:init
```

You will see output similar to this:

```text
StaticForge Initialization
==========================

[OK] Created directory: content
[OK] Created directory: templates
[OK] Created directory: public
[OK] Created configuration: .env
[OK] Created configuration: siteconfig.yaml
[OK] Installed default template: staticforce
[OK] Created sample content: content/index.md

Success! Your project is ready.
```

This command does the heavy lifting for you:
- Creates the necessary directories (`content/`, `templates/`, `public/`, etc.)
- Copies example configuration files (`.env.example` to `.env`, `siteconfig.yaml.example` to `siteconfig.yaml`)
- Installs bundled templates
- Creates a sample homepage so you're not starting with a blank screen

### Step 3: Configure Your Site (Optional)

Authentication secrets and environment-specific settings (like URLs) live in your `.env` file, but your site identity lives in `siteconfig.yaml`.

**1. Environment Settings (.env):**
Use this for things that change between environments (Dev vs Production).

```bash
SITE_BASE_URL="http://localhost:8000"
TEMPLATE="staticforce"
```

**2. Site Identity (siteconfig.yaml):**
Use this for public information about your site. Open `siteconfig.yaml` and edit:

```yaml
site:
  name: "My Static Site"
  tagline: "Built with ❤️ and PHP"
  description: "A super fast site built with StaticForge"
```

### Step 4: Install a Template (Optional)

Want a different look? You can install new templates via Composer:

```bash
composer require vendor/template-name
```

StaticForge automatically copies the new template into your `templates/` directory. You can then activate it by setting `TEMPLATE="template-name"` in your `.env` file.

### Step 5: Generate Your Site

This is the moment of truth. Build your static site using the render command:

```bash
php vendor/bin/staticforge.php site:render
```

You'll see output confirming the generation:

```text
Building Site...
================

[+] Discovered 5 files
[+] Processing content/index.md... OK
[+] Processing content/about.md... OK
[+] Generating Sitemap... OK
[+] Generating RSS Feed... OK

[OK] Site generation complete! (0.42s)
```

Your site is now ready in the `public/` directory!

### Step 6: See It Live

Let's take a look at what you built. Start the built-in development server:

```bash
php vendor/bin/staticforge.php site:devserver
```

Open your browser to `http://localhost:8000` to see your new site!

### Step 7: Go Live

Built something you're proud of? It's time to share it.

StaticForge includes a smart deployment tool (`site:upload`) that handles everything for you. It optimizes your build for production and syncs only the changes to your server.

👉 **[See the Deployment Guide](site-management.html#going-live)** to configure your server and launch your site.

---

## Creating Your First Page

StaticForge includes a starter homepage (`content/index.md`), but let's make your mark by creating a new page.

### Step 1: Create a Content File

StaticForge looks for content in the `content/` directory. Create a new file called `content/hello.md`:

```markdown
---
title: "My First Page"
description: "Learning how to use StaticForge"
menu: '2.1.1'
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

- **Lines 1-5** (between `---`) - This is the **frontmatter**. It contains metadata about your page using `key: "value"` YAML format.
- **Everything after** - This is your content, written in Markdown.

### Step 2: Regenerate Your Site

Now tell StaticForge to regenerate your site with the new page:

```bash
php vendor/bin/staticforge.php site:render
```

You'll see:

```text
✓ Site generation complete!
  Generated 2 pages
```

StaticForge just:
1. Read your content files
2. Converted the Markdown to HTML
3. Applied your chosen template
4. Saved them in the `public/` directory

### Step 3: View Your Page

If you started the local server in Step 5:

1. Open your browser
2. Go to `http://localhost:8000/hello.html`
3. See your beautiful new page! 🎉

**Not using the local server?** Just open `public/hello.html` directly in your browser.

---

## Try Different Content Formats

StaticForge supports both Markdown and HTML. Let's try an HTML page for when you need more control.

Create `content/about.html`:

```html
<!--
---
title: "About Me"
description: "Learn more about me"
---
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
- HTML files use `<!-- ... -->` for frontmatter (inside an HTML comment)
- The content is plain HTML instead of Markdown

Generate your site again:

```bash
php vendor/bin/staticforge.php site:render
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
   ![My Photo](/assets/images/sf_quickstart_hero.jpg)
   ```

StaticForge will automatically copy everything from `content/assets` to `public/assets` when you build your site.

---

## Adding Pages to Your Menu

Want your pages to show up in the navigation menu? Add a `menu` value to the frontmatter:

```markdown
---
title: "Blog"
menu: 1.1
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
title: "Contact"
menu: 1.5, 2.3
---
```

This puts your Contact page in the main menu AND the footer menu!

Regenerate your site to see the menu update!

---

## Organizing with Categories

Keep related content together using categories:

```markdown
---
title: "My First Blog Post"
category: "blog"
---

# My First Blog Post

This is a blog post about StaticForge!
```

StaticForge automatically:
- Creates a `blog/` directory
- Moves the page to `public/blog/my-first-blog-post.html`
- Groups all blog posts together

---

## What's Next?

**Congratulations!** 🎉 You've created your first static site with StaticForge!


### Quick Tips

**Regenerate after every change:** StaticForge doesn't watch for changes. Run `php vendor/bin/staticforge.php site:render` after editing content or templates.

**Try different templates:** Change `TEMPLATE` in `.env` to try out different templates - staticforce (default) or sample.

**Keep frontmatter simple:** Only add metadata you actually need. At minimum, just set a `title`.

**Use Markdown for content:** Markdown is easier to write and read than HTML. Save HTML for special layouts.

### Need Help?

- Check out the other documentation pages
- Look at the example content in the `content/` directory
- Explore the templates in `templates/` to see how they work

Happy site building! 🚀
