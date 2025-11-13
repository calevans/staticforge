---
menu = 1.4, 2.4
name="Template Development"
template = "docs"
category = "docs"
---
# Template Development

Learn how to create and customize templates for your StaticForge site. Templates control how your content looks when rendered to HTML.

## Available Themes

StaticForge comes with four built-in themes:

- **`sample/`** - Clean, modern design (default)
- **`terminal/`** - Retro terminal-style theme
- **`vaulttech/`** - Vintage tech aesthetic
- **`staticforce/`** - Documentation-focused theme

You can switch themes by changing the `TEMPLATE` setting in your `.env` file. You can also override the value in the .env by specifying the `--template` option when running the `render:site` command.

---

## Template Structure

Every theme lives in the `templates/` directory. Here's what a typical theme looks like:

```
templates/
├── sample/                      # Theme name
│   ├── base.html.twig          # Main layout (required)
│   ├── index.html.twig         # Homepage (optional)
│   ├── category-index.html.twig # Category pages (optional)
│   ├── menu1.html.twig         # Primary menu (optional)
│   ├── menu2.html.twig         # Footer menu (optional)
│   ├── placeholder.jpg         # Default image (optional)
│   └── partials/               # Reusable components (optional)
│       └── category-file-item.html.twig
└── custom/                      # Your custom theme
    ├── base.html.twig
    └── ...
```

### Required Files

**`base.html.twig`** - This is the main layout template. Every theme must have this file. It's used by default when you don't specify a template in your content file's frontmatter.

### Optional Files

- **`index.html.twig`** - Special template for your homepage
- **`category-index.html.twig`** - Template for category listing pages
- **`menu1.html.twig`** - Override how the primary menu is rendered
- **`menu2.html.twig`** - Override how the secondary/footer menu is rendered
- **`placeholder.jpg`** - Default image (recommended size: 1200×630px)

### Creating Custom Templates

You can create as many template files as you need! For example, if you want a special landing page design:

1. Create `templates/yourtheme/landing.html.twig`
2. In your content file's frontmatter, add: `template = "landing"`
3. StaticForge will use `landing.html.twig` instead of `base.html.twig`

In most cases, `base.html.twig` has all of the basic parts you need. It is then extended by different templates for specific purposes.

---

## Template Variables

Your templates have access to lots of variables. Here's what's available:

### Site-Wide Variables

These come from your `.env` configuration:

```twig
{{ site_name }}          {# Your site's name (from SITE_NAME) #}
{{ site_base_url }}      {# Your site's URL (from SITE_BASE_URL) #}
{{ site_tagline }}       {# Your site's tagline (from SITE_TAGLINE) #}
```

### Page Content Variables

These are available on every page:

```twig
{{ title }}              {# The page title #}
{{ content }}            {# The rendered HTML content #}
```

### Metadata Variables

These come from your content file's frontmatter (if defined):

```twig
{{ description }}        {# Meta description for SEO #}
{{ author }}             {# Page author #}
{{ date }}               {# Publication date #}
{{ category }}           {# Page category #}
{{ tags }}               {# Array of tags #}
```

### Feature Data Variables

Features expose their data through the `features` object:

```twig
{# MenuBuilder - Pre-rendered menu HTML #}
{{ features.MenuBuilder.html.1|raw }}   {# Primary menu HTML #}
{{ features.MenuBuilder.html.2|raw }}   {# Secondary menu HTML #}

{# MenuBuilder - Raw menu data for custom rendering #}
{% if features.MenuBuilder.files[1] is defined %}
  <ul class="my-menu">
    {% for item in features.MenuBuilder.files[1] %}
      <li><a href="{{ item.url }}">{{ item.title }}</a></li>
    {% endfor %}
  </ul>
{% endif %}

{# ChapterNav - Previous/Next navigation #}
{% if features.ChapterNav is defined %}
  {{ features.ChapterNav.html|raw }}
{% endif %}
```

**Note:** The `|raw` filter tells Twig not to escape the HTML.

### Menu Data Structure

When using `features.MenuBuilder.files[X]`, each menu item contains:

- `title` - Page title
- `url` - Full URL with category prefix
- `file` - Source file path
- `position` - Menu position (e.g., "1.2")

Items are automatically sorted by position.

### Category Index Variables

On category listing pages (like `category-index.html.twig`), you get these extra variables:

```twig
{{ category }}           {# Category name #}
{{ total_files }}        {# How many files are in this category #}
{{ files }}              {# Array of file objects in this category #}
```

Each file object in the `files` array has:

```twig
{% for file in files %}
  {{ file.title }}       {# File's title #}
  {{ file.url }}         {# Link to the file #}
  {{ file.image }}       {# Hero/featured image URL #}
  {{ file.date }}        {# File's date #}
  {{ file.metadata }}    {# All metadata from the file #}
{% endfor %}
```

---

## Feature Data Reference

StaticForge features expose their data to templates through the `features` object. Here's what's available:

### MenuBuilder

```twig
{# Option 1: Pre-rendered HTML #}
{{ features.MenuBuilder.html.1|raw }}
{{ features.MenuBuilder.html.2|raw }}

{# Option 2: Raw data for custom markup #}
{% for item in features.MenuBuilder.files[1] %}
  <a href="{{ item.url }}">{{ item.title }}</a>
{% endfor %}
```

