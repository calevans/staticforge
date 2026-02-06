---
title: 'Site Configuration (siteconfig.yaml)'
description: 'Reference for siteconfig.yaml, the primary configuration file for StaticForge site settings and menus.'
template: docs
menu: '2.1.3'
url: "https://calevans.com/staticforge/guide/site-config.html"
og_image: "Master control center, mainframes, global settings, mission control room, screens showing system status, --ar 16:9"
---
# Site Configuration (siteconfig.yaml)

StaticForge supports an optional `siteconfig.yaml` file for defining site-wide configuration that can be safely committed to version control. Unlike `.env` which contains sensitive credentials, `siteconfig.yaml` contains non-sensitive site settings like menu definitions, site metadata, and other configuration.

## File Location

Place `siteconfig.yaml` in your project root directory (same level as `content/`, `templates/`, `.env`).

The file is **completely optional** - your site will work fine without it.

## Configuration Options

### Site Information

You can define your site's name and tagline here. These values are available in templates as `{{ site_name }}` and `{{ site_tagline }}`.

```yaml
site:
  name: "My Awesome Site"
  tagline: "Built with StaticForge"
```

### Static Menus

The primary use case for `siteconfig.yaml` is defining static menu items that don't correspond to content files. This is useful for:

- External links (e.g., to an ecommerce section not managed by StaticForge)
- Links to dynamic sections of your site
- Hardcoded navigation structure
- Footer menus, utility menus, etc.

#### Menu Syntax

```yaml
menu:
  top:
    Home: /
    About: /about
    Shop: /shop
    Contact: /contact
  footer:
    Privacy Policy: /privacy
    Terms of Service: /terms
    Contact Us: /contact
  utility:
    Login: /login
    Account: /account
```

#### Menu Structure

- **Named menus**: Each menu has a name (e.g., `top`, `footer`, `utility`)
- **Simple key/value pairs**: `Title: /url`
- **Order matters**: Items appear in the order defined in the YAML file

#### Template Access

Static menus are available in templates using the naming convention `menu_{name}`:

```twig
{# Top navigation #}
{{ menu_top }}

{# Footer navigation #}
{{ menu_footer }}

{# Utility menu #}
{{ menu_utility }}
```

#### HTML Output

Static menus generate the same HTML structure as content-based menus:

```html
<ul class="menu">
  <li><a href="/">Home</a></li>
  <li><a href="/about">About</a></li>
  <li><a href="/shop">Shop</a></li>
  <li><a href="/contact">Contact</a></li>
</ul>
```

### Static vs. Numbered Menus

StaticForge supports two types of menus:

**Numbered Menus** (from frontmatter):
- Defined in content file frontmatter: `menu: 1.5`
- Accessed as `{{ menu1 }}`, `{{ menu2 }}`, etc.
- Position-based ordering (1.0, 1.5, 2.0)
- Automatically discovered from content files

**Named Menus** (from siteconfig.yaml):
- Defined in `siteconfig.yaml` under `menu:`
- Accessed as `{{ menu_top }}`, `{{ menu_footer }}`, etc.
- YAML order determines display order
- Manually defined, not tied to content files

These are **completely separate** systems. Use numbered menus for content-based navigation and named menus for static/external links.

### Disabling Features

You can disable specific features (both core and custom) by adding them to the `disabled_features` list. This is useful for turning off functionality you don't need or troubleshooting issues.

```yaml
disabled_features:
  - WeatherShortcode
  - Sitemap
  - SomeOtherFeature
```

When a feature is disabled:
- It is not loaded by the system
- Its event listeners are not registered
- Other features that depend on it may also be skipped (if they use `requireFeatures`)

### Site Information

Configure site-wide metadata that appears in templates:

```yaml
site:
  name: "StaticForge"
  tagline: "Built with ❤️ and PHP"
  description: "A flexible static site generator"
  author: "Cal Evans"
```

### Forms Configuration

Define forms that can be embedded in your content using the `{{ form('name') }}` shortcode.

```yaml
forms:
  contact:
    provider_url: "https://eicc.com/f/"
    form_id: "YOUR_FORM_ID"
    challenge_url: "https://sendpoint.lndo.site/?action=challenge"
    submit_text: "Send Message"
    success_message: "Thanks! We've received your message."
    error_message: "Oops! Something went wrong. Please try again."
    fields:
      - name: "name"
        label: "Your Name"
        type: "text"
        required: true
        placeholder: "John Doe"
      - name: "email"
        label: "Email Address"
        type: "email"
        required: true
        placeholder: "john@example.com"
      - name: "message"
        label: "Message"
        type: "textarea"
        rows: 7
        required: true
```

See the [Forms Feature documentation](../features/forms.html) for full details.


```yaml
site:
  name: "My Awesome Site"
  tagline: "Building amazing things with PHP"
```

**Configuration Options:**

- `name`: Your site's name (appears in titles, headers, footers)
- `tagline`: A short phrase describing your site

**Template Access:**

