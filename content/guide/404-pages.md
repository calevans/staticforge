---
title: '404 Pages'
description: 'How to add a custom 404 page to your StaticForge site and configure your web server to serve it with a real 404 status.'
template: docs
menu: '2.1.5'
og_image: "A lost paper airplane hovering over a glowing signpost pointing to helpful destinations, calm blue theme, --ar 16:9"
---

# 404 Pages

Sooner or later a visitor will follow a broken link or mistype an address. A good 404 page tells them what happened and points them somewhere useful instead of leaving them at a blank browser error.

This page shows how to add one, how to customize it, and, most importantly, how to make sure your web server answers with a real `404` status code.

**A 404 page does nothing until you configure your web server.** StaticForge only builds `404.html`. Your server has to be told to send it for missing URLs. Until you do, visitors get the server's own plain "Not Found" page. See [Configuring Your Web Server](#content-configuring-your-web-server): on Apache it is one line, `ErrorDocument 404 /404.html`.

## Contents

*   [Creating the Page](#content-creating-the-page)
*   [Customizing the Page](#content-customizing-the-page)
*   [Absolute Links and Sub-Path Installs](#content-absolute-links-and-sub-path-installs)
*   [Configuring Your Web Server](#content-configuring-your-web-server)
*   [Verifying It Works](#content-verifying-it-works)

---

## Creating the Page

A 404 page is an ordinary content file that uses the `404` template and is written to `public/404.html`. Create `content/404.md`:

```yaml
---
title: 'Page not found'
description: 'The page you were looking for does not exist.'
template: 404
noindex: true
sitemap: false
search_index: false
no_llms: true
---

Sorry, we could not find that page.
```

What each key does:

| Key | Effect |
| --- | --- |
| `template: 404` | Renders the page with the theme's `404.html.twig` and writes it to `public/404.html`. |
| `noindex: true` | Adds `<meta name="robots" content="noindex, follow">` so search engines do not index the page. |
| `sitemap: false` | Keeps the page out of `sitemap.xml`. |
| `search_index: false` | Keeps the page out of the site search index. |
| `no_llms: true` | Asks the AEO tooling to leave the page out of `llms.txt`. Only relevant if you use that package. |

StaticForge also excludes `404.html` from the sitemap by its output path, so the sitemap stays clean even if you leave `sitemap: false` out. The other keys are still worth setting.

**New sites** created with `site:init` get this file automatically. **Existing sites** are not touched: create `content/404.md` yourself with the frontmatter above.

---

## Customizing the Page

The two bundled themes each ship a `404.html.twig`.

*   **`staticforce`** shows a "404" label, a "Page not found" heading, a short explanation, a "Go to the homepage" button, a search box (only when JavaScript is available, and it replaces the navbar search on this page), and a "Popular sections" list. Visitors without JavaScript see a link to the sitemap instead of the search box.
*   **`sample`** shows the same heading and explanation, a homepage link, the "Popular sections" list, and a sitemap link. It has no search.

The bundled templates supply the headings and wording; the body text of `content/404.md` is not displayed by them. To change the wording, copy the theme's `404.html.twig` into your own theme and edit it (see [Templates](../development/templates.html)).

### Choosing the Popular Sections

The link list comes from the first of these that exists:

1.  A `404_links` list in `siteconfig.yaml`.
2.  Your top-level menu (`menu.top`).
3.  A single link to the home page.

```yaml
404_links:
  - title: "Getting Started"
    url: "guide/quick-start.html"
  - title: "Features"
    url: "features/index.html"
  - title: "Blog"
    url: "https://blog.example.com/"
```

Each item needs a `title` and a `url`. A `url` starting with `http` is used as written. Any other value is treated as relative to `SITE_BASE_URL`, so `guide/quick-start.html` and `/guide/quick-start.html` both work. Items missing either key are skipped. See [Site Configuration](site-config.html#content-404-page-links) for the reference entry.

### Custom Themes

A theme that does not ship a `404.html.twig` cannot render a page with `template: 404`. If you use a custom theme, add a `404.html.twig` that extends your base layout. See [Templates](../development/templates.html#content-the-404-template) for what the bundled version does and what to include.

---

## Absolute Links and Sub-Path Installs

A web server shows the 404 page for any missing address, at any depth: `/nope`, `/blog/2024/nope`, and so on. A relative link like `guide/index.html` would resolve differently at each depth and break.

For that reason the bundled 404 templates build every link and asset URL from the site base URL (`site_base_url` in templates, set by `SITE_BASE_URL` in `.env`). Set it to a full URL:

```bash
SITE_BASE_URL="https://example.com/"
```

If your site lives in a sub-path, include it: `https://example.com/docs/`. The template links then include the sub-path, but your web server's 404 setting must also point at the sub-path (see the Apache section below).

`audit:config` checks this for you. When `content/404.md` or `content/404.html` exists and `SITE_BASE_URL` is not a full URL, it prints a warning asking you to set one:

```bash
php bin/staticforge.php audit:config
```

---

## Configuring Your Web Server

Generating `public/404.html` is only half the job. Your server must be told to send that file **with a 404 status** whenever a URL does not exist. Uploading the file is not enough: `https://example.com/404.html` will load, but `https://example.com/no-such-page` will still show the server's default error page until you add the setting for your server below.

### Apache

`make:htaccess` generates a production `.htaccess` that now ends with this line:

```apache
ErrorDocument 404 /404.html
```

```bash
# Print to the screen
php bin/staticforge.php make:htaccess

# Save to htaccess.txt
php bin/staticforge.php make:htaccess --write

# Save to a file of your choice
php bin/staticforge.php make:htaccess --write --output=my-htaccess.txt
```

`make:htaccess` only prints or saves the text. `site:render` never puts an `.htaccess` in `public/`, and `site:upload` never sends one or replaces the one on your server.

**`site:upload` adds the 404 line for you.** When your site has a `404.html`, the upload adds `ErrorDocument 404 /404.html` to the end of the `.htaccess` in your remote folder, and prints a line saying so. It only ever adds to the file:

*   Everything already in the file is left exactly as it is.
*   If the file already has an `ErrorDocument 404` line of its own, nothing is added. A line inside an `<IfModule>` or similar block counts too, so an existing setup is never overridden.
*   If the site lives in a sub-path (for example `UPLOAD_URL` is `https://example.com/docs`), the line uses that path: `ErrorDocument 404 /docs/404.html`.
*   The path comes from `UPLOAD_URL` (or `--url`). If it contains spaces or other unusual characters, the upload prints a notice and skips the line.
*   If the file does not exist yet, it is created.
*   Dry runs and uploads with errors change nothing.

The `.htaccess` must be in the folder you upload to (`SFTP_REMOTE_PATH`). If your web server reads its rules from a parent folder or the virtual host, add the line there yourself. That also applies if `.htaccess` files are disabled (`AllowOverride None`): put the line inside the `<VirtualHost>` block and reload Apache.

To see or copy the full recommended file, run `make:htaccess --write` and read `htaccess.txt`.

Two rules for that line:

*   Use a **local path** only. A full URL (`ErrorDocument 404 https://example.com/404.html`) makes Apache send a redirect instead of a 404.
*   If your site is installed in a sub-path, change the line to match, for example `ErrorDocument 404 /docs/404.html`.

### nginx

Add this to your `server` block:

```nginx
error_page 404 /404.html;

location = /404.html {
    internal;
}
```

Then test and reload nginx:

```bash
sudo nginx -t && sudo nginx -s reload
```

nginx keeps the 404 status when it serves the page. The `internal` location means visitors only see the page as an error response, so `/404.html` itself is not directly reachable. Leave that block out if you want the page to also load at `/404.html`.

If your site is installed in a sub-path, change both lines to match, for example `error_page 404 /docs/404.html;`.

### Dev Server

`site:devserver` serves `public/404.html` for any URL that does not exist, with a real `404` status. If `public/404.html` has not been built yet, it falls back to a plain built-in 404 page.

```bash
php bin/staticforge.php site:devserver
```

### Cloudflare Pages

Host behaviour for 404.html has not been verified for this guide yet.

### Netlify

Host behaviour for 404.html has not been verified for this guide yet.

### GitHub Pages

Host behaviour for 404.html has not been verified for this guide yet.

---

## Verifying It Works

### Why the Status Code Matters

If a server answers a missing URL with your friendly page but a `200 OK` status (a "soft 404"), search engines treat every made-up address as a real page. The same happens if unknown URLs are redirected to `/404.html` or to the home page. Always serve the 404 page **in place**, with status 404, and never redirect to it.

### Check with curl

```bash
curl -I https://example.com/no-such-page
```

The first line of the response must be `HTTP/1.1 404 Not Found` (or `HTTP/2 404`). A `200` or a `301`/`302` means the server is misconfigured.

### Check with `audit:live`

`audit:live` includes a soft-404 check against your deployed site. It requests a random address that does not exist, once without an extension and once ending in `.html`, since servers often treat the two differently.

*   Both requests must return `404`.
*   A redirect (any `3xx`) counts as a failure. Redirects are never followed.
*   Any other status, such as `200`, is reported as an error.
*   If the site cannot be reached, you get a warning instead.
*   `--insecure` relaxes TLS certificate verification for this check, just as it does for the others.

```bash
php bin/staticforge.php audit:live
php bin/staticforge.php audit:live --url=https://example.com --insecure
```

---

## Next Steps

*   [Site Management & Deployment](site-management.html) - Build and upload your site.
*   [Auditing](auditing.html) - Run `audit:config` and `audit:live` on your project.
*   [Site Configuration](site-config.html) - The `404_links` key and other settings.
*   [Templates](../development/templates.html) - Write a `404.html.twig` for your own theme.
