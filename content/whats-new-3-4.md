---
title: "What's New in 3.4"
description: 'Site-wide feeds, a live-reloading dev server, a 404 page, and safer deploys.'
template: docs
menu: '2.4'
og_image: "A lighthouse beam sweeping across a calm harbor at dawn, version 3.4 release, clean flat illustration, --ar 16:9"
---

# What's New in 3.4

StaticForge 3.4.0 adds site-wide feeds, a dev server that rebuilds and reloads for you, a proper 404 page, and safeguards that stop a bad deploy from wiping your site. Everything new is opt-in or only changes something that was already broken. Various bug and security fixes are included as well.

---

## Site-wide feeds

StaticForge has always written one RSS feed per category. 3.4 can also write a feed for the whole site, in three formats:

| Format | Address |
|--------|---------|
| RSS 2.0 | `/feed.xml` |
| Atom 1.0 | `/feed.atom` |
| JSON Feed 1.1 | `/feed.json` |

Your category feeds are not touched. Same address, same content, so podcast feeds keep working exactly as before. Categories can also get Atom and JSON versions alongside their `rss.xml`, if you ask for them.

Turn the site feeds on in `siteconfig.yaml`:

```yaml
feed:
  enabled: true
```

They are off by default for existing sites, and on for sites created with `site:init`. Drafts, `noindex` pages, the 404 page and generated pages are left out. So is any category whose definition file has `podcast: true`, because the site feeds cannot carry podcast enclosures. The bundled themes add the autodiscovery `<link>` tags for you, and custom themes get a `feed_links` variable. See [RSS Feed](features/rss-feed.html) for every option.

---

## Dev server with live reload

`site:devserver --watch` watches your content, templates and configuration. When you save a file it rebuilds and reloads the open browser tab.

- A small "Rebuilding..." note appears while it works.
- If a build fails, a banner shows the error and the last good version keeps being served.
- Builds are incremental, and deleting a source file triggers a clean build so no orphan pages linger.

```bash
lando php bin/staticforge.php site:devserver --watch
```

Set `SITE_BASE_URL` in `.env` to the dev server address (for example `http://localhost:8000/`) while you work, so your CSS and JavaScript load from it. See [Dev Server and Live Reload](guide/dev-server.html).

---

## A 404 page

The `staticforce` and `sample` themes now include a `404` template. Create `content/404.md` with `template: 404` and StaticForge builds `public/404.html`. It has a search box (in `staticforce`) and a list of popular sections you can set with `404_links` in `siteconfig.yaml`.

- `make:htaccess` includes the matching `ErrorDocument 404 /404.html` line, and (from 3.4.1) `site:upload` adds that line to the `.htaccess` on your server if it has no `ErrorDocument 404` of its own. Nothing else in the file is touched.
- The dev server serves the page with a real 404 status.
- `audit:live` checks that your live site answers unknown addresses with a 404, not a redirect or a 200.
- `site:init` writes a sample `content/404.md` for new sites.

See [404 Pages](guide/404-pages.html).

---

## Safer deploys

`site:upload` has a new safety net:

- `--no-delete` uploads but never deletes anything on the server.
- If a run would delete more files than the larger of 10 or a quarter of what you last deployed (or your own `upload.max_delete`), it stops before deleting anything and asks. In a cron job or CI it stops with a failure exit code instead. `--force-delete` overrides it.
- Files that were held back, or that failed to delete, are remembered and cleaned up on a later run.

See [Deploy Safety](guide/site-management.html).

---

## Smaller improvements

- **Sitemap and robots.txt:** pages with `sitemap: false`, `noindex: true` or `robots: no` are left out of `sitemap.xml`, and `lastmod` uses `updated`, then `date`, then the file's modified time. Both bundled themes add a `noindex` meta tag for `noindex: true` pages.
- **Search:** `search.json` is written compactly. On this site it dropped from 319 KB to 274 KB.
- **Audits:** `audit:config` validates the new `feed` and `upload` settings.

---

## Upgrading

1. Update StaticForge and run `site:render`. Nothing else is required.
2. To use site feeds, add `feed:` with `enabled: true` to `siteconfig.yaml`. If you publish a podcast, put `podcast: true` in the category's definition file so its episodes stay out of the site feeds.
3. To add a 404 page, create `content/404.md` (see [404 Pages](guide/404-pages.html)) and run `site:upload`. On Apache it adds the `ErrorDocument` line to your server's `.htaccess` (see the guide for nginx).
4. `audit:live` now makes two extra requests to check for a real 404. A site that answers 200 or redirects for a missing page will fail that check.
5. If you rely on a symbolic link inside `public/` while using the dev server, copy the files instead. Links that point outside the output folder now return 404.

---

## Coming later

Two things are planned for 3.5: moving to PHP's newer built-in HTML parser, and an atomic deploy mode that switches to the new version of your site in one step.
