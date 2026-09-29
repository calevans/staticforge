---
title: "What's New in 3.4"
description: 'Site-wide feeds, a live-reloading dev server, a 404 page, and safer deploys, plus the small behavior changes worth knowing about before you upgrade.'
template: docs
menu: '2.4'
og_image: "A lighthouse beam sweeping across a calm harbor at dawn, version 3.4 release, clean flat illustration, --ar 16:9"
---

# What's New in 3.4

StaticForge 3.4 adds four things people asked for: feeds that cover the whole site, a dev server that rebuilds and reloads for you, a proper 404 page, and guards that stop a bad deploy from deleting your site. Everything new is either opt-in or only changes something that was already broken, but a handful of small behavior changes are worth reading before you upgrade.

---

## New

### Site-wide feeds

StaticForge has always written one RSS feed per category. 3.4 can also write `/feed.xml` (RSS), `/feed.atom` and `/feed.json` (JSON Feed) for the whole site. Your category feeds are not touched: same address, same content, same events, so podcast feeds keep working exactly as before.

Site feeds are **off by default** for existing sites. Turn them on with:

```yaml
feed:
  enabled: true
```

Sites created with `site:init` have this switched on. Drafts, `noindex` pages, the 404 page and generated pages are left out, and so is any category whose definition file has `podcast: true`. See [RSS Feed](features/rss-feed.html) for every option.

### Dev server with live reload

`site:devserver --watch` rebuilds when you save a file and reloads the open browser tab. It shows a small "Rebuilding..." note while it works, and a banner with the error if a build fails, while it keeps serving the last good version. See [Dev Server and Live Reload](guide/dev-server.html).

### A 404 page

The `staticforce` and `sample` themes now have a `404` template. Add a `content/404.md` with `template: 404` and StaticForge builds `public/404.html`. `make:htaccess` adds the matching Apache line, the dev server serves the page with a real 404 status, and `audit:live` checks that your live site answers unknown addresses with 404 rather than a redirect or a 200. See [404 Pages](guide/404-pages.html).

### Safer deploys

`site:upload` has a new safety net:

- `--no-delete` uploads but never deletes anything on the server.
- If a run would delete more files than the larger of 10 or a quarter of what you last deployed (or your own `upload.max_delete`), it stops before deleting anything and asks. In a cron job or CI it stops with a failure exit code instead. `--force-delete` overrides it.
- Nothing is forgotten: files that were held back, or that failed to delete, stay on the list and are cleaned up on a later run.

See [Deploy Safety](guide/site-management.html).

---

## Behavior changes you might notice

**Sitemap and robots.txt**

- `sitemap.xml` now leaves out pages with `sitemap: false`, `noindex: true` or `robots: no`, and the 404 page.
- `lastmod` uses `updated` from your frontmatter first, then `date`, then the file's modified time.
- `robots: false` (which is how YAML reads a bare `no`) now blocks a page, as `robots: no` always did.
- A blocked page that is a folder's `index.html` also gets a `Disallow: /folder/$` line, which blocks that page only, not everything under the folder.
- A quoted `search_index: "false"` is now honored.
- Both bundled themes add `<meta name="robots" content="noindex, follow">` for pages with `noindex: true`.

**Search**

- `search.json` is written without pretty-printing or escaped characters. On this site it went from 319 KB to 274 KB. The search scripts no longer log to the browser console.

**Web server and audits**

- `make:htaccess` output includes an `ErrorDocument 404 /404.html` line. Existing `.htaccess` files are never edited.
- `audit:live` makes two extra requests to check unknown addresses return 404. A site that answers 200 or redirects for a missing page will now fail that check.
- `audit:config` warns if a 404 page exists but `SITE_BASE_URL` is not a full address, and it validates the new `feed` and `upload` settings.

**Dev server**

- Every request must resolve to a file inside the output folder. A symbolic link in `public/` that points outside it now returns 404.
- The dev server keeps its working files in a private temporary folder, starts PHP without a shell, and passes it only a minimal environment, so your SFTP credentials never reach it.
- If the PHP server stops on its own, for example because the port is taken, the command now says why and exits with an error.
- `.php` files in `public/` still run, as before.

**Commands**

- `make:content --type` rejects folders that climb out of your content directory.
- `site:upload` exits with an error if any remote deletion fails, instead of silently forgetting it.
- `site:init` writes a sample `content/404.md` and the `feed` setting into new sites only.

---

## Fixes since 3.3.4

These shipped in earlier 3.3.x releases and are included here for completeness:

- **3.3.5:** the built-in `[[youtube]]`, `[[alert]]` and `[[weather]]` shortcodes work again; RSS entries carry the article instead of the whole themed page; search stops indexing your navigation; `site:render` exits with an error when a page fails; `--clean` refuses to wipe your project; incremental builds notice template and config changes; `trust_html: false` is respected by shortcodes and forms.
- **3.3.6:** upload manifest entries are validated and remote deletes are never recursive; `audit:live` verifies the certificate instead of only reading its expiry date.
- **3.3.7:** a failed upload no longer deletes the server's copy of that file, and a category whose name has no usable characters can no longer overwrite your home page.

---

## Upgrading

1. Update StaticForge and run `site:render`. Nothing else is required.
2. To use site feeds, add `feed:` with `enabled: true` to `siteconfig.yaml`. If you publish a podcast, put `podcast: true` in the category's definition file so its episodes stay out of the site feeds.
3. To add a 404 page, create `content/404.md` (see [404 Pages](guide/404-pages.html)) and run `make:htaccess` to get the Apache line.
4. If you rely on a symbolic link inside `public/` while using the dev server, copy the files instead.

---

## Coming later

Two things are planned for 3.5: moving to PHP's newer built-in HTML parser, and an atomic deploy mode that switches to the new version of your site in one step. Splitting the search index into several files is on hold until someone reports a size problem.
