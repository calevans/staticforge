---
title: 'Site Management & Deployment'
description: 'Guide to generating, managing, and deploying your StaticForge site.'
template: docs
menu: '2.2.1'
url: "https://calevans.com/staticforge/guide/site-management.html"
og_image: "Digital construction site, futuristic scaffolding around a glowing website hologram, easy deployment button, tech blue theme, --ar 16:9"
hero: assets/images/site-management-hero.jpg
---

# Site Management & Deployment

Building your site shouldn't be a chore. StaticForge gives you powerful tools to generate your pages locally and seamless ways to push them to the world.

Whether you are iterating on a single blog post or launching a major update, we've got you covered.

## Generating Your Site

At its heart, StaticForge is a compiler. It takes your raw content and templates and turns them into a beautiful, static website.

### The Heavy Lifter: `site:render`

When you're ready to see your whole site, this is the command you'll reach for. It processes everything: markdown files, assets, template logic, and more.

```bash
# Build everything
php bin/staticforge.php site:render
```

**Need a fresh start?**
Sometimes caches get stale or old files linger. Use the `--clean` flag to wipe the slate clean before rebuilding. This is especially useful for production builds.

```bash
php bin/staticforge.php site:render --clean
```

**Testing a new look?**
If you're experimenting with different themes, you can switch them on the fly without changing your configuration files.

```bash
php bin/staticforge.php site:render --template=experimental-theme
```

### The Surgical Tool: `site:page`

Waiting for the whole site to build when you just fixed a typo in one file is painful. The `site:page` command lets you render just what you're working on.

This is perfect for rapid development loops.

```bash
# Fix that typo in about.md
php bin/staticforge.php site:page content/about.md

# Update all your blog posts at once
php bin/staticforge.php site:page "content/blog/*.md"
```

---

## Going Live

So you've built a site you're proud of. Now what?

StaticForge includes a built-in "Smart Uploader." It's not just a dumb file copy; it understands your site.

### Why not just use FileZilla?

You certainly can! But `site:upload` does three critical things for you:

1.  **Production Build**: It automatically re-renders your site using your *production* URL (defined in `.env`), ensuring no `localhost` links leak into the wild.
2.  **Smart Sync**: It checks what's changed. It uploads new files and—crucially—**deletes old ones** that are no longer part of your site.
3.  **Safety**: It tracks its own files using a manifest, so it won't accidentally delete other files on your server (like your server logs or other applications).

### Configuration

Before you deploy, tell StaticForge where to go. Open your `.env` file and set up your credentials.

**Pro Tip:** We highly recommend using SSH Keys for authentication. It's more secure and means you don't have to put passwords in text files.

```bash
# Where is this site going to live?
UPLOAD_URL="https://www.mysite.com"

# Server Details
SFTP_HOST="example.com"
SFTP_USERNAME="your-username"
SFTP_REMOTE_PATH="/var/www/html"

# Authentication (Choose one)
SFTP_PRIVATE_KEY_PATH="/home/user/.ssh/id_rsa"
# OR
# SFTP_PASSWORD="your-password"
```

### Deploying

Once configured, going live is a single command.

```bash
php bin/staticforge.php site:upload
```

If you need to deploy to a staging server first, you can override the URL on the fly:

```bash
php bin/staticforge.php site:upload --url="https://staging.mysite.com"
```

> **Note:** The first time you run this, it will upload everything. Subsequent runs will only upload changes, making updates lightning fast.
*   **Safety**: It *only* touches files it knows about. It will not delete your manually uploaded `.htaccess` or images folders unless they were part of a previous build.

#### Troubleshooting
*   **Connection Failed**: Check hostname, port (22), and firewall rules.
*   **Permission Denied**: Ensure the SFTP user has write permissions to `SFTP_REMOTE_PATH`.
*   **SSH Keys**: Ensure your private key file has strict permissions (`chmod 600`).