### ChapterNav

```twig
{# Previous/Next navigation #}
{{ features.ChapterNav.html|raw }}
```

### Tags

```twig
{# Tag cloud or list #}
{% if features.Tags is defined %}
  {% for tag, count in features.Tags.tags %}
    <a href="/tags/{{ tag }}.html">{{ tag }} ({{ count }})</a>
  {% endfor %}
{% endif %}
```

---

## Example: Creating a Basic Template

Here's a simple but complete template to get you started:

```twig
{# templates/custom/base.html.twig #}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  {# Page title with site name #}
  <title>{{ title|default('Untitled Page') }} - {{ site_name }}</title>

  {# Base URL for relative links #}
  {% if site_base_url %}
    <base href="{{ site_base_url }}">
  {% endif %}

  {# SEO meta tags #}
  {% if description %}
    <meta name="description" content="{{ description }}">
  {% endif %}

  {# Keywords from tags #}
  {% if tags is iterable and tags|length > 0 %}
    <meta name="keywords" content="{{ tags|join(', ') }}">
  {% endif %}

  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <header>
    <h1>{{ site_name }}</h1>
    <p>{{ site_tagline }}</p>

    <nav>
      {# Primary menu #}
      {{ features.MenuBuilder.html.1|raw }}
    </nav>
  </header>

  <main>
    {# This is where your page content appears #}
    {{ content|raw }}

    {# Chapter navigation if available #}
    {% if features.ChapterNav is defined %}
      {{ features.ChapterNav.html|raw }}
    {% endif %}
  </main>

  <footer>
    <p>&copy; 2025 {{ site_name }}. {{ site_tagline }}</p>

    {# Secondary menu in footer #}
    {{ features.MenuBuilder.html.2|raw }}
  </footer>
</body>
</html>
```

### Key Points:

- Use `|default('fallback')` to provide fallback values
- Use `|raw` when outputting HTML content
- Use `{% if variable %}` to check if something exists
- Check `features.FeatureName is defined` before accessing feature data
- Use `{% include 'filename.twig' %}` to include other template files

---

## Example: Customizing Menu Display

The menu templates receive pre-built HTML from the MenuBuilder feature. Here's how to display it:

```twig
{# templates/custom/menu1.html.twig #}

{# Check if menu exists before displaying #}
{% if features.MenuBuilder.html.1 is defined %}
  {# Output the menu HTML #}
  {{ features.MenuBuilder.html.1|raw }}
{% endif %}
```

The MenuBuilder automatically creates structured HTML menus from your content files' `menu` frontmatter values.

---

## Example: Category Index Page

Here's how to create a custom category listing page:

```twig
{# templates/custom/category-index.html.twig #}
{% extends "base.html.twig" %}

{% block content %}
<div class="category-page">
  <h1>{{ category }}</h1>
  <p>{{ total_files }} {{ total_files == 1 ? 'article' : 'articles' }} in this category</p>

  <div class="file-grid">
    {% for file in files %}
      <article class="file-card">
        {% if file.image %}
          <img src="{{ file.image }}" alt="{{ file.title }}">
        {% endif %}

        <h2><a href="{{ file.url }}">{{ file.title }}</a></h2>

        {% if file.metadata.description %}
          <p>{{ file.metadata.description }}</p>
        {% endif %}

        {% if file.date %}
          <time>{{ file.date }}</time>
        {% endif %}
      </article>
    {% endfor %}
  </div>
</div>
{% endblock %}
```

---

## Using Template Blocks

Twig's `{% block %}` feature lets you create flexible templates:

**Base template:**
```twig
{# base.html.twig #}
<body>
  <header>
    {% block header %}
      <h1>{{ site_name }}</h1>
    {% endblock %}
  </header>

  <main>
    {% block content %}
      {{ content|raw }}
    {% endblock %}
  </main>
</body>
```

**Child template that extends it:**
```twig
{# landing.html.twig #}
{% extends "base.html.twig" %}

{% block header %}
  {# This replaces the header block #}
  <div class="hero">
    <h1>Welcome!</h1>
  </div>
{% endblock %}

{# Content block uses the default from base.html.twig #}
```

---

## Tips and Best Practices

### Always Use Filters Appropriately

- **`|raw`** - Use for HTML content that should NOT be escaped
- **`|escape`** - Use for user input (Twig does this automatically)
- **`|default('fallback')`** - Provide fallback values
- **`|join(', ')`** - Convert arrays to comma-separated strings

### Check Before Using

Always check if a variable exists before using it:

```twig
{% if description %}
  <meta name="description" content="{{ description }}">
{% endif %}
```

### Handle Arrays Safely

Check if something is an array before looping:

```twig
{% if tags is iterable and tags|length > 0 %}
  {% for tag in tags %}
    <span>{{ tag }}</span>
  {% endfor %}
{% endif %}
```

### Organizing Large Templates

For complex templates, use partials:

```twig
{# base.html.twig #}
<header>
  {% include 'partials/navigation.html.twig' %}
</header>
```
