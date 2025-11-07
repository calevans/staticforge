---
title = "Menu Builder"
template = "docs"
menu = 1.3.3, 2.3.3
---

# Menu Builder

**What it does:** Automatically creates navigation menus from your content

**Events:** `POST_GLOB` (priority 100)

**How to use:** Add a `menu` field to your frontmatter

## Menu Positioning System

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

**Option 1 - Include the menu template:**
```twig
<nav>
  {% include 'menu1.html.twig' %}
</nav>
```

**Option 2 - Access the HTML directly:**
```twig
<nav>
  {{ features.MenuBuilder.html.1|raw }}
</nav>
```

## Tips

- Use commas to place a page in multiple menus
- If you don't specify a menu number (just `menu = 1`), the item appears but in no specific order
- Duplicate positions are allowed - the last one wins
- Position `0` is special - it's always a dropdown title, never a regular link
- No need for brackets or quotes (but they work if you prefer them)

---

[← Back to Features Overview](FEATURES.html)