```twig
<title>{{ title }} - {{ site_name }}</title>
<h1>{{ site_name }}</h1>
<p>{{ site_tagline }}</p>
```

`SITE_BASE_URL` should remain in `.env` as it's environment-specific (different for dev/staging/production).

### Chapter Navigation

Configure sequential navigation (previous/next links) for numbered menus:

```yaml
chapter_nav:
  menus: "2"           # Comma-separated menu numbers (e.g., "2,3")
  prev_symbol: "←"     # Symbol for previous link
  next_symbol: "→"     # Symbol for next link
  separator: "|"       # Separator between nav elements
```

**Configuration Options:**

- `menus`: Which numbered menus to generate chapter navigation for
  - Single menu: `"2"`
  - Multiple menus: `"2,3,4"`
  - Must be quoted as a string
- `prev_symbol`: Symbol/text for "previous" links (default: `←`)
- `next_symbol`: Symbol/text for "next" links (default: `→`)
- `separator`: Character between navigation elements (default: `|`)

**Fallback Behavior:**

If `chapter_nav` is not in `siteconfig.yaml`, the ChapterNav feature will look for these environment variables in `.env`:
- `CHAPTER_NAV_MENUS`
- `CHAPTER_NAV_PREV_SYMBOL`
- `CHAPTER_NAV_NEXT_SYMBOL`
- `CHAPTER_NAV_SEPARATOR`

**Migration Note:** Moving these settings from `.env` to `siteconfig.yaml` is recommended for better version control, but both locations are supported.

## Complete Example

```yaml
# siteconfig.yaml - Site-wide configuration

# Site Information
site:
  name: "My Awesome Site"
  tagline: "Building amazing things with PHP"

# Static menu definitions
menu:
  # Main navigation
  top:
    Home: /
    Products: /products
    Shop: https://shop.example.com  # External link
    Blog: /blog
    Contact: /contact

  # Footer navigation
  footer:
    About Us: /about
    Privacy Policy: /privacy
    Terms: /terms
    Sitemap: /sitemap.xml

  # User account menu
  account:
    Dashboard: /dashboard
    Settings: /settings
    Logout: /logout

# Chapter navigation for sequential page navigation
chapter_nav:
  menus: "2"           # Generate prev/next links for menu 2
  prev_symbol: "←"
  next_symbol: "→"
  separator: "|"
```

## Implementation Details

### Loading Process

1. **Bootstrap phase**: `siteconfig.yaml` is loaded during application bootstrap (after `.env`)
2. **Location search**: Checks current working directory, then application root
3. **Error handling**: Parse errors are logged but don't stop site generation
4. **Storage**: Configuration is stored in the container as `site_config`

### Menu Generation

1. **POST_GLOB event**: MenuBuilder processes static menus during the POST_GLOB event
2. **HTML generation**: Uses the same HTML generation logic as numbered menus
3. **Container storage**: Each named menu is stored as `menu_{name}` in the container
4. **Template access**: Templates can access menus via `{{ menu_{name} }}` variables

## Use Cases

### External Shop Example

You have a StaticForge site with an external ecommerce platform:

```yaml
menu:
  top:
    Home: /
    About: /about
    Products: /products  # StaticForge page
    Shop: https://shop.mysite.com  # External ecommerce
    Contact: /contact
```

### Separate Footer Menu

Different navigation for your footer:

```yaml
menu:
  top:
    Home: /
    Features: /features
    Pricing: /pricing
    Blog: /blog

  footer:
    Company: /about
    Careers: /careers
    Press: /press
    Legal: /legal
    Privacy: /privacy
    Contact: /contact
```

### Multi-Menu Template

Template using multiple named menus:

```twig
<!DOCTYPE html>
<html>
<head>
  <title>{{ title }}</title>
</head>
<body>
  <header>
    <nav class="main-nav">
      {{ menu_top }}
    </nav>
  </header>

  <main>
    {{ content }}
  </main>

  <footer>
    <nav class="footer-nav">
      {{ menu_footer }}
    </nav>
    <nav class="utility-nav">
      {{ menu_utility }}
    </nav>
  </footer>
</body>
</html>
```

### Search Configuration

Configure the search engine and behavior.

```yaml
search:
  # Search engine to use: 'minisearch' (default) or 'fuse'
  engine: minisearch

  # Paths to exclude from search index
  exclude_paths:
    - /tags/
    - /categories/
    - /404.html
```

## Version Control

**DO commit `siteconfig.yaml` to version control** - it contains no sensitive information.

**DO NOT commit `.env`** - it contains credentials and environment-specific settings.

## Troubleshooting

### Menu Not Appearing

1. Check file location (must be in project root)
2. Verify YAML syntax is valid
3. Check logs for parsing errors
4. Confirm menu name matches template variable (`menu_top` for `top:`)

### YAML Parse Errors

If your YAML is invalid:
- Error is logged but site generation continues
- Menu will not be available
- Check logs for specific parse error message

### Menu Name Conflicts

- Named menus use `menu_{name}` pattern
- Numbered menus use `menu{number}` pattern
- No conflicts possible between the two systems
