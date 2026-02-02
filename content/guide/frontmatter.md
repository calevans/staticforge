---
title: 'Frontmatter Guide'
description: 'How to use YAML frontmatter in StaticForge to define metadata, templates, and variables for your content.'
template: docs
menu: '2.1.5'
url: "https://calevans.com/staticforge/guide/frontmatter.html"
og_image: "YAML data structure block floating, metadata header, structured text configuration, file properties, abstract data cube, --ar 16:9"
---
# Frontmatter Guide

Frontmatter is the metadata block at the top of your content files. It tells StaticForge how to handle your content, what template to use, and provides data to your templates.

## The Basics

Every content file in StaticForge (Markdown or HTML) starts with a frontmatter block. This block is written in YAML and is enclosed by three dashes `---`.

```markdown
---
title: "My Awesome Page"
description: "This is a description for SEO"
template: "standard_page"
---
```

### Required Fields

While you can put anything you want in your frontmatter, there are a few fields that StaticForge looks for:

*   **`title`**: The title of your page. This is usually used in the `<title>` tag and `<h1>` of your template.
*   **`template`**: (Optional) The name of the Twig template to use (without `.html.twig`). If omitted, it defaults to `base` or whatever your template uses as default.
*   **`menu`**: (Optional) If you want this page to appear in a menu, specify the position here (e.g., `'1.0'`, `'2.1'`).

## It's Just Data

The most powerful thing about frontmatter is that **it is just data**. You are not limited to the standard fields. You can add any custom data using standard YAML formatting, and it will be available in your Twig templates.

For example, if you are using an AI image generator like Midjourney to create hero images for your blog posts, you might want to store the prompt you used right in the file for future reference.

```markdown
---
title: "The Future of AI"
midjourneyPrompt: "A futuristic city with flying cars, cyberpunk style, neon lights --ar 16:9"
---
```

In your template, you can then access this data. If the data is not used by any feature or template, it is simply ignored.

```twig
{% if midjourneyPrompt %}
  <!-- Image generated with: {{ midjourneyPrompt }} -->
{% endif %}
```

## Feature Configuration

Many StaticForge features rely on frontmatter to work. Here are a few common examples:

### Tags & Categories
Organize your content by adding tags or categories.

```markdown
---
category: "blog"
tags:
  - php
  - static-site
  - tutorial
---
```

### SEO Metadata
Control how your page looks in search engines and social media.

```markdown
---
description: "A detailed guide to StaticForge frontmatter."
image: "/assets/images/frontmatter-guide.jpg"
author: "Cal Evans"
---
```

### Draft Status
Prevent a page from being published by marking it as a draft.

```markdown
---
draft: true
---
```

## HTML Files

You can also use frontmatter in `.html` files! Just wrap the YAML block in an HTML comment:

```html
<!--
---
title: "Custom HTML Page"
template: "landing"
---
-->
<h1>Welcome</h1>
```

This allows you to use StaticForge's powerful templating system even when you need to write raw HTML.
