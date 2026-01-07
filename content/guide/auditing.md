---
title: 'Site Auditing'
description: 'A guide to using StaticForge audit tools to validate integrity, SEO, and security.'
template: docs
menu: '2.2.2'
url: "https://calevans.com/staticforge/guide/auditing.html"
og_image: "A magnifying glass examining digital code, green checkmarks appearing on a holographic screen, quality assurance, futuristic lab, --ar 16:9"
hero: assets/images/auditing-hero.jpg
---

# Site Auditing & Quality Assurance

StaticForge isn't just about building sites; it's about building *correct* sites. The system includes a comprehensive suite of audit tools designed to catch issues—from broken links to missing SEO tags—before your visitors do.

---

## Why Audit?

In the dynamic world of CMSs (like WordPress), errors often happen at runtime. In the static world, we have the luxury of checking everything *before* deployment.

StaticForge divides auditing into four distinct phases:
1.  **Configuration**: Is the environment set up correctly?
2.  **Content**: Is the source content valid?
3.  **Build**: Does the generated HTML work (links, images)?
4.  **Live**: Is the production server secure and performant?

---

## Phase 1: Configuration Audit

The `audit:config` command validates your project structure, environment variables (`.env`), and feature settings. It ensures you haven't missed critical settings like `SITE_BASE_URL`.

**When to run:** When setting up a new machine, deploying for the first time, or troubleshooting weird behavior.

```bash
php bin/staticforge.php audit:config
```

---

## Phase 2: Content Audit

The `audit:content` command scans your **source** markdown files (`content/`). It validates Frontmatter syntax and ensures required fields (like `title` and `layout`) are present.

**When to run:** While writing or integrating content from other people.

```bash
php bin/staticforge.php audit:content
```

---

## Phase 3: Link & SEO Audit

These checks happen **after** you run `site:render`. They check the final HTML output.

### Link Validation (`audit:links`)
This tool crawls your `output/` directory and checks every `<a>` tag.

*   **Internal Links**: Verifies that links to other pages on your site actually exist.
*   **External Links**: (Optional) Pings external websites to ensure they are still up.

**Best Practice:** Run internal checks on every build. Run external checks weekly to prevent "link rot."

```bash
# Fast: Check internal links only
php bin/staticforge.php audit:links --internal

# Thorough: Check everything (may be slow)
php bin/staticforge.php audit:links
```

### SEO Validation (`audit:seo`)
Ensures your pages are search-engine friendly. It checks for:
*   Unique Page Titles
*   Meta Descriptions (presence and length)
*   Canonical URLs

```bash
php bin/staticforge.php audit:seo
```

---

## Phase 4: Live Site Audit

The `audit:live` command is unique because it checks your **hosted** website, not your local files. It verifies that your web server is sending the correct security headers.

**Checks performed:**
*   **HSTS**: Ensures SSL is enforced.
*   **X-Content-Type-Options**: Prevents MIME-sniffing attacks.
*   **X-Frame-Options**: Prevents clickjacking.

**When to run:** Immediately after deploying to production.

```bash
php bin/staticforge.php audit:live
```
