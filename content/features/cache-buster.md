---
title: 'Cache Buster'
description: 'Documentation for the Cache Buster feature, providing automated asset versioning for cache invalidation.'
template: docs
menu: '3.1.2'
---
# Cache Buster

**What it does:** Automatically appends a unique build timestamp to your CSS file references to ensure browsers always load the latest version of your styles.

**Events:** `CREATE` (priority 10)

**How it works:**
1. During the `CREATE` event, the feature generates a unique `build_id` based on the current timestamp.
2. This `build_id` is stored in the container.
3. In your Twig templates, you can append `?v={{ build_id }}` to your asset URLs.

**Example Usage:**

In your `base.html.twig`:

```twig
<link rel="stylesheet" href="assets/css/main.css?v={{ build_id }}">
```

**Result:**

The rendered HTML will look like:

```html
<link rel="stylesheet" href="assets/css/main.css?v=1732134567">
```

This forces the browser to treat the file as a new resource whenever you rebuild your site, preventing stale cache issues.
