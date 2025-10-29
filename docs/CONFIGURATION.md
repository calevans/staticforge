# Configuration Guide

This guide covers all configuration options for StaticForge.

## Table of Contents
- [Environment Variables](#environment-variables)
- [Directory Configuration](#directory-configuration)
- [Template Configuration](#template-configuration)
- [Feature Configuration](#feature-configuration)
- [Development vs Production](#development-vs-production)

---

## Environment Variables

StaticForge uses a `.env` file for configuration. Copy `.env.example` to `.env` and customize.

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

### Database Settings

StaticForge uses MySQL/MariaDB for storing content metadata, menu items, categories, and tags.

#### `DB_HOST`
- **Type**: String (hostname)
- **Default**: `database` (Lando), `localhost` (production)
- **Description**: Database server hostname
- **Example**:
  ```
  DB_HOST=database
  ```

#### `DB_PORT`
- **Type**: Integer
- **Default**: `3306`
- **Description**: Database server port
- **Example**:
  ```
  DB_PORT=3306
  ```

#### `DB_NAME`
- **Type**: String
- **Default**: `lamp`
- **Description**: Database name
- **Example**:
  ```
  DB_NAME=staticforge
  ```

#### `DB_USER`
- **Type**: String
- **Default**: `lamp`
- **Description**: Database username
- **Example**:
  ```
  DB_USER=root
  ```

#### `DB_PASS`
- **Type**: String
- **Default**: `lamp`
- **Description**: Database password
- **Security**: Never commit real passwords to version control
- **Example**:
  ```
  DB_PASS=your_secure_password
  ```

---

### Logging Settings

#### `LOG_PATH`
- **Type**: String (file path)
- **Default**: `logs/app.log`
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

#### Categories

Categories are configured in database tables and content frontmatter:

**Frontmatter**:
```
---
category = web-development
---
```

**Database** (`categories` table):
```sql
INSERT INTO categories (slug, title, description)
VALUES ('web-development', 'Web Development', 'Articles about web development');
```

#### Tags

Tags are extracted from content frontmatter:

**Frontmatter**:
```
---
tags = [php, static-site, tutorial]
---
```

#### Menus

Menus are configured in the database (`menu_items` table):

```sql
INSERT INTO menu_items (menu_id, label, url, position, parent_id)
VALUES (1, 'Home', '/', 1, NULL);
```

See the [Menu System](#menu-system-configuration) section for details.

---

## Directory Configuration

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
- Files are processed recursively
- Directory structure is preserved in output
- Files starting with `_` are ignored
- Supported extensions: `.md`, `.html`, `.htm`

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

**Optional Files**:
- `index.html.twig`: Homepage override
- `menu1.html.twig`: Primary menu template
- `menu2.html.twig`: Secondary menu template
- `placeholder.jpg`: Default placeholder image (1200x630px)

---

## Template Configuration

### Twig Template Variables

All templates have access to these variables:

#### Global Variables

```twig
{{ site.title }}           {# Site title from config #}
{{ site.description }}     {# Site description #}
{{ site.url }}            {# Site URL #}
{{ page.title }}          {# Current page title #}
{{ page.content }}        {# Rendered page content #}
{{ page.url }}            {# Current page URL #}
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
{{ category.title }}      {# Category title #}
{{ category.description }}{# Category description #}
{{ category.posts }}      {# Array of posts in category #}
{{ category.pagination }} {# Pagination data #}
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

---

## Feature Configuration

### Markdown Renderer

**Enabled**: Always active
**Priority**: 500
**Events**: `PRE_RENDER`, `RENDER`

Processes `.md` files with:
- INI frontmatter parsing
- CommonMark Markdown to HTML conversion
- Twig template rendering

**Configuration**: None required

### HTML Renderer

**Enabled**: Always active
**Priority**: 500
**Events**: `PRE_RENDER`, `RENDER`

Processes `.html` and `.htm` files with:
- INI frontmatter parsing (HTML comments)
- Twig template rendering

**Configuration**: None required

### Menu Builder

**Enabled**: Always active
**Priority**: 100
**Events**: `CREATE`, `PRE_LOOP`

Builds navigation menus from database.

**Database Tables**:
- `menus`: Menu definitions
- `menu_items`: Menu items with hierarchy

**Example Data**:
```sql
-- Create menu
INSERT INTO menus (id, name, description) VALUES (1, 'Primary', 'Main navigation');

-- Add menu items
INSERT INTO menu_items (menu_id, label, url, position, parent_id) VALUES
(1, 'Home', '/', 1, NULL),
(1, 'About', '/about.html', 2, NULL),
(1, 'Services', '#', 3, NULL),
(1, 'Web Development', '/services/web-dev.html', 1, 3),
(1, 'Mobile Apps', '/services/mobile.html', 2, 3);
```

**Template Usage**:
```twig
{{ menu1|raw }}  {# Menu ID 1 #}
{{ menu2|raw }}  {# Menu ID 2 #}
```

### Categories

**Enabled**: Always active
**Priority**: 600
**Events**: `POST_RENDER`

Organizes content into categories.

**Database Table**: `categories`
```sql
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(255) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Content Frontmatter**:
```
---
category = web-development
---
```

**Category Index Pages**:
Generated at `public/category/{slug}/index.html`

### Tags

**Enabled**: Always active
**Priority**: 700
**Events**: `POST_RENDER`

Extracts and indexes content tags.

**Database Table**: `tags`
```sql
CREATE TABLE tags (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE content_tags (
  content_id VARCHAR(255) NOT NULL,
  tag_id INT NOT NULL,
  FOREIGN KEY (tag_id) REFERENCES tags(id)
);
```

**Content Frontmatter**:
```
---
tags = [php, tutorial, beginner]
---
```

### Category Index

**Enabled**: Always active
**Priority**: 800
**Events**: `POST_LOOP`

Generates category index pages with pagination.

**Configuration**: Pagination
- Posts per page: 10 (hardcoded, can be made configurable)

**Output**:
- `public/category/{slug}/index.html`
- `public/category/{slug}/page-2.html`
- etc.

---

## Development vs Production

### Development Setup (.env)

```bash
# Lando development environment
CONTENT_PATH=content/
OUTPUT_PATH=public/
TEMPLATE_NAME=sample

DB_HOST=database
DB_PORT=3306
DB_NAME=lamp
DB_USER=lamp
DB_PASS=lamp

LOG_PATH=logs/app.log
LOG_LEVEL=DEBUG
```

### Production Setup (.env)

```bash
# Production environment
CONTENT_PATH=/var/www/staticforge/content/
OUTPUT_PATH=/var/www/html/
TEMPLATE_NAME=production

DB_HOST=localhost
DB_PORT=3306
DB_NAME=staticforge_prod
DB_USER=staticforge_user
DB_PASS=secure_password_here

LOG_PATH=/var/log/staticforge/app.log
LOG_LEVEL=WARNING
```

---

## Menu System Configuration

### Creating Menus

1. **Create Menu**:
```sql
INSERT INTO menus (id, name, description)
VALUES (1, 'Primary', 'Main site navigation');
```

2. **Add Top-Level Items**:
```sql
INSERT INTO menu_items (menu_id, label, url, position, parent_id) VALUES
(1, 'Home', '/', 1, NULL),
(1, 'About', '/about.html', 2, NULL),
(1, 'Services', '#', 3, NULL),
(1, 'Contact', '/contact.html', 4, NULL);
```

3. **Add Submenu Items**:
```sql
-- Assuming 'Services' item has id=3
INSERT INTO menu_items (menu_id, label, url, position, parent_id) VALUES
(1, 'Web Development', '/services/web-dev.html', 1, 3),
(1, 'Mobile Apps', '/services/mobile.html', 2, 3),
(1, 'Consulting', '/services/consulting.html', 3, 3);
```

### Menu Position

The `position` field determines order within a level:
- Lower numbers appear first
- Position is relative to siblings (items with same `parent_id`)

**Example**:
```
Home (position=1)
About (position=2)
Services (position=3)
  ├─ Web Development (position=1)
  ├─ Mobile Apps (position=2)
  └─ Consulting (position=3)
Contact (position=4)
```

### Menu in Templates

```twig
{# Primary menu (menu_id=1) #}
<nav class="primary-nav">
  {{ menu1|raw }}
</nav>

{# Secondary menu (menu_id=2) #}
<nav class="secondary-nav">
  {{ menu2|raw }}
</nav>
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

## Advanced Configuration

### Custom Features

Create custom features in `src/Features/YourFeature/Feature.php`.

See [FEATURE_DEVELOPMENT.md](FEATURE_DEVELOPMENT.md) for details.

### Database Migrations

Run migrations:
```bash
lando php migrations/run_migrations.php
```

Create new migration:
```php
<?php
// migrations/007_your_feature.php

return [
  'up' => "
    CREATE TABLE your_table (
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(255) NOT NULL
    );
  ",
  'down' => "
    DROP TABLE IF EXISTS your_table;
  "
];
```

### Container Services

Register custom services in `src/Core/Bootstrap.php`:

```php
$container->set('yourService', function() use ($container) {
  return new YourService($container->get('config'));
});
```

Access in features:
```php
$service = $this->container->get('yourService');
```

---

## Environment File Template

Complete `.env.example`:

```bash
# StaticForge Configuration

# Content and Output
CONTENT_PATH=content/
OUTPUT_PATH=public/
TEMPLATE_NAME=sample

# Database
DB_HOST=database
DB_PORT=3306
DB_NAME=lamp
DB_USER=lamp
DB_PASS=lamp

# Logging
LOG_PATH=logs/app.log
LOG_LEVEL=INFO

# Feature Settings (future expansion)
# PAGINATION_PER_PAGE=10
# SEARCH_INDEX_ENABLED=true
# CACHE_ENABLED=false
```

---

## Troubleshooting

### Database Connection Issues

**Error**: `Connection refused`

**Solutions**:
1. Verify database is running: `lando info`
2. Check `DB_HOST` matches Lando service name (`database`)
3. Verify credentials in `.env` match database

### Template Not Found

**Error**: `Template not found: base.html.twig`

**Solutions**:
1. Verify `TEMPLATE_NAME` in `.env` matches directory in `templates/`
2. Ensure `base.html.twig` exists in theme directory
3. Check file permissions

### Missing Menu

**Error**: Menu not appearing on pages

**Solutions**:
1. Verify menu data exists in database:
   ```sql
   SELECT * FROM menus;
   SELECT * FROM menu_items;
   ```
2. Check template includes `{{ menu1|raw }}`
3. Run with `-v` flag to see menu building logs

---

## Next Steps

- Read [FEATURE_DEVELOPMENT.md](FEATURE_DEVELOPMENT.md) for custom features
- See [README.md](../README.md) for usage examples
- Check [documents/technical.md](../documents/technical.md) for architecture details
