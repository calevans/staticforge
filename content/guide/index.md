---
title: User Guide
description: 'Landing page for the StaticForge User Guide. Tutorials and reference material for building static sites.'
template: docs
menu: '2.1'
hero: assets/images/user-guide-hero.jpg
url: "https://calevans.com/staticforge/guide/index.html"
og_image: "Open glowing user manual, guide book leading the way, path to knowledge, library background, --ar 16:9"
---
# User Guide

Welcome to the StaticForge User Guide. This section will walk you through everything from installation to deployment.

## What is a "Page"?

In StaticForge, a "page" is just a text file. You don't need a database. You write your content in a simple file, add a little metadata at the top, and save it.

Here is an example of what a blog post looks like:

```yaml
---
title: My First Post
date: 2023-10-01
description: "This is a summary of my post"
template: default
---

# Hello World

This is the content of my page. I can use **bold** text, *italics*, and lists.

*   Item 1
*   Item 2
```

The part between the `---` lines is the **Frontmatter** (metadata). The rest is your content. That's it!

If you are new to writing in this format, check out the original [Markdown Syntax Guide](https://daringfireball.net/projects/markdown/syntax) by John Gruber. It covers everything you need to know.

## Contents

*   [Quick Start](quick-start.html) - Installation and building your first page.
*   [Local Configuration](configuration.html) - Setting up your environment (`.env`).
*   [Site Configuration](site-config.html) - Configuring your site (`siteconfig.yaml`).
*   [System Commands](commands.html) - Utility and reference commands.
*   [Frontmatter Guide](frontmatter.html) - How to add metadata to your content.
*   [CLI Commands](cli-commands.html) - Reference for rendering, auditing, and system commands.*   Control exactly where your page appears in the menu

---

## Building & Deploying

Ready to show the world?

### [Command Reference](commands.html)
StaticForge is a CLI-first tool, which means you have a lot of power at your fingertips. This reference page documents every command available to you, including:
*   `site:render`: The command that builds your site.
*   `site:devserver`: A built-in server to preview your work locally.
*   `site:upload`: The magic command that deploys your site to production via SFTP.

---

## Next Steps

Once you've mastered the basics, you can start exploring the really cool stuff:

*   **[Features](../features/index.html)**: Discover built-in superpowers like Search, Forms, and SEO generators.
*   **[Development](../development/index.html)**: Want to go deeper? Dive into the code to create custom templates, build your own features, or even contribute to the core.
