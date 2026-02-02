---
menu: '4.1.5'
title: 'The Face of the Operation: Templates'
description: 'Comprehensive guide to the Twig templating system in StaticForge, variables, inheritance, and layout design.'
template: docs
url: "https://calevans.com/staticforge/development/templates.html"
og_image: "Web design architectural blueprints, wireframes on a table, rulers and pencils, digital overlay of final design, creative studio atmosphere, --ar 16:9"
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
{# templates/mytemplate/base.html.twig #}
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
{# templates/mytemplate/standard_page.html.twig #}
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

## Templates

StaticForge uses the term **Templates** to refer to the collection of Twig files that define your site's look and feel.

### Built-in Templates

We include a few templates to get you started. You can find them in the `templates/` directory.

*   **`sample`**: A clean, modern default.
*   **`staticforce`**: The documentation template you are reading right now.

To switch templates, change the `template` setting in your `siteconfig.yaml` file (or `TEMPLATE` in `.env`).

```yaml
site:
  template: "staticforce"
```

### Installing Templates

You can find more StaticForge templates on Packagist. Installing them is as easy as running a composer command:

```bash
composer require vendor/template-name
```

**How it works:**
1.  Composer installs the package to your `vendor/` directory.
2.  The **StaticForge Installer** automatically copies the template files from `vendor/` to your `templates/` directory (e.g., `templates/template-name/`).
3.  **Safety First**: If a directory with that name already exists in `templates/`, the installer will **NOT** overwrite it.

**Why copy?**
We copy the files so you can customize them! Once a template is in your `templates/` directory, it is yours. You can edit the Twig files, CSS, and JS to your heart's content.

**Uninstalling:**
If you remove the package (`composer remove vendor/template-name`), the files in `vendor/` are removed, but your copy in `templates/` **remains**. This ensures you never lose your customizations.

### Developing & Distributing Templates

Want to share your design with the world? Creating a distributable StaticForge template is simple.

#### 1. Package Structure
A standard template package looks like this:

```text
my-template/
├── composer.json
└── templates/          # Contains your template files
    ├── assets/
    ├── base.html.twig
    ├── index.html.twig
    └── ...
```

#### 2. `composer.json` Configuration
To tell StaticForge this is a template, you must set the `type` to `staticforge-template`.

```json
{
    "name": "my-vendor/my-template",
    "description": "A beautiful template for StaticForge",
    "type": "staticforge-template",
    "license": "MIT",
    "require": {
        "eicc/staticforge-installer": "^1.0"
    }
}
```

**Advanced Configuration:**
If you need to store your templates in a different directory (not `templates/`), you can specify it in `extra`:

```json
{
    ...
    "extra": {
        "staticforge": {
            "template": {
                "name": "custom-template-name",  // Directory name in user's templates/ folder
                "source": "src/template-files"   // Source directory in your package
            }
        }
    }
}
```

#### 3. Publish
Submit your package to [Packagist.org](https://packagist.org). Use the keyword `staticforge-template` to help users find it!

---

[← Back to Documentation](index.html)
