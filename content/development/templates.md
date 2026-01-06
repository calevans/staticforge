---
menu: '4.1.5'
title: 'The Face of the Operation: Templates'
description: 'Comprehensive guide to the Twig templating system in StaticForge, variables, inheritance, and layout design.'
template: docs
---
# The Face of the Operation: Templates

If Features are the brains of StaticForge, **Templates** are the face. They determine what your users actually see.

We use **Twig**, a powerful templating engine for PHP. If you know HTML, you already know 90% of Twig. The other 10% is just "filling in the blanks."

---

## The "Master Slide" Concept (Inheritance)

The most powerful feature of Twig is **Inheritance**.

Think of it like a PowerPoint "Master Slide." You define the layout (Header, Footer, Sidebar) in *one place*, and every other page just fills in the content area.

### 1. The Master Layout (`base.html.twig`)

This file contains the HTML skeleton that is shared by every page on your site.

```twig
{# templates/mytheme/base.html.twig #}
<!DOCTYPE html>
<html>
<head>
    <title>{% block title %}My Site{% endblock %}</title>
    <link rel="stylesheet" href="{{ site_base_url }}/assets/css/style.css">
</head>
<body>
    <nav>
        <!-- Menu goes here -->
    </nav>

    <main>
        {# This is the "Slot" where child templates will inject content #}
        {% block body %}
        {% endblock %}
    </main>

    <footer>
        &copy; {{ "now"|date("Y") }} {{ site_name }}
    </footer>
</body>
</html>
```

### 2. The Child Page (`standard_page.html.twig`)

This file doesn't need to rewrite the `<html>` or `<body>` tags. It just says, "I want to use the Master Layout, and here is my content."

```twig
{# templates/mytheme/standard_page.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}{{ title }} - {{ site_name }}{% endblock %}

{% block body %}
    <h1>{{ title }}</h1>
    <div class="content">
        {{ content|raw }}
    </div>
{% endblock %}
```

---

## Variables: Filling in the Blanks

StaticForge passes a lot of data to your templates. You access it using double curly braces: `{{ variable_name }}`.

### The Essentials

*   `{{ content }}`: The HTML content of your Markdown file.
*   `{{ title }}`: The title from your Frontmatter.
*   `{{ site_base_url }}`: The URL of your site (e.g., `https://mysite.com`). **Always use this for assets!**
*   `{{ site_name }}`: The name of your site.

### The "Features" Array

Remember how features can expose data? It all lives in the `features` array.

*   `{{ features.MenuBuilder.html.1 }}`: The main menu HTML.
*   `{{ features.Tags.cloud }}`: The tag cloud HTML.

---

## Control Structures: Logic in HTML

Sometimes you need to show things only if they exist, or loop through a list.

### The `if` Statement

```twig
{% if image %}
    <img src="{{ site_base_url }}/assets/images/{{ image }}" alt="{{ title }}">
{% endif %}
```

### The `for` Loop

```twig
<ul>
{% for tag in tags %}
    <li><a href="{{ site_base_url }}/tags/{{ tag }}.html">{{ tag }}</a></li>
{% endfor %}
</ul>
```

---

## Asset Management (CSS & JS)

StaticForge includes an `AssetManager` that allows features (like Photo Galleries) to automatically inject the CSS and JavaScript they need.

To support this, your `base.html.twig` should include the following variables:

*   `{{ styles }}`: Outputs `<link>` tags for all registered stylesheets. Place this in `<head>`.
*   `{{ head_scripts }}`: Outputs `<script>` tags that must run in the head. Place this in `<head>`.
*   `{{ scripts }}`: Outputs `<script>` tags for the footer. Place this before `</body>`.

**Example `base.html.twig`:**

```twig
<head>
    ...
    {# Your main styles #}
    <link rel="stylesheet" href="{{ site_base_url }}/assets/css/style.css">

    {# Feature styles (e.g. Gallery CSS) #}
    {% if styles %}{{ styles|raw }}{% endif %}
    {% if head_scripts %}{{ head_scripts|raw }}{% endif %}
</head>
<body>
    ...
    {# Feature scripts (e.g. jQuery, Gallery JS) #}
    {% if scripts %}{{ scripts|raw }}{% endif %}
</body>
```

### Automatic Injection

If you forget to include these variables, StaticForge attempts to **automatically inject** them for you:
*   Styles and Head Scripts are injected before the closing `</head>` tag.
*   Footer Scripts are injected before the closing `</body>` tag.

*Note: While automatic injection works, it is recommended to explicitly place the variables in your template for better control over load order.*

---

## The "Asset Trap" (Critical Warning)

This is the #1 mistake people make.

Because StaticForge generates static HTML files that live in different folders (e.g., `/index.html` vs `/blog/my-post.html`), **relative paths do not work**.

❌ **WRONG:**
```html
<link rel="stylesheet" href="css/style.css">
```
*   Works on homepage.
*   Breaks on `/blog/post.html` (looks for `/blog/css/style.css`).

✅ **RIGHT:**
```twig
<link rel="stylesheet" href="{{ site_base_url }}/assets/css/style.css">
```
*   Always points to the root.

---

## Built-in Themes

We include a few themes to get you started. You can find them in `templates/`.

*   **`sample`**: A clean, modern default.
*   **`terminal`**: A retro, hacker-style theme.
*   **`staticforce`**: The documentation theme you are reading right now.

To switch themes, just change the `TEMPLATE` variable in your `.env` file.

```dotenv
TEMPLATE="terminal"
```

---

[← Back to Documentation](index.html)
