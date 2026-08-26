---
title: "What's New in 3.0"
description: 'A tour of what changed in StaticForge 3.0 — typed events, security hardening, and a completed dependency-injection story.'
template: docs
menu: '2.3'
og_image: "Fireworks over a futuristic city skyline, version 3.0 celebration, bold and vibrant, digital art, --ar 16:9"
---

# What's New in 3.0

StaticForge 3.0 is a modernization release, not a rewrite. Every change in it followed the same rule: behavior stays the same unless a bug is being fixed on purpose. If you're rendering a site today, upgrading and re-running `site:render` should produce byte-identical output — the changes are almost entirely under the hood.

The one place that *does* break compatibility is how Features hook into the system. If you've written a custom Feature — in-tree or as an external Composer package — see [Migrating to 3.0](migrating-to-3-0.html) for the upgrade path. Everything else on this page is safe to skim.

---

## Requirements Changed

*   **PHP 8.5 or higher** is now required (was 8.4).
*   **Symfony components bumped to `^8.0`** (console, dom-crawler, css-selector, http-client, yaml). If you have code that calls `Application::add()` directly, it's gone in Symfony 8 — use `Application::addCommand()`. Commands using the old `protected static $defaultName` property no longer work either; use the `#[AsCommand(name: ..., description: ...)]` attribute instead.

---

## Typed Events Replace Array-Based Ones

Every event listener used to receive `(Container $container, array $parameters)` and had to `return $parameters` to keep the pipeline alive. That's gone. Listeners now receive one typed object — `Event`, `RenderEvent`, or a purpose-built subclass like `RssItemBuildingEvent` — and mutate its public properties directly. There's no array to remember to return, and no "forgot to return it, and now the site won't build" failure mode.

This is the biggest change in 3.0, and it's the reason 3.0 is a major version bump rather than a minor one. See [The Nervous System: Events](development/events.html) for the full reference and [Migrating to 3.0](migrating-to-3-0.html) if you need to update your own code — that page also covers `feature:migrate`, a CLI command that converts most of a Feature's old event contract automatically.

---

## Dependency Injection, Finished

Every in-tree Feature now gets its dependencies constructor-injected and autowired by a new `FeatureFactory`, instead of hand-building services inside `register()`. Two real bugs came out of finishing this:

*   `HtmlRenderer` and `ShortcodeProcessor` were each building their own private Twig environment instead of sharing the one everyone else uses — a full `site:render` was silently compiling Twig three times instead of once.
*   `MenuBuilder`'s and `TagsService`'s scan-time state could end up split across two different service instances depending on load order, silently losing data. They're now explicit shared singletons.

Neither of these ever showed up as an error — they were just slower and, in MenuBuilder's case, occasionally wrong. Both are fixed.

---

## Security Hardening

A cluster of real, previously-unguarded issues were closed in this release:

*   **Path traversal** — a naive prefix check (`/content` would incorrectly match `/content-evil`) existed in three places (output-path generation, robots.txt path calculation, category image handling). All now go through a shared `PathGuard`, and all file writes go through a jailed `OutputWriter`.
*   **Secrets in templates** — the full container, including `.env` values like `SFTP_PASSWORD`, used to be available to every Twig template. Templates now only see an explicit allowlist of variables built from what the shipped themes actually use.
*   **Frontmatter injection** — a page's YAML frontmatter could previously set reserved keys like `app_root` or anything matching `*_KEY`/`*_TOKEN`/`*_SECRET`/`*_PASSWORD`, silently overriding real system values. These are now stripped and logged, not applied.
*   **Search XSS** — the search results page built HTML via string interpolation and `innerHTML`; a title or category containing `<script>` would have executed. Rewritten to build DOM nodes with `textContent`.
*   **SFTP host-key verification** — `site:upload` now does trust-on-first-use host key pinning (stored next to your SFTP key, or overridable via `SFTP_HOST_KEY`) and fails closed if a host key changes unexpectedly, instead of accepting whatever the server presents every time.
*   **SSRF hardening** — the `audit:live` and `audit:links` commands now validate that external URLs can't resolve to private/loopback/link-local addresses, and cap redirect chains and protocols.
*   **CSS `@import` traversal** — template asset bundling could previously be made to read arbitrary files via a crafted `@import` path.
*   **Forms `https:`-only** — form `provider_url`/`challenge_url` values are dropped (with a logged warning) rather than rendered if they aren't `https:`.

None of these change the shape of `siteconfig.yaml` or `.env` — they change what the system *does* with values that were always there.

---

## Reachable-CLI Fixes

*   `audit:content` was fully built and tested but was never actually registered on the CLI application — it's reachable now.
*   `site:devserver`'s own help text referenced a command name (`render:site`) that has never existed; it now correctly says `site:render`.

---

## First-Party Feature Packages, All Updated

Every officially-maintained external package is migrated and released as `3.0.0`, matching StaticForge's own major version: `staticforge-chapternav`, `staticforge-google-analytics`, `staticforge-social-metadata`, `staticforge-gallery`, `staticforge-podcast`, `staticforge-popup`, `staticforge-sitedownloader`, and `answer-engine-optimization`. If your `composer.json` still pins one of these to `^2.0` or `>=1.x`, bump it to `^3.0` alongside your own `eicc/staticforge` upgrade.

---

## Nothing Else Changed

`siteconfig.yaml`, frontmatter fields, template variables, shortcode syntax, and every command's flags and output are unchanged. If you don't maintain a custom Feature, upgrading should be a version bump and nothing else.
