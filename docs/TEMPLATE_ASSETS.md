---
title: 'Template Assets'
template: docs
menu: '1.3.6, 2.3.6'
category: docs
---
# Template Assets

**What it does:** Automatically copies static assets (CSS, JS, images) from your template to the public output directory.

**Events:** `POST_LOOP` (priority 100)

**How to use:** Place your static files in an `assets` folder within your template directory.

## Directory Structure

If your template is named `oom`, organize your files like this:

```
templates/
  oom/
    assets/        <-- Put files here
      css/
        style.css
      js/
        app.js
      images/
        logo.png
    index.html.twig
```

## Output Structure

When StaticForge builds your site, it will copy the contents of `assets/` directly to `public/assets/`.

```
public/
  assets/          <-- Files are copied here
    css/
      style.css
    js/
      app.js
    images/
      logo.png
  index.html
```

## Referencing Assets in Templates

Since the files are flattened into `public/assets/`, you should reference them in your Twig templates using the absolute path `/assets/...`.

```html
<link rel="stylesheet" href="/assets/css/style.css">
<script src="/assets/js/app.js"></script>
<img src="/assets/images/logo.png" alt="Logo">
```
