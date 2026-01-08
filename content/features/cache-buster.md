---
title: 'Cache Buster'
description: 'Documentation for the Cache Buster feature, providing automated asset versioning for cache invalidation.'
template: docs
menu: '3.1.2'
url: "https://calevans.com/staticforge/features/cache-buster.html"
og_image: "Breaking a metal chain link, cache refresh icon, speed motion blur, high velocity digital data stream, freeze frame action, --ar 16:9"
---
# Cache Buster

**What it does:** Automatically appends a unique build timestamp to your CSS file references to ensure browsers always load the latest version of your styles.

**Events:** `CREATE` (priority 10)

**How it works:**
1. During the `CREATE` event, the feature generates a unique `build_id` based on the current timestamp.
2. This `build_id` is stored in the container, along with a `cache_buster` variable (formatted as `sfcb=TIMESTAMP`).
3. In your Twig templates, you can append `?{{ cache_buster }}` to your asset URLs.

**Example Usage:**

In your `base.html.twig`:

```twig
<link rel="stylesheet" href="assets/css/main.css?{{ cache_buster }}">
```

**Result:**

The rendered HTML will look like:

```html
<link rel="stylesheet" href="assets/css/main.css?sfcb=1732134567">
```

This forces the browser to treat the file as a new resource whenever you rebuild your site, preventing stale cache issues.
