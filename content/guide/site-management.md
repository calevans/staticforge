---
title: 'Site Management & Deployment'
description: 'Guide to generating, managing, and deploying your StaticForge site.'
template: docs
menu: '2.2.1'
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
php vendor/bin/staticforge.php site:render
```

**Need a fresh start?**
Sometimes caches get stale or old files linger. Use the `--clean` flag to wipe the slate clean before rebuilding. This is especially useful for production builds.

```bash
php vendor/bin/staticforge.php site:render --clean
```

**Testing a new look?**
If you're experimenting with different templates, you can switch them on the fly without changing your configuration files.

```bash
php vendor/bin/staticforge.php site:render --template=experimental-template
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

### Host Key Verification

`site:upload` verifies the server's SSH host key on every connection using trust-on-first-use: the first time you connect to `SFTP_HOST`, it accepts and records the key the server presents (next to your private key if `SFTP_PRIVATE_KEY_PATH` is set, otherwise in `~/.ssh/known_hosts`). Every connection after that compares against the recorded key and **refuses to connect** if it ever changes — this catches a server impersonating your host after the fact, though a first-connect man-in-the-middle is still possible if `SFTP_HOST` itself is wrong, the same trade-off as `ssh`'s own `StrictHostKeyChecking=accept-new`.

If you already know the server's host key (or want to skip trust-on-first-use entirely), pin it explicitly:

```bash
SFTP_HOST_KEY="ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAA..."
```

### Deploying

Once configured, going live is a single command.

```bash
php vendor/bin/staticforge.php site:upload
```

If you need to deploy to a staging server first, you can override the URL on the fly:

```bash
php vendor/bin/staticforge.php site:upload --url="https://staging.mysite.com"
```

> **Note:** The first time you run this, it will upload everything. Subsequent runs will only upload changes, making updates lightning fast.
*   **Safety**: It *only* touches files it knows about. It will not delete your manually uploaded `.htaccess` or images folders unless they were part of a previous build.

#### Troubleshooting
*   **Connection Failed**: Check hostname, port (22), and firewall rules.
*   **Permission Denied**: Ensure the SFTP user has write permissions to `SFTP_REMOTE_PATH`.
*   **SSH Keys**: Ensure your private key file has strict permissions (`chmod 600`).
*   **Host key changed since last connection**: uploads refuse to proceed rather than silently trust a new key. If you rebuilt or replaced the server, delete the stale entry from the recorded known-hosts file (or `SFTP_HOST_KEY` if you're pinning explicitly) and reconnect to trust the new key.
