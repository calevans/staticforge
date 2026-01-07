---
title: 'System Commands'
description: 'Reference guide for StaticForge system and utility commands.'
template: docs
menu: '2.2.3'
url: "https://calevans.com/staticforge/guide/commands.html"
og_image: "Hacker terminal screen with green command line interface, typing fast, system control, matrix background, code flowing, --ar 16:9"
hero: assets/images/system-commands-hero.jpg
---

# System Commands

While `site:render` builds your site and `site:upload` deploys it, StaticForge also includes a set of system utilities to help you manage your installation.

Think of these as the dashboard for your engine. They help you see what's running under the hood.

---

## Managing Features

StaticForge is built on a plugin architecture called "Features." Everything from RSS feeds to Sitemap generation is a feature.

### Checking Feature Status

Sometimes you need to know exactly what is running. Did you successfully disable the Sitemap? Is the CacheBuster active? The `system:features` command gives you a live look at your configuration.

```bash
php bin/staticforge.php system:features
```

This will output a clean table showing every available feature and whether it is currently **Enabled** or **Disabled** based on your `siteconfig.yaml`.

```text
+--------------------+----------+
| Feature Name       | Status   |
+--------------------+----------+
| CacheBuster        | Enabled  |
| Categories         | Enabled  |
| Sitemap            | Disabled |
| ...                | ...      |
+--------------------+----------+
```

**Pro Tip:** If you are old school, you can also use `system:plugins`. It does the exact same thing.




