---
title: 'Menu Builder'
template: docs
menu: '1.3.3, 2.3.3'
category: docs
---
# Menu Builder

**What it does:** Automatically creates navigation menus from your content

**Events:** `POST_GLOB` (priority 100)

**How to use:** Add a `menu` field to your frontmatter

## Menu Types

StaticForge supports two types of menus:

### Numbered Menus (Content-Based)

Defined in content file frontmatter using the `menu` field. These menus are automatically discovered from your content files.

**Access in templates:** `{{ menu1 }}`, `{{ menu2 }}`, etc.

### Named Menus (Static)

Defined in `siteconfig.yaml` for static/external links. See [Site Configuration](SITE_CONFIG.md) for details.

**Access in templates:** `{{ menu_top }}`, `{{ menu_footer }}`, etc.

**Use case:** External links, hardcoded navigation, links to non-StaticForge sections.

---

## Numbered Menu Positioning System

The `menu` value uses a dot-notation system: `menu.position.dropdown-position`

### Single Menu Position

```markdown
---
title = "Home"
menu = 1.1
---
```
Creates: First item in menu 1

### Multiple Menu Positions

Want a page to appear in multiple menus? Just list the positions separated by commas:

```markdown
---
title = "Privacy Policy"
menu = 1.5, 2.1
---
```
Creates: Item appears in menu 1 at position 5 AND menu 2 at position 1

```markdown
---
title = "Contact Us"
menu = 1.6, 2.3, 3.1
---
```
Creates: Item appears in three different menus

### Format Options
```markdown
menu = 1.2, 2.3         # Recommended - simple and clean
menu = [1.2, 2.3]       # Also works - brackets optional
menu = ["1.2", "2.3"]   # Also works - quotes optional
```

## More Examples

```markdown
---
title = "About"
menu = 1.2
---
```
Creates: Second item in menu 1

```markdown
---
title = "Services"
menu = 1.3.0
---
```
Creates: Dropdown title at position 3 in menu 1 (`.0` means it's the dropdown label)

```markdown
---
title = "Web Development"
menu = 1.3.1
---
```
Creates: First item inside the "Services" dropdown

## Visual Example

```
Menu 1 (Main Navigation):
├─ Home (1.1)
├─ About (1.2)
├─ Services (1.3.0) ▼
│  ├─ Web Development (1.3.1)
│  ├─ Mobile Apps (1.3.2)
│  └─ Consulting (1.3.3)
├─ Contact (1.4)         # Also in menu 2
└─ Privacy (1.5)         # Also in menu 2

Menu 2 (Footer):
├─ Privacy (2.1)         # Same page as 1.5
├─ Terms (2.2)
└─ Contact (2.3)         # Same page as 1.4
```

## Using Menus in Templates

Menus are available in templates through the `features.MenuBuilder` object.

### Option 1 - Pre-rendered HTML

Use the pre-rendered HTML menu:

```twig
<nav>
  {{ features.MenuBuilder.html.1|raw }}
</nav>
```

This outputs the complete `<ul>` structure with all menu items sorted by position.

### Option 2 - Manual Iteration (More Control)

Access the raw menu data to build custom markup:

```twig
<nav>
  <ul class="my-custom-menu">
    {% if features.MenuBuilder.files[1] is defined %}
      {% for item in features.MenuBuilder.files[1] %}
        <li><a href="{{ item.url }}">{{ item.title }}</a></li>
      {% endfor %}
    {% endif %}
  </ul>
</nav>
```

### Menu Data Structure

Each menu item in `features.MenuBuilder.files[X]` contains:

- `title` - Page title
- `url` - Generated URL (includes category prefix if applicable)
- `file` - Source file path
- `position` - Menu position string (e.g., "1.2")

Items are automatically sorted by position number.

### Multiple Menus Example

```twig
{# Top navigation - Menu 1 #}
<nav class="topnav">
  {{ features.MenuBuilder.html.1|raw }}
</nav>

{# Sidebar - Menu 2 #}
<aside class="sidebar">
  <ul class="nav">
    {% for item in features.MenuBuilder.files[2] %}
      <li><a href="{{ item.url }}">{{ item.title }}</a></li>
    {% endfor %}
  </ul>
</aside>

{# Footer - Menu 3 #}
<footer>
  {{ features.MenuBuilder.html.3|raw }}
</footer>
```

---

## How MenuBuilder Works

1. **POST_GLOB Event** - MenuBuilder listens at priority 100
2. **Scan discovered_files** - Iterates pre-parsed metadata from FileDiscovery
3. **Extract menu positions** - Finds all files with `menu` metadata
4. **Build menu structure** - Organizes items by menu number and position
5. **Sort by position** - Ensures items appear in correct order (1, 2, 3... not filesystem order)
6. **Generate HTML** - Creates rendered `<ul>` markup
7. **Store in features** - Makes both raw data and HTML available to templates

---

## Tips

- Use commas to place a page in multiple menus
- Menu items are automatically sorted by position number
- Position `0` is reserved for dropdown titles
- URLs include category prefixes automatically
- No need for brackets or quotes in frontmatter (but they work if you prefer them)
- Menu data is pre-parsed during discovery phase for performance

---

[← Back to Features Overview](FEATURES.html)
