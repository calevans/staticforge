---
title: 'Category Index Pages'
template: docs
menu: '3.1.3'
---
# Category Index Pages

**What it does:** Creates index pages that list all files in each category

**Events:**
- `POST_GLOB` (priority 200)
- `PRE_RENDER` (priority 150)
- `POST_RENDER` (priority 50)
- `POST_LOOP` (priority 100)

**How to use:** Create a `.md` file named after your category

## Example - Create `content/tutorials.md`

```markdown
---
type = "category"
title = "Tutorials"
description = "Learn with our step-by-step guides"
template = "category-index"
menu = 1.3
---

Browse all our tutorials below. This text will be replaced with the file listing.
```

## What You Get

StaticForge generates `output/tutorials/index.html` containing:
- All files with `category = "tutorials"`
- Sorted, styled listing
- Pagination (if you have many files)
- Your custom template styling

## Template Variables Available

```twig
{{ category }}           {# "tutorials" #}
{{ total_files }}        {# 23 #}
{{ files }}              {# Array of file objects #}

{% for file in files %}
  <article>
    <h2><a href="{{ file.url }}">{{ file.title }}</a></h2>

    {% if file.image %}
      <img src="{{ file.image }}" alt="{{ file.title }}">
    {% endif %}

    {% if file.metadata.description %}
      <p>{{ file.metadata.description }}</p>
    {% endif %}

    <time>{{ file.date }}</time>
  </article>
{% endfor %}
```

## File Object Properties

- `file.title` - The page title
- `file.url` - Relative URL to the page
- `file.image` - Hero/featured image (if any)
- `file.date` - Publication or modification date
- `file.metadata` - All frontmatter from the file

## Example Category Index Template

```twig
{% extends "base.html.twig" %}

{% block content %}
<div class="category-page">
  <h1>{{ category|title }}</h1>
  <p class="count">{{ total_files }} articles</p>

  <div class="article-grid">
    {% for file in files %}
      <article class="card">
        <h2><a href="{{ file.url }}">{{ file.title }}</a></h2>
        <p>{{ file.metadata.description|default('') }}</p>
        <a href="{{ file.url }}" class="read-more">Read more →</a>
      </article>
    {% endfor %}
  </div>
</div>
{% endblock %}
```

---

[← Back to Features Overview](index.html)
