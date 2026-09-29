---
title: 'CLI Commands'
description: 'Reference for all StaticForge CLI commands.'
template: docs
menu: '2.2'
og_image: "Command line interface abstract, matrix code rain, green text on black screen, power user terminal, --ar 16:9"
hero: assets/images/cli-commands-hero.jpg
---

# Command Line Interface

StaticForge is primarily a CLI tool. This section details the complete command reference.

## Contents

*   [Site Management](site-management.html) - Rendering and deploying (`site:*`).
*   [Content Creation](content-creation.html) - Scaffolding new content (`make:*`).
*   [Auditing](auditing.html) - Verifying site health (`audit:*`).
*   [System Commands](commands.html) - Utilities and debugging (`feature:*`).
*   [Dev Server and Live Reload](dev-server.html) - Local preview with `site:devserver` and `--watch`.

## Related Commands

*   `site:init` creates a new project, including a starter `content/404.md`.
*   `site:devserver` serves `public/` locally. Missing URLs get `public/404.html` with a real `404` status when that file exists. Options: `--port`/`-p` (default `8000`), `--host` (default `localhost`), `--watch` (rebuild on changes and reload the browser), `--allow-remote` and `--include-drafts` (both only apply with `--watch`). See [Dev Server and Live Reload](dev-server.html).
*   `site:upload` deploys over SFTP. Options: `--url=<url>` (override the site URL and rebuild), `--input=<dir>`, `--test` or `--dry-run` (list what would be uploaded and deleted without changing anything), `--no-delete` (never delete remote files), `--force-delete` (delete stale files even past the delete guard limit). If both delete flags are given, `--no-delete` wins. See [Deploy Safety](site-management.html).
*   `make:htaccess` prints an Apache `.htaccess` (add `--write` to save it to `htaccess.txt`, or `--output=<file>` to choose the name). It includes `ErrorDocument 404 /404.html`.
*   `audit:config` warns if a 404 page exists but `SITE_BASE_URL` is not a full URL.
*   `audit:live` checks that unknown URLs on the deployed site return 404 rather than a redirect or `200`.

See [404 Pages](404-pages.html) for how these fit together.
