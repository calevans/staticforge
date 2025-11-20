---
title: 'Assets Management'
template: docs
menu: '1.3.6, 2.3.6'
category: docs
---
# Assets Management

**What it does:** Automatically copies static assets (CSS, JS, images) from both your template and your content directory to the public output directory.

**Events:** `POST_LOOP` (priority 100)

**How to use:**
1. Place template-specific files (dependencies like CSS frameworks, JS libs) in `templates/<template_name>/assets`.
2. Place content-specific files (custom CSS, hero images, custom JS) in `content/assets`.

**Conflict Resolution:** Files in `content/assets` will overwrite files with the same name in `templates/<template_name>/assets`. This allows you to override template styles or scripts on a per-site basis.

## Directory Structure

### Template Assets
If your template is named `oom`, organize your files like this:

```
templates/
  oom/
    assets/        <-- Template dependencies
      css/
        style.css
      js/
        app.js
      images/
        card_bg.png
    index.html.twig
```

### Content Assets
For site-specific assets:

```
content/
  assets/          <-- Site-specific overrides and images
    css/
      custom.css
    images/
      hero.jpg
  index.md
```

## Output Structure

When StaticForge builds your site, it merges both directories into `public/assets/`.

```
public/
  assets/
    css/
      style.css    (from template)
      custom.css   (from content)
    js/
      app.js       (from template)
    images/
      card_bg.png  (from template)
      hero.jpg     (from content)
  index.html
```

## Referencing Assets in Templates

Since the files are flattened into `public/assets/`, you should reference them in your Twig templates using the absolute path `/assets/...`.

```html
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/custom.css">
<script src="/assets/js/app.js"></script>
<img src="/assets/images/hero.jpg" alt="Hero Image">
```
