---
title: Tags
template: docs
menu: '1.3.7, 2.3.7'
category: docs
---
# Tags

**What it does:** Extracts tags from frontmatter and makes them available site-wide

**Events:**
- `POST_GLOB` (priority 100)
- `POST_RENDER` (priority 100)

**How to use:** Add a `tags` field to your frontmatter

## Example

```markdown
---
title = "Introduction to PHP"
tags = ["php", "tutorial", "beginner", "web-development"]
---

# Introduction to PHP

Learn PHP from scratch!
```

## What Happens

1. Tags are extracted from each file during processing
2. Tags are normalized (lowercase, sanitized)
3. Tags are added to the HTML as `<meta name="keywords">`
4. Tags are available to templates for tag clouds, filtering, etc.

## Using Tags in Templates

### Display Tags on a Page

```twig
{% if tags is iterable and tags|length > 0 %}
  <div class="tags">
    {% for tag in tags %}
      <span class="tag">{{ tag }}</span>
    {% endfor %}
  </div>
{% endif %}
```

### Access All Site Tags

```twig
{% if features.Tags.all_tags is defined %}
  <div class="tag-cloud">
    {% for tag, count in features.Tags.all_tags %}
      <a href="/tags/{{ tag }}.html" class="tag-{{ count }}">
        {{ tag }} ({{ count }})
      </a>
    {% endfor %}
  </div>
{% endif %}
```

## Tag Format Options

```markdown
# Array format (recommended)
tags = ["php", "tutorial", "beginner"]

# Comma-separated string (also works)
tags = "php, tutorial, beginner"
```

## Why Use Tags

- Improve SEO with keyword meta tags
- Create tag-based navigation
- Find related content
- Build tag clouds
- Enable filtering and search

---

[← Back to Features Overview](FEATURES.html)
