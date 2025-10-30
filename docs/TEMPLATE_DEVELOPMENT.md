---
menu=1.3
name="Template Development"
template = "docs"
---
# Templates

Available templates:
- `sample/` - Basic template
- `terminal/` - Terminal theme
- `vaulttech/` - Retro theme
- `staticforce/` - Documentation theme

### Template Directory Structure

```
templates/
├── sample/                  # Theme name
│   ├── base.html.twig      # Base template (required)
│   ├── index.html.twig     # Homepage template (optional)
│   ├── menu1.html.twig     # Menu template (optional)
│   ├── menu2.html.twig     # Secondary menu (optional)
│   └── placeholder.jpg     # Default image (optional)
└── custom/                  # Your custom theme
    ├── base.html.twig
    └── ...
```

**Required Files**:
- `base.html.twig`: Main layout template
This is the core template that all pages extend. If no template is specified in the content frontmatter, this template is used by default.

**Optional Files**:
- `index.html.twig`: Homepage override
- `menu1.html.twig`: Primary menu template
- `menu2.html.twig`: Secondary menu template
- `menuX.html.twig`: Secondary menu template
- `placeholder.jpg`: Default placeholder image (1200x630px)

You can add as many twig files as you like to create your site. You reference them by specifying the `template` key in the frontmatter of your content files. For example, to use landing_page.html.twig, you would add this.

```
template="landing_page
```
---

## Template Configuration

### Twig Template Variables

All templates have access to these variables:

#### Global Variables

```twig
{{ site_name }}
{{ site_base_url }}
{{ site_tagline }}
{{ title }}
{{ content }}
```

#### Metadata Variables

From content frontmatter:

```twig
{{ title }}               {# Page title #}
{{ description }}         {# Meta description #}
{{ keywords }}            {# Meta keywords #}
{{ author }}              {# Author name #}
{{ date }}                {# Publication date #}
{{ category }}            {# Page category #}
{{ tags }}                {# Array of tags #}
```

#### Menu Variables

```twig
{{ menu1 }}               {# Primary menu HTML #}
{{ menu2 }}               {# Secondary menu HTML #}
```

#### Category Variables

On category index pages:

```twig
{{ category }}            {# Category name #}
{{ total_files }}         {# Number of files in category #}
{{ files }}               {# Array of file objects #}
{{ category_files }}      {# Alternative access to files array #}
```

#### Individual File Variables
When looping through files in a category:

```twig
  {{ file.title }}        {# File title #}
  {{ file.url }}          {# File URL #}
  {{ file.image }}        {# Hero image URL #}
  {{ file.date }}         {# File date #}
  {{ file.metadata }}     {# Full metadata object #}
```
### Template Example

```twig
{# templates/custom/base.html.twig #}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ title|default('Untitled') }} - {{ site.title }}</title>

  {% if description %}
  <meta name="description" content="{{ description }}">
  {% endif %}

  {% if keywords %}
  <meta name="keywords" content="{{ keywords is iterable ? keywords|join(', ') : keywords }}">
  {% endif %}

  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <header>
    <nav>
      {{ menu1|raw }}
    </nav>
  </header>

  <main>
    {{ content|raw }}
  </main>

  <footer>
    <p>&copy; 2024 {{ site.title }}</p>
  </footer>
</body>
</html>
```

### Custom Menu Template

Override default menu rendering by creating `menu1.html.twig`:

```twig
{# templates/custom/menu1.html.twig #}
<ul class="nav">
{% for item in menu_items %}
  <li class="{{ item.has_children ? 'has-dropdown' : '' }}">
    <a href="{{ item.url }}">{{ item.label }}</a>
    {% if item.has_children %}
      <ul class="dropdown">
      {% for child in item.children %}
        <li><a href="{{ child.url }}">{{ child.label }}</a></li>
      {% endfor %}
      </ul>
    {% endif %}
  </li>
{% endfor %}
</ul>
```

---

## Next Steps
- [QuickStart Guide](QUICK_START_GUIDE.html)
- [Configuration Guide](CONFIGURATION.html)
- Template Development
- [Feature Development](FEATURE_DEVELOPMENT.html)
- [Core Events](EVENTS.html)
- [Additional Commands](ADDITIONAL_COMMANDS.html)
