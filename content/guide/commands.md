---
title: 'System Commands'
description: 'Reference guide for StaticForge system and utility commands.'
template: docs
menu: '2.2.4'
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

Sometimes you need to know exactly what is running. Did you successfully disable the Sitemap? Is the CacheBuster active? The `feature:list` command gives you a live look at your configuration.

```bash
php bin/staticforge.php feature:list
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

### Migrating a Feature to 3.0

If you have a custom Feature (in-tree or an external package) still written against the pre-3.0 event contract, `feature:migrate` converts it automatically — dry run by default, so nothing changes until you pass `--write`:

```bash
php bin/staticforge.php feature:migrate MyFeatureName
php bin/staticforge.php feature:migrate MyFeatureName --write
php bin/staticforge.php feature:migrate --all
```

See [Migrating to 3.0](../migrating-to-3-0.html) for what it converts, what it leaves as a TODO for you to finish by hand, and how to verify the result.

