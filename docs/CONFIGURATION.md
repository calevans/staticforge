---
title = "Configuration Guide"
template = "docs"
menu = 1.2
---

# Configuration Guide

This guide covers all configuration options for StaticForge.

## Table of Contents
- [Environment Variables](#environment-variables)
- [Directory Configuration](#directory-configuration)
- [Default Features](#default-features)

---

## <a id="environment-variables">Environment Variables</a>

StaticForge uses a `.env` file for configuration. Copy `.env.example` to `.env` and customize.

`.env.example`:

```bash
# StaticForge Configuration

# Content and Output
CONTENT_PATH=content/
OUTPUT_PATH=public/
TEMPLATE_NAME=sample

# Logging
LOG_PATH=logs/app.log
LOG_LEVEL=INFO

```

### Core Settings

#### `CONTENT_PATH`
- **Type**: String (directory path)
- **Default**: `content/`
- **Description**: Directory containing source content files (Markdown, HTML, PDF)
- **Example**:
  ```
  CONTENT_PATH=content/
  ```

#### `OUTPUT_PATH`
- **Type**: String (directory path)
- **Default**: `public/`
- **Description**: Directory where generated static site files are written
- **Example**:
  ```
  OUTPUT_PATH=public/
  ```

#### `TEMPLATE_NAME`
- **Type**: String
- **Default**: `sample`
- **Description**: Name of the template theme to use (must exist in `templates/` directory)
- **Options**: `sample`, `terminal`, `vaulttech`, or custom theme names
- **Example**:
  ```
  TEMPLATE_NAME=terminal
  ```

---

### Logging Settings

#### `LOG_PATH`
- **Type**: String (file path)
- **Default**: `logs/staticforge.log`
- **Description**: Path to application log file
- **Example**:
  ```
  LOG_PATH=logs/staticforge.log
  ```

#### `LOG_LEVEL`
- **Type**: String
- **Default**: `INFO`
- **Options**: `DEBUG`, `INFO`, `WARNING`, `ERROR`, `CRITICAL`
- **Description**: Minimum log level to record
- **Example**:
  ```
  LOG_LEVEL=DEBUG
  ```

---

### Feature-Specific Settings
All of these are optional. If you set a feature specific setting and the feature is not installed then it will be ignored.s

#### Categories

Categories are configured in content frontmatter:

**Frontmatter**:
```
---
category = web-development
---
```


#### Tags

Tags are extracted from content frontmatter:

**Frontmatter**:
```
---
tags = [php, static-site, tutorial]
---
```

---

## <a id="directory-configuration">Directory Configuration</a>

### Content Directory Structure

```
content/
├── index.md                 # Homepage
├── about.md                 # About page
├── blog/                    # Blog posts
│   ├── post-1.md
│   ├── post-2.md
│   └── draft-post.md
├── docs/                    # Documentation
│   ├── getting-started.md
│   └── advanced.md
└── static/                  # Static assets
    ├── images/
    └── downloads/
```

**Rules**:
- Directories are recursed when looking for files to process.
- Directory structure is not preserved in output

---

## <a id="default-features">Default Features</a>

Any of these features can be disabled by simply deleting the directory from `src/Features/`. It is not recommended that you edit these features but it is acceptable to create your own versions or add additional featueres as needed. See [Feature Development](FEATURE_DEVELOPMENT.html) for more information.

These features are included by default:

### Markdown Renderer

**Priority**: 100<br />
**Events Listend For**: `PRE_RENDER`, `RENDER`<br />

Processes `.md` files with:
- INI frontmatter parsing
- CommonMark Markdown to HTML conversion
- Twig template rendering

**Configuration**: None required<br />

### HTML Renderer

**Priority**: 100<br />
**Events Listend For**: `PRE_RENDER`, `RENDER`<br />

Processes `.html` and `.htm` files with:
- INI frontmatter parsing (HTML comments)
- Twig template rendering

**Configuration**: None required<br />

### Menu Builder
If the Menu builder is installed then it will read the frontmatter `menu` key to determine which menu to add the page to.
- `1` Means that it will be placed on Menu 1 but in no particular order.
- `1.2` Means that it will be placed in the second position on Menu 1. If there are duplicates for a given position the last one specified gets the slot.
- `1.2.3` This indicates that position 2 in menu 1 is a drop-down and that this itema will be item number 3 in that drop-down.
- `1.2.0` This is a special case as no menu item can be assigned position 0. In this case, the value specified will be the name of the dropdown in position 2 of menu 1.

**Example:**
```mark
---
menu=1.2
---
```

```
Home (position=1)
About (position=2)
Services (position=3)
  ├─ Web Development (position=1)
  ├─ Mobile Apps (position=2)
  └─ Consulting (position=3)
Contact (position=4)
```

**Priority**: 100<br />
**Events Listend For**: `CREATE`, `PRE_LOOP`<br />


**Template Usage**:
```twig
{{ menu1|raw }}  {# Menu ID 1 #}
{{ menu2|raw }}  {# Menu ID 2 #}
```

### Categories
Categories allows you to group content into subdirectory based on a category name defined in the frontmatter. This is the only way to have the system generate content anywhere except for the webroot.

**Priority**: 100<br />
**Events Listend For**: `POST_RENDER`<br />

**Example:**
```markdown
---
category = web-development
---
```

### Category Index Pages**
If you have categories, you can create a MD file names the same as the category with details about how you want index.html created. If you DO NOT do this then index.html will not be created for that category. If it exists though then it will be used to generate the index page for that category and place it in the appropriate subdirectory.

Generated at `public/{slug}/index.html`

**Example:**

```markdown
---
type = category
title = Business Services
description = Comprehensive business solutions and services for your company
template = index
menu = 1.4
per_page = 10
---

This content will be replaced with the category file listing during site generation.
```

### Tags

**Priority**: 100<br />
**Events**: `POST_RENDER`<br />

Sets the specified tags as meta tags in your page.


**Example:**
```
---
tags = [php, tutorial, beginner]
---
```

### Category Index

**Priority**: 800<br />
**Events**: `POST_LOOP`<br />

Generates category index pages with pagination.

**Configuration**: Pagination
- Posts per page: 10 (hardcoded, can be made configurable)

**Output**:
- `public/category/{slug}/index.html`
- `public/category/{slug}/page-2.html`
- etc.

---


## Next Steps
- [QuickStart Guide](QUICK_START_GUIDE.html)
- Configuration Guide
- [Template Development](TEMPLATE_DEVELOPMENT.html)
- [Feature Development](FEATURE_DEVELOPMENT.html)
- [Core Events](EVENTS.html)
- [Additional Commands](ADDITIONAL_COMMANDS.html)
