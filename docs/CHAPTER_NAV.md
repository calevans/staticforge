---
title = "Chapter Navigation"
template = "docs"
menu = 1.3.4, 2.7
---

# Chapter Navigation

**What it does:** Automatically generates sequential prev/next navigation links for documentation pages

**Events:** `POST_GLOB` (priority 150, runs after MenuBuilder)

**Configuration:** Set via `.env` file

```bash
# Which menus should have chapter navigation
CHAPTER_NAV_MENUS="2"

# Customize navigation symbols
CHAPTER_NAV_PREV_SYMBOL="←"
CHAPTER_NAV_NEXT_SYMBOL="→"
CHAPTER_NAV_SEPARATOR="|"
```

## Disabling Chapter Navigation

To completely disable chapter navigation processing, either:
- Set `CHAPTER_NAV_MENUS=""` (empty string)
- Don't include `CHAPTER_NAV_MENUS` in your `.env` file at all

When disabled, the feature skips all processing and adds no overhead to your build.

## How It Works

Chapter Navigation uses the menu ordering from MenuBuilder to create sequential navigation between pages. Pages that appear in the configured menus automatically get prev/next links based on their menu position.

## Example Setup

```markdown
---
title = "Quick Start Guide"
menu = 2.1
template = "docs"
---
```

```markdown
---
title = "Configuration Guide"
menu = 2.2
template = "docs"
---
```

```markdown
---
title = "Built-in Features"
menu = 2.3
template = "docs"
---
```

**Results:**
- **Quick Start Guide** (2.1): Shows only "Next →" link to Configuration Guide
- **Configuration Guide** (2.2): Shows "← Prev" to Quick Start and "Next →" to Features
- **Built-in Features** (2.3): Shows only "← Prev" link to Configuration Guide

## Multiple Menus

If a page appears in multiple menus (e.g., `menu = 2.1, 3.2`), and both menus are configured for chapter navigation (`CHAPTER_NAV_MENUS="2,3"`), the page will have separate navigation for each menu context.

## Using in Templates

The chapter navigation HTML is automatically generated. To display it in your template:

```twig
{# Include the snippet (recommended) #}
{% include '_chapter_nav.html.twig' %}
```

Or access the data directly:

```twig
{% if features.ChapterNav.pages[source_file] is defined %}
  {% for menu_num, nav_data in features.ChapterNav.pages[source_file] %}
    {{ nav_data.html|raw }}
  {% endfor %}
{% endif %}
```

## Navigation Data Structure

Each page gets:
- `prev` - Previous page data (title, url, file) or null
- `current` - Current page data
- `next` - Next page data or null
- `html` - Pre-generated HTML for the navigation

## Customization

The navigation includes CSS classes for styling:
- `.chapter-nav` - Container
- `.chapter-nav-prev` - Previous link
- `.chapter-nav-current` - Current page (not a link)
- `.chapter-nav-next` - Next link

## Tips

- Works best with sequential menu positions (2.1, 2.2, 2.3)
- Dropdown items (position ends in .0) are ignored
- Only processes menus specified in `CHAPTER_NAV_MENUS`
- Place the include above your footer for best UX
- Use different symbols for different themes (arrows, text, emoji)

---

[← Back to Features Overview](FEATURES.html)
