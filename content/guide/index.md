---
title: User Guide
description: 'Landing page for the StaticForge User Guide. Tutorials and reference material for building static sites.'
template: docs
menu: '2.1'
hero: assets/images/user-guide-hero.jpg
url: "https://calevans.com/staticforge/guide/index.html"
---
# User Guide

Welcome to the StaticForge User Guide! We're excited to help you build something awesome. Whether you're crafting a personal blog, a portfolio, or a full-blown documentation site, you're in the right place. This guide is designed to take you from "Hello World" to a fully deployed site, step by step.

---

## Core Concepts

Before we jump into the commands, let's take a quick look at how StaticForge thinks. It's actually pretty simple:

1.  **You Write Content**: You create Markdown (`.md`) files in the `content/` directory. This is where your words live.
2.  **You Configure**: You tweak the look and feel in `siteconfig.yaml` and handle local settings in `.env`.
3.  **We Build**: You run `site:render`, and StaticForge combines your content with templates.
4.  **We Publish**: The result is a complete, static HTML website in the `public/` directory, ready to be hosted anywhere.

---

## Getting Started

New here? No problem. Here is the best path to get up and running quickly:

### 1. [Quick Start Guide](quick-start.html)
**Start here.** We'll walk you through installation, project initialization, and building your first page in under 5 minutes. It's easier than you think.

### 2. [Configuration](configuration.html)
Once you're up and running, learn how to set up your local environment. We'll cover the `.env` file, which handles the sensitive stuff like API keys and local paths.

### 3. [Site Configuration](site-config.html)
Now, let's give your site some personality. The `siteconfig.yaml` file is where you define your site's identity, set up navigation menus, and tweak feature settings.

---

## Creating Content

This is where the magic happens. Once your site is set up, you'll spend most of your time here.

### [Frontmatter Guide](frontmatter.html)
Every content file in StaticForge starts with a metadata block called "Frontmatter". It might sound technical, but it's just a way to tell StaticForge about your page. We'll show you how to:
*   Set page titles and descriptions
*   Choose specific templates
*   Organize pages into categories and tags
*   Control exactly where your page appears in the menu

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
