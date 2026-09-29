# StaticForge 3.4 — Design

Status: IMPLEMENTED in 3.4.0 (deferred: D3 atomic deploy and G HTML parser to 3.5; F2 search.split on hold). Owner decisions O1-O7 resolved 2026-09-28.
Author: Claude (orchestrator/architect). Owner: Cal Evans.
Baseline: 3.3.6 (`232e1ba`).

## Revision changelog

**Rev 3 (this revision) — security pass (11 findings, section 17 "SEC2")**
- P2 corrected: the empty slug reaches `CategoriesService::categorizeOutputPath` and the definition-filename slug in `CategoryPageService`, not `sanitizeCategoryName`'s callers I named in rev 2. Verified: definition file `..md` gives slug `.` and `...md` gives `..`. Fix specified at the real sinks (2.2).
- A: private 0700 dir, temp+rename and `proc_open` argv apply in both modes; router containment runs for every request; Host-header allowlist and 5-line error cap on the state endpoint; `--host` matrix; `OUTPUT_DIR` excluded by resolved path, `--watch` refused when it overlaps source/template dirs.
- B: category Atom/JSON use the hidden-page rules via `MetadataFlags`; `Rss2Builder` uses XMLWriter; `mb_scrub`; `feed_links` autoescaped.
- D: `upload.*` validated at runtime; `keep_releases >= 1`; a release is complete only when `manifests/<id>.json` exists; relative symlink target with exact `readlink` match; rollback id regex; canary mechanics (done by `site:upload`, not `audit:config`); `current.tmp-*` cleanup.
- Outbound HTTP client requirements (7.4); `audit:live` checks the served `staticforge-manifest.json` (12.1).
- Stale cross-reference fixed: the disposition table is section 17 (was written as 16).

**Rev 2**
- New release split: 3.3.7 (data-loss + security bug fixes) -> 3.4.0 -> 3.5 (flagged items). See section 1.
- New "Prerequisite bug fix" section: SiteUploader deletes remote files after failed uploads. The draft's "already true" claim was false and is corrected.
- A: proc_open server with non-blocking pipes, router resolves HTML itself, private 0700 dir, failure banner, scroll preservation, `--incremental` behavior verified (it does NOT wipe `public/`).
- B: separate collector (RssFeedService untouched), flat `feed:` config, hidden-page exclusion, podcast categories excluded by default, Atom via XMLWriter, JSON via `JSON_THROW_ON_ERROR`.
- C: one shared boolean helper, cascade from category definitions, `robots: no` vs `noindex` semantics settled, `lastmod` = `updated ?? date ?? mtime`, AEO interaction specified, OQ2 decided (no).
- D: reordered (prerequisite -> `--no-delete` -> delete guard -> flagged atomic), config in siteconfig `upload:`, SftpClient injected, atomic hardened.
- E: full template spec, no-JS fallback, one search box, soft-404 audit, exact manual pages.
- F: minify first; split kept as flagged design; both JS files; sub-path `indexPath`.
- G: wrapper design, serialization/`content:encoded`/re-upload consequences; recommended for 3.5.
- H: accurate map of the six implementations; per-method contracts; SEC-10 fix pulled into 3.3.7.
- `site:audit` replaced with `audit:config` / `audit:*` everywhere; new audit checks listed (section 12).
- Testing section rewritten around TEST-1..10; golden files land first.
- Disposition table for every reviewer finding (section 17).

**Rev 1**: initial draft.

## Owner decisions (resolved)

**Resolved 2026-09-28: Cal said "go with your recommendation" — the recommendation column applies to O1–O7.** O7 marker CONFIRMED 2026-09-28 from ../staticforge-podcast (3.1.0, requires `eicc/staticforge ^3.0`): a podcast category's definition file (`type: category`) carries `podcast: true` (`PodcastFeedService::handleRssBuilderInit`). Its episodes carry `audio_file` or `video_file`. The site feed excludes any category whose definition has `podcast: true` by default; `feed.exclude_categories` remains for other cases. (`../heartsntales.com` still runs core 2.0.1 with the old package and uses `rss_type: podcast`; that key is obsolete for the 3.x package and the site will need `podcast: true` when it upgrades.)

**Podcast package facts that constrain 3.4 (verified in ../staticforge-podcast/src):**
- It listens to `RSS_BUILDER_INIT` and `RSS_ITEM_BUILDING` (priority 100) and reads `$event->file['metadata']` (`audio_url`, `media_length`, `media_type`, `podcast_show_notes_html`). The category-feed path must keep providing exactly these. The site feed must NOT fire these events (its `RSS_BUILDER_INIT` handler logs a warning for a feed with empty category metadata).
- Show notes are captured at `MARKDOWN_CONVERTED` (priority 900) from the converted HTML, after the heading-id fix and after `HtmlPlaceholders::restore`. So G (parser swap) changes show-notes bytes and the enclosure/`content:encoded` of every episode, which is why G stays in 3.5. It does not depend on the full-page HTML, so the 3.3.5 content-marker change did not affect it.
- It also hooks `PRE_RENDER` (50), `PRE_LOOP`, `POST_LOOP` (110), `DESTROY` and `CREATE`. The real regression fixture for B is this package at `../staticforge-podcast` plus the real content in `../heartsntales.com/content`.

Each item stays in the design as specified.

| # | Decision | Recommendation (default) | Tradeoff |
|---|----------|--------------------------|----------|
| O1 | Release placement of **G** (HTML parser) | Ship alone in **3.5**, not 3.4 | G changes bytes of every HTML page, every podcast `content:encoded`, and forces a full re-upload on next `site:upload`. Doing it in 3.4 buries that in a feature release. |
| O2 | **Atomic deploy** strategy (D3) | Keep designed; ship in **3.5**, behind `upload.strategy: atomic`, after `--no-delete` + guard have run in the field | Highest-risk item (server layout change, conflicts with `UPLOAD_CHECK_FILE` offloading, most shared hosts can't repoint docroot). Delaying costs nothing for sites that never opt in. |
| O3 | **`search.split`** (F2) | Keep designed; **do not build until someone reports a size problem**. Ship minification only in 3.4 | Shards do not reduce initial load (engine needs all data), and inserting a page shifts later shards. Splitting only helps individual transfer size. |
| O4 | **Scope of H** | Build `category()` + `filename()` + `fileDiscovery()` as designed, but migrate only the four identical category implementations in 3.4; the `FileDiscovery` mismatch is a separate reviewed bug fix (touches `src/Core`) | Full migration is cleaner but touches public URLs in more places. |
| O5 | **Site feed default** | `feed.enabled` is **off** for existing sites (key absent), **on** in `site:init` sample config | On-by-default publishes new public feeds (and autodiscovery on every page) for existing sites, including podcast sites. |
| O6 | **`robots: no` semantics** | Keep 3.3.6 behavior (robots.txt `Disallow`, no meta). Sitemap drops those pages. `audit:seo` warns when `robots: no` and `noindex: true` are both set. `noindex: true` is documented as the de-index key | SEO reviewer wanted `robots: no` to become noindex and stop writing Disallow. That changes robots.txt output and de-indexing behavior for existing sites. |
| O7 | **Podcast category marker** | Auto-exclude from site feeds any category whose definition carries a podcast marker | **Unresolved**: the podcast package is external and I could not confirm which frontmatter key marks a podcast category. Until confirmed, only `feed.exclude_categories` is authoritative and the docs tell podcast sites to set it. |

Not decisions, but flagged for the owner: the AEO package needs changes (section 8); the `FileDiscovery::slugify` mismatch is a live link bug (section 11.4).

## 0. Ground rules

- **Podcast category feeds keep working exactly as today**: `/{category}/rss.xml`, `RssBuilder`, `FeedChannel`/`FeedItem`, `RSS_BUILDER_INIT`, `RSS_ITEM_BUILDING`, item set, unlimited count, sort. New feeds sit beside them.
- PHP only, no new Composer packages without asking, no frameworks, no Node/Python, no JS test harness.
- Everything new is a Feature or lives in `src/Services`. `src/Core` is touched only where called out (none currently planned).
- Every item ships with tests; user-facing items ship a manual page in `content/`.
- Manual pages must not contain internal labels (A/B/C, section numbers, "3.3.6-equivalent", "characterization tests").

## 1. Scope and release split

| # | Item | Release | Size | Risk |
|---|------|---------|------|------|
| P | Prerequisite: SiteUploader failed-upload delete bug | **3.3.7** | S | fixes data loss |
| P2 | Prerequisite: empty or dot-only category slug overwrites pages such as `public/index.html` (SEC-10, SEC2-1) | **3.3.7** | S | fixes home-page overwrite |
| G0 | Golden files / characterization tests for B, G, H | 3.4.0 (first commits) | M | none |
| H | Shared `Slugger` (scope per O4) | 3.4.0 | M | high (public URLs) |
| C | Sitemap exclusion | 3.4.0 | S | low |
| F1 | Minified `search.json` | 3.4.0 | S | low |
| E | 404 page + template + manual page | 3.4.0 | S | low |
| B | Site feed + Atom + JSON, beside category feeds | 3.4.0 | M | medium |
| A | Dev server watch + live reload | 3.4.0 | M | low (dev only) |
| D1/D2 | `--no-delete`, delete guard | 3.4.0 | S/M | medium |
| G | Modern HTML parser | 3.5 (O1) | M | medium |
| D3 | Atomic deploy strategy | 3.5 (O2) | L | high |
| F2 | `search.split` | on hold (O3) | S | low |

Order inside 3.4.0: G0 golden commits -> H -> C -> F1 -> E -> B -> A -> D1 -> D2. G0 lands before any refactor. Version tags follow the existing release process.

Out of scope: AEO package `.md` twins (in `documents/backlog.md`); tag feeds (`/tags/{tag}/feed.xml`) — deliberately not built, called out in the feed manual page.

## 2. Prerequisite bug fixes (ship as 3.3.7, before any 3.4 work)

### 2.1 P — Upload cleanup runs after failed uploads (DA-1 / SEC-7)

Verified in `src/Services/Upload/SiteUploader.php`: a file whose upload fails is not added to `newManifest`; `processManifestCleanup()` then runs unconditionally, and it deletes every path in the old manifest that is missing from `newManifest`. So one transient SFTP failure on a **changed** file deletes the live remote copy. Only the manifest write is gated on `errorCount === 0`.

Design of the fix (not yet implemented):
- Invariant: **files -> deletes (only if `errorCount === 0`) -> manifest (only if `errorCount === 0`)**.
- When `errorCount > 0`: skip cleanup entirely, print "N upload errors; skipping remote deletions", exit non-zero as today.
- Belt and braces: a path whose upload failed is added to a `keep` set and excluded from the stale set even if cleanup is later re-enabled by a code change.
- Regression tests (mock `SftpClient`): one failed upload -> zero deletes; zero errors -> deletes happen; dry run never deletes; failed upload of a new file (not in old manifest) -> no deletes.
- The draft's claim that upload order is "already true" is withdrawn; the order was upload, delete, manifest.

### 2.2 P2 — Category slug can be empty or dot-only (SEC-10 / SEC2-1)

Verified against `src/` (rev 2 aimed at the wrong sink):

| Sink | Behavior today |
|------|----------------|
| `CategoryPageService::deferFile` (~line 51) and `renderCategoryPage` (~line 90) | Slug is `pathinfo($filePath, PATHINFO_FILENAME)` of the category **definition file**, not `sanitizeCategoryName`. Output is `OUTPUT_DIR/<slug>/index.html`. A definition file named `..md` yields slug `.` and `...md` yields `..` (checked with PHP). `.` resolves inside `OUTPUT_DIR`, so `PathGuard` (used by `OutputWriter`) does not stop it: `public/./index.html` overwrites the home page. `..` resolves outside and is already rejected by `PathGuard` where the write goes through `OutputWriter`. |
| `CategoriesService::categorizeOutputPath` (~line 154), called from the Categories feature at PRE_RENDER-path and POST_RENDER (priority 100) | `sanitizeCategoryName` returns `''` for a category like `!!!`; the path becomes `dirname//file`, i.e. the page is written **uncategorized** into its own directory and can overwrite a same-named page. |
| `CategoriesService` first pass (~line 37-43) | Template map key is the definition filename slug (same `.`/`..` case). Lookup only; no write. |
| `TemplateRenderer.php:57` (`slugifyCategory`) | Template lookup only; an empty slug just misses the map. Not a write sink (the security agent listed it as one; modified). Cheap guard: skip the lookup when the slug is empty. |
| `RobotsTxtService` (~line 163) | Empty slug produces `Disallow: //`; a dot-only filename slug produces `/./`. |

3.3.7 fix (small, no URL change, `Slugger` not required):
- **Rule**: a category slug is *unsafe* when it is `''` or consists only of `.` characters. (Rev 2's suggested strict `^[a-z0-9]+(-[a-z0-9]+)*$` is deliberately **not** applied to definition filenames in 3.3.7: it would reject existing valid names such as `My_Cat.md` and change URLs. The strict postcondition arrives with `Slugger::category()` in 3.4 after owner sign-off, section 11.3.)
- `categorizeOutputPath`: throw `\InvalidArgumentException` when `sanitizeCategoryName` is empty. The Categories feature listeners let it surface as a per-file build error (page skipped, error reported, build continues; confirm at implementation that the loop reports and continues, otherwise catch, log ERROR and leave the output path unchanged).
- `CategoryPageService::deferFile`: throw for an unsafe filename slug, same handling.
- `RobotsTxtService`: skip the rule for an unsafe slug and log a warning.
- `TemplateRenderer` / `CategoriesService` first pass: skip the template lookup/registration for an unsafe slug.
- `OutputWriter`/`PathGuard` already jail writes inside `OUTPUT_DIR`; no change (it cannot stop `.`, which is why the slug check is needed).
- Tests: definition files `..md`, `...md` and category `!!!` each produce a reported error and leave `public/index.html` and same-named root pages untouched; `My_Cat.md` still works; RobotsTxt emits no `//` or `/./` rule.

## 3. A — Dev server watch mode and live reload

### 3.1 Behavior
`site:devserver --watch` (opt-in; without the flag the server behaves as today, apart from the both-modes hardening in 3.2). Rebuilds when anything under `SOURCE_DIR`, `TEMPLATE_DIR`, `siteconfig.yaml`, `siteconfig.d/` or `.env` changes, then the open tab reloads. `--include-drafts` is passed through to the build if given to the dev server.

### 3.2 Design

| Concern | Design |
|---------|--------|
| Event loop | Existing `popen` + blocking `fgets` cannot run a watcher (nothing prints when idle). With `--watch`, `startServer()` uses `proc_open` with an argv array (no shell), stdout/stderr pipes set non-blocking, and `stream_select` with a 100 ms timeout. The watcher ticks every 500 ms. Without `--watch` the event loop stays blocking, but the "Both modes" hardening below applies (SEC2-2). Windows-native is unsupported for `--watch` (`stream_select` on proc pipes); Lando and WSL2 are supported. |
| Watcher | `WatchLoop` (pure state machine) + `FileSignatureProvider` (path+mtime+size, no symlink following, no inotify). Excludes `.staticforge-build`, the private temp dir, and the **resolved `OUTPUT_DIR`** (not the literal `public/`) (SEC2-11). `--watch` **refuses to start** (clear error) if the resolved `OUTPUT_DIR` is inside `SOURCE_DIR` or `TEMPLATE_DIR`, or equal to or a parent of either, because every build would retrigger itself and `--clean` would loop. Missing `siteconfig.d/` is fine. |
| Rebuild | `BuildRunnerInterface` (`start`, `isRunning`, `exitCode`, `output`) implemented over `proc_open` of `PHP_BINARY bin/staticforge.php site:render --incremental` (argv array, cwd = app root, non-blocking pipes). One build at a time; changes during a build queue exactly one follow-up; 300 ms debounce. |
| `public/` during rebuild | Verified: `site:render` only wipes the output directory with `--clean`; `--incremental` does not. So the server keeps serving the previous files and they are overwritten in place (a page can be briefly half-written; the client retries on next poll). "Last good output" is therefore true for edits and adds. |
| Deleted/renamed sources | Incremental leaves orphan outputs in `public/` (DA-11) and they would be served and deployed. When the signature diff includes a deleted or renamed source path, the follow-up build runs with `--clean` (brief 404 window, covered by the "Rebuilding" indicator). Terminal logs why. |
| Terminal output | One line per rebuild: `OK`/`FAILED`, duration, first changed path (UX-17). Line states when a change forces a full rebuild: config, templates, `.env`, or a deletion (UX-18). Requests to `/__staticforge/` are filtered from the server's request log lines and the reload poll is never logged (UX-15). |
| Both modes: private state dir (SEC-2, SEC2-2) | Applies with **and without** `--watch`. `mkdir(sys_get_temp_dir()/staticforge-dev-<bin2hex(random_bytes(8))>, 0700)` (fail if it exists; verify owner/mode after creation). Router file (and `state.json` in watch mode) live inside; written via temp file + `rename`; the whole dir is removed on cleanup and on SIGINT/SIGTERM where `pcntl` is available. Replaces the predictable `/tmp/staticforge-devserver-router-<pid>.php` written with `file_put_contents` (DevServerCommand.php:50, follows symlinks). The server is started with `proc_open` and an argv array in both modes (no shell string); the non-watch loop may keep reading the blocking stdout pipe. |
| Router | Generated router is written from a real class (`DevServerRouter`, testable in-process), used in both modes. **For every request, in both modes** (SEC-1, SEC2-3): rawurldecode, reject NUL, map `/` and directory paths to `index.html`, `realpath()` the candidate and require it to equal or start with `realpath(docroot) . '/'`. A path that is outside the root or does not resolve (including a symlink in `public/` pointing outside) is a 404; `return false` (php -S serves natively) happens **only after** containment passes. In `--watch` mode `.html` results are read, injected, and sent with `Content-Type`, `Content-Length`, `Cache-Control: no-store`. Without `--watch` there is no injection and no state endpoint. `404.html` is served with an explicit 404 status from a fixed path only (DEV-11). |
| Injection | Inserted before the last `</body>` (else appended), HTML only. `public/` never contains it. |
| Reload endpoint | `GET /__staticforge/state` returns `{"v":N,"status":"ok|building|failed","error":"first lines"}`; GET only, no CORS, `no-store`. No HTTP request can trigger a build. **Host allowlist** (SEC2-4, DNS rebinding): every `/__staticforge/*` request returns 403 unless the `Host` header (port stripped, case-insensitive) is the bound host, `localhost`, `127.0.0.1` or `[::1]`, or, when `LANDO=ON`, the app's Lando proxy hostname read from the environment (not hard-coded; confirm the variable, e.g. `LANDO_INFO`, during implementation). **Error text**: `error` is at most 5 lines and 500 characters, with every occurrence of `app_root` replaced by `.`; never config values or the environment. |
| Concurrency | Short polling only. `PHP_CLI_SERVER_WORKERS` is not used (fork-based, Unix-only, unnecessary). Open question 4 closed. |
| `--host` guard (SEC2-4) | Under Lando the container's `localhost` is usually not reachable from the host browser, so users pass `--host=0.0.0.0`. After SEC-1/SEC2-3 the exposed surface is only the docroot (which `php -S` already serves) plus the Host-checked, read-only state endpoint. Rules for `--watch` with a non-loopback host (loopback = `127.0.0.1`, `localhost`, `::1`): without `--allow-remote` and **not** under Lando (`LANDO=ON`): **refuse**; without `--allow-remote` under Lando: run with a warning; with `--allow-remote` anywhere: run with a warning that build errors and the site are reachable from the network. The manual documents the Lando invocation. Non-watch `--host` behavior is unchanged. |

### 3.3 Injected client script (about 20 lines, all identifiers prefixed `sfdev`)
- Poll `/__staticforge/state` every 1 s with `cache: 'no-store'`; first response records baseline, never reloads; reload when `v` changes.
- Skip polling while `document.hidden`; on fetch failure back off to 5 s and show "Dev server disconnected".
- Before `location.reload()`: store `scrollY` in `sessionStorage`, restore after load; do not reload while a form field has focus (defer until blur) (UX-14).
- `status: building` shows a small "Rebuilding..." indicator, `role="status"`, `aria-live="polite"`. `status: failed` shows a fixed, dismissible banner, `role="alert"`, icon plus text: "Build failed - showing last good version" and the first error lines; cleared on next success (UX-12/13).
- Banner rendered in a shadow root so site CSS cannot clash (UX-16). All text set via `textContent` (never HTML).

### 3.4 Tests
- Unit: `ClockInterface`, `FileSignatureProvider`, `BuildRunnerInterface` seams. `WatchLoop`: burst inside 300 ms = one build; change during build queues exactly one follow-up; delete/rename triggers clean build; same-size mtime change detected; excluded paths never trigger; symlink loop; missing `siteconfig.d/`.
- Router in-process: injection only for text/html and only with `--watch`; no `</body>`; multiple `</body>`; non-HTML passthrough (`false`); `../` and encoded traversal and NUL rejected; directory index; `/foo` vs `/foo/`; state endpoint before first build, missing/corrupt state file; 404 status with body.
- `--host` table: 127.0.0.1, localhost, ::1, 0.0.0.0, LAN IP, with/without `--allow-remote`, with/without Lando env (refuse vs warn vs run).
- Router in both modes: symlink in docroot pointing outside returns 404 (not `false`); non-HTML `../` traversal 404; `/__staticforge/state` with a foreign `Host` header returns 403 (also a rebinding hostname); error text capped at 5 lines with `app_root` replaced by `.`.
- Private dir: mode 0700, random name, pre-existing symlink at the old predictable path is never followed, dir removed on exit, argv array used in both modes.
- Watcher: `OUTPUT_DIR` outside `public/` is excluded and does not retrigger; `--watch` refuses when `OUTPUT_DIR` is inside or equals `SOURCE_DIR`/`TEMPLATE_DIR`.
- Integration: start the real server against a fixture docroot, fetch a page and the state endpoint.
- Template-only edit under `--watch` yields changed output (fingerprint covers `TEMPLATE_DIR` since 3.3.5).

## 4. B — Feeds: site feed, Atom, JSON, beside category feeds

### 4.1 Compatibility contract
`RssFeedService` (including `collectCategoryFiles`, its private state, and `sanitizeCategoryName`) is **not modified**. No existing event (`RSS_BUILDER_INIT`, `RSS_ITEM_BUILDING`) fires for new feeds. Verified: nothing in this repo listens to `RSS_*`; the podcast package is external, so compatibility is proven by a golden snapshot of a category feed generated by 3.3.6 code, plus a run against the owner's real podcast package and content before release (manual gate).

### 4.2 Outputs (off unless enabled; off = zero files written)

| Path | Format |
|------|--------|
| `/feed.xml` | RSS 2.0, site-wide |
| `/feed.atom` | Atom 1.0 |
| `/feed.json` | JSON Feed 1.1 |

Optional, default off: `/{category}/feed.atom`, `/{category}/feed.json` via `feed.category_formats`. Category RSS is unchanged. **Item set for category Atom/JSON** (SEC2-8): the category's pages as the category RSS selects them (unlimited count, same sort), **minus** hidden pages: `draft`, `noindex`, `robots: no`, `sitemap: false`, the 404 page, generated Tags/CategoryIndex pages, evaluated with `MetadataFlags` (`isTrue`/`isFalse`/`robotsBlocked`). Categories listed in `exclude_categories` get **no** Atom/JSON files. `feed: false` remains a site-feed-only key. Category `rss.xml` is not modified; whether it already includes hidden pages was not verified and is out of scope here (it must stay byte-identical, section 4.1). Feeds are not listed in `sitemap.xml` or `search.json`, and are never Disallowed.

### 4.3 Config (`siteconfig.yaml`, flat singular key like `search`, `tags`)
```yaml
feed:
  enabled: false            # absent/false = feature inert; site:init sample sets true (O5)
  limit: 20                 # site feeds only
  formats: [rss, atom, json]
  exclude_categories: []    # category slugs (Slugger::category), e.g. [podcast]
  category_formats: [rss]   # adding atom/json emits those beside category rss.xml
```
Category feed item count stays unlimited; no `category_limit` key (YAGNI). `audit:config` validates types (`limit` positive int, `formats` a list of known names, etc).

Frontmatter `feed: false` excludes a page from **site** feeds only. Caution: page frontmatter is merged into template variables, so the template variable for autodiscovery is named `feed_links`, not `feed`, to avoid a clash with the frontmatter key and with the flattened `feed` config.

### 4.4 Candidate rules (site feeds)
A page is a candidate when it has a `date` (checked directly in metadata; the 1970/mtime fallback used by the category path is not used) and either a `category` or `feed: true`. Every exclusion is evaluated through `MetadataFlags` (`isTrue`, `isFalse`, `robotsBlocked`), never ad hoc casts (SEC2-8). Excluded regardless: `draft`, `noindex`, `robots: no`, `sitemap: false`, `feed: false`, the 404 page, generated Tags/CategoryIndex pages, categories in `exclude_categories`, and (once O7 is settled) podcast categories. Tie-break on equal dates: path ascending. Timezone injected.

**As built (3.4.0), fail-closed and edge rules:** hidden-page flags fail closed (an unrecognised non-null value of `draft`, `noindex`, `sitemap` or `robots` hides the page); pages whose category cannot be slugged by `Slugger::category()` are excluded from all new feeds with a warning; pages without ContentMarkers are skipped with a warning (never publish the whole templated page); the 404 page is matched by basename `404.html` or `template: 404` in feeds; category Atom/JSON list only dated pages (Atom requires `updated`; no invented dates), unlike category `rss.xml` which keeps its mtime fallback; item URLs are percent-encoded per path segment; an empty Atom feed uses a fixed `updated` of `1970-01-01T00:00:00Z`; `feed: false` is site-feed-only. Verified on ../heartsntales.com content: category `podcast/rss.xml` is byte-identical (except `lastBuildDate`) between 3.3.7 and 3.4 with feeds off and on, and `podcast: true` on the category definition keeps episodes out of the site feeds.

### 4.5 Class structure (`src/Features/SiteFeed/`)
- `Feature.php` — registers POST_RENDER collector and POST_LOOP writer; reads `feed` config (`ConfigurableFeatureInterface`).
- `Services/SiteFeedCollector` — POST_RENDER listener; builds `SiteFeedItem` DTOs from event metadata and the already-extracted article body (ContentMarkers), URLs absolutized as in 3.3.5. Independent of `RssFeedService` state.
- `Services/FeedBuilderInterface` with `Rss2Builder` (small, own implementation over **XMLWriter**; the existing `RssBuilder` is not reused so no shared behavior can drift), `AtomBuilder` (XMLWriter), `JsonFeedBuilder` (`json_encode(..., JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)`).
- `Services/SiteFeedWriter` — POST_LOOP; writes via `OutputWriter`; with no items it writes an empty valid feed (subscribers see an empty feed, not a 404).
- Events: new `SITE_FEED_INIT` and `SITE_FEED_ITEM_BUILDING` (payload mirrors the RSS events). Documented in `development/events.md`.
- Item `id`/`guid`/`url` = canonical absolute URL; `updated`/`date_modified` = frontmatter `updated ?? date`.
- XML (RSS 2.0 and Atom): all text through XMLWriter (`writeElement`, never string concat or CDATA). Text pipeline, in order: `mb_scrub($text, 'UTF-8')` (replaces invalid UTF-8), strip XML-1.0-invalid control characters, then encode. Illegal `]]>` cannot break out. JSON Feed applies the same `mb_scrub` before `json_encode`, so one bad byte in a page cannot make `JSON_THROW_ON_ERROR` fail the whole feed (SEC2-9).

### 4.6 Autodiscovery and template variable
- `TemplateVariableBuilder::ALLOWED_CONTAINER_VARIABLES` gains `feed_links`. The feature sets a container variable `feed_links` (list of `{type, title, href}`; absolute URLs; types `application/rss+xml`, `application/atom+xml`, `application/feed+json`; distinct titles).
- `feed_links` values are rendered with Twig autoescape (attribute context) and never with `|raw` (SEC2-9).
- staticforce and sample `base.html.twig` emit `<link rel="alternate">` directly in `<head>` guarded by `{% if feed_links %}`, not inside child-overridable blocks (`extra_head`/`head`), so child templates cannot lose them.
- Category pages additionally advertise their own category feed (existing behavior kept; not changed).

### 4.7 Tests
Golden files per format (captured after the category golden commit); JSON Feed required fields; Atom well-formed with required elements; category feed byte-for-byte snapshot (committed before any B code); podcast-listener spy receives zero calls from site feeds; undated pages; `feed: false` vs `feed: true` without category; `limit: 0`; empty item set; `exclude_categories` naming a missing category (compared as slugs); `updated` vs `date`; `]]>` and control characters; emoji in JSON; sub-path base URL; collision with existing `content/feed.xml`, `feed.atom`, `feed.json` (existing output wins, warning); hidden-page exclusions (each key); all formats off = zero files; deterministic ordering; category Atom/JSON exclude each hidden-page key and `exclude_categories`; quoted `"false"` handled via `MetadataFlags`; invalid UTF-8 byte in title/body still yields valid RSS, Atom and JSON; `feed_links` value containing `"><script>` is escaped.

## 5. C — Sitemap exclusion

### 5.1 Rule
A page is omitted from `sitemap.xml` when any of: `sitemap: false`, `noindex: true`, `robots: no`, it is the 404 page (detected by output path `404.html`, no frontmatter needed), draft, or generated non-200/redirect output. `search_index: false` does **not** affect the sitemap (OQ2 decided: no; they answer different questions).

### 5.2 Shared boolean helper (FR-5)
New `src/Services/MetadataFlags` (`isFalse($v)`, `isTrue($v)`, `robotsBlocked($v)`), based on `FILTER_VALIDATE_BOOLEAN` with `FILTER_NULL_ON_FAILURE` like `draft`. Used by `sitemap`, `noindex`, `feed`, and adopted by `search_index` (today strict `=== false`; quoted `"false"` becomes honored — release-noted). `robots` keeps its own parse (`strtolower(trim()) === 'no'`) **plus** the YAML 1.1 case: YAML parses bare `no` as boolean `false`, so `robots: false` and `robots: no` both mean blocked. This is the single source for `RobotsTxtService` and `SitemapService`. Tests: `"false"` vs `false`, `robots: no` as parsed YAML, `sitemap: true` vs `noindex: true` (exclusion wins), 404 excluded without frontmatter, empty sitemap is valid XML.

### 5.3 Cascade
`noindex`, `sitemap: false` and `robots: no` on a `type: category` definition cascade to that category's generated index pages (RobotsTxt already does this in `scanCategoryFiles`; sitemap gets the same logic through the shared helper).

### 5.4 `robots: no` vs `noindex` (SEO-2/4, FR-4)
- `robots: no` = crawl block: unchanged, robots.txt `Disallow` (O6). Sitemap drops it.
- `noindex: true` = de-index: `<meta name="robots" content="noindex, follow">` in both bases; **never** produces a Disallow (a blocked crawler cannot see the meta). Non-HTML files need an `X-Robots-Tag` header, documented in the manual.
- Both set: `audit:seo` warns "Disallow hides the noindex from crawlers".
- Known mismatch to test and fix: `RobotsTxtService::calculateWebPath` disallows `/foo/index.html` while the sitemap URL is `/foo/`. The precedence test exposes it; fix in the shared helper path.

### 5.5 `lastmod` (SEO-9, FR-11)
`updated ?? date ?? source mtime ?? today`. Today's behavior (verified) is `date`, else mtime, else today; it ignores `updated`. Change is release-noted. Never a build timestamp except the last-resort default. Robots.txt `Sitemap:` line stays absolute. Robots.txt's per-render `# <datetime>` header (see `plan_cloudflare_pages.md`) is not touched here.

## 6. D — Safer deploys

### 6.1 Config location (FR-1)
Credentials stay in `.env` (`UPLOAD_URL`, `SFTP_*`, via `SftpConfigLoader`). Non-secret behavior goes in `siteconfig.yaml` under a flat `upload:` key (naming consistent with `site:upload`):
```yaml
upload:
  max_delete: null       # null = max(10, 25% of manifest)
  strategy: in_place     # in_place | atomic (3.5)
  keep_releases: 3       # atomic only
```
CLI flags override config. **Runtime validation** (SEC2-6): `site:upload` validates `upload.*` itself before any remote action and aborts on bad values (advisory `audit:config` runs the same validator): `max_delete` must be null or a non-negative integer; `strategy` one of the two names; `keep_releases` an integer `>= 1`. Prune always keeps the current release and the rollback target regardless of `keep_releases`. In `in_place` mode `staticforge-manifest.json` sits in the served tree, protected only by `.htaccess` (ignored by nginx/Cloudflare); `audit:live` checks it (12.1) and the manual documents it (SEC2-10). Extraction note (CLAUDE.md section 6): `SiteUploader` lives in `src/Services/Upload`, so D is not self-contained; recorded, not fixed.

### 6.2 Order of work and guards
1. **Prerequisite fix** (section 2.1, 3.3.7).
2. **D1 `--no-delete`**: upload and update; never delete remotely. Manifest still records stale entries so a later run without the flag cleans up.
3. **D2 delete guard**: if the stale set exceeds `upload.max_delete` (default `max(10, 25% of manifest)`), abort before deleting unless `--force-delete`. Interactive TTY without `--no-interaction` gets y/N; non-TTY and `--no-interaction` are non-interactive and abort. Dry run reports delete count and whether the guard would trip. Tested thresholds: manifest sizes 0, 1, 40, 41. Deliberate behavior change for pathological runs only.
4. **D3 atomic (flagged, O2)**, section 6.3.

Dependency injection (DEV-8): `UploadSiteCommand` currently does `new SftpClient(...)`. Inject `SftpClientInterface` from the container (also gives testable seams and lets the in-memory fake replace it). `SftpClientInterface`: `uploadFile`, `deleteFile`, `symlink`, `readlink`, `isLink`, `rename`, `posixRename`, `rmdir`, `capabilities()`.

`UPLOAD_CHECK_FILE` (DA-7): the event stays as is for `in_place`. In `atomic`, listeners that set `skipUpload`/`handled` mean "remote copy stays", which cannot hold in a fresh release directory. Rule: `atomic` refuses to run (clear error) if any `UPLOAD_CHECK_FILE` listener is registered, unless `--atomic-ignore-plugins` is given (then plugin-owned paths are documented as not carried into new releases).

### 6.3 D3 Atomic strategy (flagged; design retained)
```
<remote>/releases/<release-id>/...     # full upload
<remote>/current -> releases/<release-id>
<remote>/manifests/<release-id>.json   # OUTSIDE the served tree
```
- **Release id** (SEC-6): generated locally as `<build_id>-<8 hex>` using the `build_id` from the temporary full build (`CacheBusterService::generateBuildId()`, a unix timestamp; `site:upload` always does a fresh full temp render, FR-2). Pattern `^\d{10}-[a-f0-9]{8}$`. Never derived from remote data.
- **Upload**: full upload to the new release (D-1 decided: full upload; server-side copy is not universal and is not built). An interrupted upload leaves `current` untouched.
- **Complete-release rule** (SEC2-5): a release is *complete* only when `manifests/<id>.json` exists, written **last** (temp name then rename) after the upload finished with `errorCount === 0`. Only complete releases are candidates for swap, rollback, or the keep set. A regex-valid `releases/<id>/` with no manifest is *partial*: never a candidate, never `current`'s target; it is removed at the start of the next atomic run using the regex, `lstat`-based delete and the delete guard. Pruning deletes the manifest **first** (release becomes partial, so an interruption is safe), then the directory.
- **Swap**: create `current.tmp-<id>` as a symlink whose target is the **relative** string `releases/<id>` (never absolute), then `posix_rename` over `current` (plain `SFTP::rename` fails if the target exists). If `posix-rename@openssh.com` is unavailable, **abort** unless `--allow-nonatomic` (then remove+create with a warning about a window with no site) (SEC-9). After every swap `readlink current` must equal the string `releases/<id>` **exactly**, else the run fails loudly. Verify phpseclib `symlink()` argument order against a real server before release (OpenSSH reverses the order).
- **Temp-link cleanup**: at the start of each atomic run and after any failure, entries named `current.tmp-*` that match `^current\.tmp-\d{10}-[a-f0-9]{8}$` are removed only after `lstat` reports a symlink (`unlink` only; never rmdir or recursive).
- **Prune/rollback** (SEC-6): only entries matching the id regex are considered; `readlink(current)` must equal `releases/<valid-id>` of a complete release; never prune current, the rollback target (newest complete release older than current), or anything newer than current. Recursive delete implemented with `lstat`, symlinks deleted as links and never descended, no phpseclib recursive delete. The delete guard applies to prune and partial-release cleanup too. `site:upload --rollback <id>`: the argument must match `^\d{10}-[a-f0-9]{8}$` (validated before any remote call), the release must be complete, exist inside `<remote>/releases/`, and differ from current; then the same swap and `readlink` verification.
- **Server requirements and canary** (SEC-8, SEC2-7): symlinks allowed; the web root must be `<remote>/current`. If the web root is `<remote>`, every release is web-reachable. Mechanics: `site:upload` writes an empty file `releases/<id>/canary-<32 hex>.txt` (random name, not a dotfile so servers that deny dotfiles cannot cause a false negative, not in the manifest) before the swap, and after the swap requests `<SITE_BASE_URL host>/releases/<id>/canary-<hex>.txt` using the HTTP client rules in 7.4. A 200 prints a loud warning that all releases are public and that rollback republishes removed content; other results pass; an unset `SITE_BASE_URL` prints a notice and skips. The check is a `site:upload` step, not part of the offline `audit:config` (modified from the security agent's wording). The canary is removed with its release on prune. Manual states rollback re-publishes removed content.
- Hand-placed files (`.well-known`, `.htaccess` edits) that are not in the manifest do not exist in a new release. Documented; a `upload.carry_over` list is not built (YAGNI) unless requested.

### 6.4 Tests
`SftpClientInterface` with an in-memory fake remote FS in `tests/Mocks/` (ordered call log; failure injection at Nth call, dropped connection, permission error, no posix-rename). Injected build-id generator, clock, `PromptInterface`, TTY check. Cases: failed upload -> no deletes; threshold 0/1/40/41; `--no-delete` then later cleanup; guard non-interactive abort; dry run makes no remote writes; `../` manifest paths rejected; atomic: swap sequence, verification `readlink`, no posix-rename abort, interrupted upload leaves `current`, rollback with one release / missing target / target outside `releases/`, rollback arg failing the id regex (`../x`, empty, uppercase hex) rejected before any remote call, prune never removes current or rollback target, `keep_releases` 0 and negative rejected at runtime, `keep_releases: 1` keeps current plus rollback target, `max_delete` non-int/negative rejected, `UPLOAD_CHECK_FILE` refusal; partial release (no manifest) is never a swap/rollback/keep candidate and is cleaned next run; manifest written only after `errorCount === 0`; leftover `current.tmp-*` removed only when `lstat` says link and the name matches; symlink target is relative and `readlink` mismatch fails; canary written, checked after swap, 200 warns, unset `SITE_BASE_URL` skips.

## 7. E — 404 page

### 7.1 Deliverables
1. `templates/staticforce/404.html.twig` and `templates/sample/404.html.twig`.
2. `content/404.md` convention: `template: 404` produces `public/404.html` through the normal pipeline. No new Feature.
3. `make:htaccess` adds `ErrorDocument 404 /404.html` (local path only — a full URL makes Apache issue a 302) with a comment on sub-path installs. Existing `.htaccess` files are never modified (byte-compare tested).
4. Dev server router serves `public/404.html` with explicit status 404 when present, else the current built-in page (path stays HTML-escaped).
5. `site:init` seeds `content/404.md` for **new** sites only. Existing sites create the file themselves (documented).
6. Manual pages (section 13).
7. `audit:live` soft-404 check (7.4).

### 7.2 Template spec (staticforce; sample equivalent)
- Extends `base.html.twig`, overrides `body` like `standard_page` (single column, no sidebar, no `menu1`); navbar, footer and stylesheet kept; no new CSS beyond an optional `.error-404` block reusing existing variables; light theme only; no inline `<style>`. Sample theme has no `standard_page`, so its 404 is self-contained inside its base.
- Content order: muted "404" label (AA contrast checked against `variables.css` tokens) -> H1 "Page not found" -> one sentence -> one primary button "Go to the homepage" -> labeled search block -> "Popular sections" list. Plain brief tone; copywriter owns final text.
- **One search box** (UX-3): the page overrides the `search_bar` block to empty and uses one in-body search with a visible `<label>`, reusing `#search-input`/`#search-results` (no duplicate IDs). Autofocus except on touch devices. Keep `search_scripts`.
- **No-JS fallback**: `<noscript>` shows the link list plus a sitemap link; search block hidden without JS.
- **Popular sections data** (UX-5): `404_links` list in `siteconfig.yaml` if present, else top-level menu items (`menu_top`), else home link only; absent menus handled. No invented popularity.
- **URLs** (FE-1, UX-6/11): every href/src and the search `indexPath` come from `site_base_url`, which the page requires to be set (absolute or including the sub-path). `audit:config` warns when a 404 exists and `SITE_BASE_URL` is empty or host-relative.
- **Head**: `title`/`description` from frontmatter; `<meta name="robots" content="noindex, follow">` via the shared `noindex` variable; no canonical, no meta refresh (SEO-6).
- **A11y**: one H1; links inside `<nav aria-label="Helpful links">`; focus styles; body 16px+; 44px touch targets on mobile, full-width button and input; decorative images `alt=""` or none.
- **Security** (SEC-12): scripts never write `location.pathname`/`location.search` into the DOM as HTML.

### 7.3 Sample `content/404.md` frontmatter defaults
```yaml
title: 'Page not found'
template: 404
noindex: true
sitemap: false
search_index: false
no_llms: true        # AEO package opt-out (external); keeps 404 out of llms.txt and the .md twin
```
`sitemap: false` and `noindex: true` are set explicitly in the sample, but core also excludes the 404 page by output path (section 5.1), so a hand-written 404 without them is still safe. `search_index: false` is also needed (the example search config excludes `/404.html` only by path).

### 7.4 Soft-404 audit (SEO-5) and outbound HTTP requirements (SEC2-7)
`audit:live` requests a random nonexistent URL under the site and asserts status 404; failure explains SPA-style rewrites returning 200. Never redirect unknown URLs to `/404.html` or home. The manual states this and lists per-host behavior.

All outbound checks (soft-404, feed autodiscovery, manifest exposure, atomic canary) go through one injected `HttpProbeInterface` (container-provided, fakeable in tests) with these requirements:
- TLS certificate verification on (as `audit:live` already does since `232e1ba`). **As built (3.4.0):** the soft-404 probe honours the existing explicit `--insecure` opt-in exactly like the other live checks (which warn when it is set); without that flag verification is always on. The stricter "never by a flag" rule was not applied because it would make one check behave differently from the rest of `audit:live`.
- 10 s total timeout (connect and read).
- Redirects are **not followed**. A 3xx on the random URL is a soft-404 failure ("unknown URLs redirect"), not a pass.
- Requests only to the host of `SITE_BASE_URL`; discovered links (autodiscovery `href`) pointing at any other host are reported and not fetched.
- Status is checked before the body is read; bodies are size-capped (for example 1 MB) and only fetched where the check needs them.

### 7.5 Tests
Template renders with and without menus, with sub-path base URL, no duplicate IDs, noscript block present; sitemap/search/feeds exclude it; llms.txt exclusion is an AEO-package test (section 8); dev-router status and body with hostile path; `make:htaccess` byte-compare on existing file; `InitCommand` seeds only new sites; `audit:live` soft-404 with a fake HTTP client (200 fails, 301/302 fails, 404 passes; cross-host autodiscovery href not fetched; timeout and TLS failure reported).

## 8. AEO interaction (AEO package is external)

**Core does:** shipped 404 frontmatter sets `no_llms: true` (7.3); core exposes the shared `MetadataFlags` helper and documents that `feed: false`, `sitemap: false`, `noindex` and `no_llms` are separate knobs; feeds use the same ContentMarkers body the twins should use; `make:htaccess` gains an optional `X-Robots-Tag: noindex` rule for `*.md` twins (behind a flag) (AEO-2); the AEO-package-independent test for the golden site: sitemap URLs equal the URLs the AEO package would list (AEO-7).

**AEO package needs (not in this repo, logged in `documents/backlog.md`):**
1. Treat `noindex: true` and `robots: no` as implying `no_llms`; also skip the 404 page and pages excluded by `sitemap: false` (AEO-1/2).
2. `dateModified` from frontmatter `updated ?? date`, not mtime (AEO-5).
3. Optional `## Optional` section in llms.txt linking `/feed.json` and `/feed.xml` (AEO-3).
4. Twin body reuses the ContentMarkers body (AEO-4).
5. After G, verify JSON-LD blocks, `rel="llms"` and `rel="sitemap"` survive the `</head>` string replacement (AEO-9).
Do not Disallow `search*.json`; `search_index` stays independent of `no_llms` (AEO-8).

## 9. F — Smaller search index

**F1 (3.4.0):** `search.json` written with `json_encode` without `JSON_PRETTY_PRINT` (verified at `SearchIndexService`), keeping `JSON_UNESCAPED_UNICODE` as today. Decode round-trip equals the old pretty output.

**F2 (on hold, O3):** `search.split: true` (default false) writes `search.json` as `{ "version": 2, "shards": [...] }` plus shard files of about 200 KB, assigned by stable page order. Known tradeoff: initial load is not reduced; insertion shifts later shards. If built: both `src/Features/Search/assets/js/search.js` and `search-fuse.js` change together; loader accepts a flat array or the manifest, resolves shards via `new URL(shard, new URL(indexPath, location.href))`, `Promise.all`, concat, single `addAll`, ids globally unique, a missing shard shows an error rather than a silent partial index; CacheBuster query-string behavior extended to `search-N.json`.

**Both, regardless of F2:** `indexPath` in both JS files stops being hard-coded `/search.json` and is derived from `site_base_url` (sub-path installs); the existing `console.log('... Loaded')` noise removed; both files confirmed to render results with `textContent`.

**Tests (no JS harness, FE-9/TEST-6):** PHP tests for compact valid JSON, unicode unchanged, empty index, and (if F2 built) shard boundaries (exactly 200 KB, oversized page), stability across add/remove. A PHP test greps the shipped JS for `version`/`shards` handling and the `site_base_url`-derived path; a manual browser check is on the release checklist.

## 10. G — Modern HTML parser (recommended release: 3.5, O1)

Replaces four `DOMDocument::loadHTML('<?xml encoding...')` sites (TOC `TableOfContentsService`, `MarkdownRendererService` heading ids, `HtmlImageRewriterService` incl. its `<?xml` strip, `SearchIndexService`) with `Dom\HTMLDocument` behind `src/Services/HtmlDocumentInterface` and a wrapper.

**Wrapper design (DEV-6):** `createFromString` always builds `html/head/body` and has no NOIMPLIED equivalent. `fromFragment()` therefore parses `<!DOCTYPE html><html><body>{fragment}</body></html>` and `serializeFragment()` returns the body's inner HTML, so fragments never gain wrappers. `fromDocument()` for full pages. `xpath()` via `Dom\XPath`. `\DOMElement` checks become `Dom\Element` (unrelated classes). HTML5 only: no DTD/entity processing, XXE not applicable; `Dom\XMLDocument`/`loadXML` is banned for content. Serialize with the HTML serializer.

**Consequences to accept or reject at release time (DA-4):**
- Serialization differs (`<br>` vs `<br />`, entity forms, attribute quoting, whitespace), so generated HTML changes; `beautifyHtml()` (dindent) output must be checked for idempotence.
- Podcast: category-feed `content:encoded` is rendered HTML that passes through the heading-id fix, so **its bytes change** for every episode. The 4.1 byte-for-byte guarantee holds for 3.4.0 and is explicitly waived for G's release; this is release-noted and subscribers may see re-delivered items depending on how their client hashes content. This is why G ships alone.
- Every HTML page hash changes, so the next `site:upload` re-uploads the whole site (release-noted).
- Search extraction may change for `<template>`/`<noscript>` content.

**Tests (TEST-8):** characterization tests and golden files committed and passing before any parser change; corpus per call site (entities, `&nbsp;`, void elements, boolean attributes, nested inline, unicode, script/style, comments, malformed, empty); full docs-site build snapshot; a separate approved-differences golden set; no `<?xml` prefix; no html/body wrapper for fragments; hostile doctype/entity fixture; JSON-LD/`rel` assertions from 8.

## 11. H — Shared `Slugger`

### 11.1 Accurate map of the six implementations

| Implementation | Contract |
|----------------|----------|
| `CategoriesService::sanitizeCategoryName` | lower, `[^a-z0-9]+` -> `-`, trim `-`, `''` if empty. Names the output directories (canonical). |
| `TemplateRenderer::slugifyCategory` | identical to above |
| `RobotsTxtService::sanitizeCategoryName` | identical; falls back to the unsanitized input if `preg_replace` returns null |
| `RssFeedService::sanitizeCategoryName` | same, but returns `'category'` when empty |
| `FileDiscovery::slugify` (`src/Core`, protected) | spaces and `_` -> `-`, **deletes** other characters (`Dr.Who` -> `drwho`, `a&b` -> `ab`), collapses `-` — materially different |
| `ContentCreatorCommand::slugify` | Unicode-aware (`\pL`, `iconv //TRANSLIT`), locale-dependent |

### 11.2 Design
`EICC\StaticForge\Services\Slugger`, three methods, one contract each:
- `category(string): string` — the shared regex behavior. **Postcondition** `^[a-z0-9]+(-[a-z0-9]+)*$`; on empty result or a `preg_replace` failure it **throws** `InvalidSlugException` (SEC-10). Callers treat the throw as a build error for that page. Never emits `.`, `..`, separators, NUL.
- `rssCategory(string): string` — calls `category()`, but on the empty case returns `'category'`. Used **only** by the RSS path so `/category/rss.xml` for a symbol-only name is unchanged. This preserves the existing RSS URL; the symbol-only case is new-error everywhere else (it was already a bug there).
- `filename(string): string` — ContentCreator behavior, locale pinned in the implementation so tests are deterministic.
- `fileDiscovery()` is **not** created in 3.4.0 (see 11.4).

### 11.3 Migration and rules
1. Characterization tests first, one data-provider per existing implementation on one shared corpus (ASCII/mixed/numbers, whitespace, repeated separators, `& / \ . ..`, accents/CJK/RTL/emoji, empty, all-symbol, very long, NUL, invalid UTF-8, leading dash/dot, real docs-site categories). Freeze current outputs as literals plus an "implementations disagree" matrix. Prefer testing through public paths (output directory names). Old and new code paths run side by side in one commit; after migration each `Slugger` method equals its old implementation.
2. Migrate the four category implementations (O4 default). The RSS call site uses `rssCategory()`.
3. Rule "no silent URL change": any input whose slug changes is a documented breaking change needing owner sign-off, **except** the empty-result cases, which are a security fix (P2, SEC-10) rather than a URL change because no valid URL existed.
4. Property tests: `category()` output never contains `.`, `..`, `/`, `\`, NUL.

### 11.4 `FileDiscovery::slugify` mismatch (separate bug)
Verified: `FileDiscovery::slugify` deletes `.`/`&` where the category directory logic inserts `-`, so a category "Dr.Who" resolves to different names in two places (live link bug). It lives in `src/Core`, which this project restricts. Handled as a separate reviewed bug fix (owner approval to touch `src/Core`), outside the Slugger migration; the characterization matrix documents it now.

## 12. Cross-cutting

### 12.1 Audit checks (commands are `audit:config`, `audit:seo`, `audit:live`, `audit:content`, `audit:links`)
- `audit:config`: validate `feed.*`, `upload.*`, `search.split` keys and types (`limit: "abc"`, `formats: rss`, negative `keep_releases`); warn when `content/404.md` exists and `SITE_BASE_URL` is empty/host-relative; run the same `upload.*` validator as `site:upload` (6.1). `audit:config` stays offline; the atomic canary check is a `site:upload` step (6.3).
- `audit:seo`: warn on `robots: no` plus `noindex: true`; verify feed files are present when enabled and absent from sitemap; verify sitemap URLs equal the golden URL set.
- `audit:live`: random-URL soft-404 check (7.4); feed autodiscovery link resolves; **manifest exposure** (SEC2-10): fetch `<site>/staticforge-manifest.json` and warn on a 200 (it lists every deployed path; `.htaccess` protection does not apply on nginx/Cloudflare). All requests follow the 7.4 client rules.

### 12.2 Docs
Manual pages and menu slots (section 13). Existing 404 mentions in `features/search.md` (~line 68) and `guide/site-config.md` (~line 364) verified against the new default noindex/sitemap exclusion. Docs claims verified against code (`site_base_url` vs `SITE_BASE_URL`; `audit:config`). After edits run `site:render` and `audit:content` and check sidebar order.

### 12.3 Events, config, version
Events added: `SITE_FEED_INIT`, `SITE_FEED_ITEM_BUILDING`. Config keys added: `feed.*`, `upload.*`, `404_links`, `search.split` (if F2). All optional; absent keys give 3.3.6 behavior. Versions per section 1.

## 13. Documentation deliverables

| Page | Menu | Content |
|------|------|---------|
| `content/guide/404-pages.md` "404 Pages" (new) | `'2.1.5'` | What/why; `content/404.md` with `template: 404`; automatic sitemap exclusion and noindex; customizing staticforce/sample and custom themes; absolute links from `site_base_url` and sub-path caveat; Apache (`make:htaccess` line, existing files never modified, manual line for sub-path), nginx `error_page 404 /404.html;`, Cloudflare Pages/Netlify/GitHub Pages (each claim verified before publishing), dev server; existing sites create the file themselves; soft-404 and `curl -I` verification; Next Steps |
| `content/guide/dev-server.md` "Dev Server and Live Reload" (new) | `'2.2.5'` | `--watch`, Lando invocation, banner, limits |
| `content/guide/site-management.md` (edit, 2.2.1) | — | Deploy safety (`--no-delete`, guard, `--force-delete`, prune/rollback if D3 ships, interrupted upload); link to the 404 page. There is no "deployment page" (DOC-1) |
| `content/guide/cli-commands.md`, `commands.md` (2.2.4) | — | `site:devserver` flags, `site:upload` flags, `make:htaccess`, `site:init` |
| `content/guide/index.md`, `quick-start.md` | — | Contents/Next Steps; sample 404 mention |
| `content/guide/site-config.md` | — | `feed`, `upload`, `404_links` keys; `audit:config` validation noted |
| `content/features/rss-feed.md` (3.1.10), `sitemap.md` (3.1.12), `search.md` (3.1.11), `features/index.md` | — | Site feeds, exclusion rules, minified index; tag feeds out of scope |
| `content/development/slugger-and-htmldocument.md` (new) | `'4.1.10'` | Slugger contracts, HtmlDocument wrapper (G in 3.5) |
| `content/development/events.md`, `templates.md` (4.1.5) | — | New events; 404 template |
| `content/whats-new-3-4.md` (new; `whats-new-3-0.md` is 3.0-only) | `'2.4'` | User-facing release notes incl. behavior changes |

Page conventions (DOC-5): frontmatter with `title`, single-quoted `description`, `template: docs`, quoted `menu`, `og_image` prompt ending `--ar 16:9`; opening welcome paragraph; `---` between H2s; Next Steps; relative `.html` cross-links.

## 14. Security notes (consolidated)

| Area | Requirement |
|------|-------------|
| A | Router realpath containment for every request in both modes (non-HTML included), NUL rejection, private 0700 state dir with random name in both modes, temp+rename writes, `proc_open` argv arrays, read-only GET-only state endpoint with Host allowlist and 5-line path-scrubbed errors, `--host` refuse/warn matrix, no HTTP path triggers a build, watcher does not follow symlinks and excludes the resolved `OUTPUT_DIR` (refuses overlap), client script uses `textContent` |
| B | XMLWriter for RSS2 and Atom (no concat/CDATA), `mb_scrub` then control-char strip, `JSON_THROW_ON_ERROR`, hidden pages excluded from site feeds and category Atom/JSON via `MetadataFlags`, `exclude_categories` compared as slugs, `feed_links` autoescaped |
| D | Cleanup never runs after errors; delete guard; `upload.*` validated at runtime; ids generated locally and regex-validated (rollback arg too); only complete releases (manifest present) are candidates; relative symlink target verified by exact `readlink`; abort without posix-rename unless opted in; no recursive phpseclib delete; manifests outside served tree in atomic mode; canary check by `site:upload`; `audit:live` flags an exposed `staticforge-manifest.json`; outbound HTTP: TLS on, 10 s, no redirects, same-host only |
| E | Static 404 file does not reflect the URL; dev-router reflected path HTML-escaped; no location-to-DOM HTML writes |
| G | HTML5 parser only; no XML entity/DTD loading |
| H / P2 | 3.3.7: empty or dot-only slugs rejected at `categorizeOutputPath`, `CategoryPageService`, RobotsTxt; 3.4: `category()` postcondition/throw; slugs never contain `.`, `..`, separators, NUL |

## 15. Backwards-compatibility register

| Change | Break? |
|--------|--------|
| Cleanup skipped after upload errors (3.3.7) | Fix; previously deleted live files |
| Empty or dot-only category slug (`!!!`, `..md`) now errors instead of overwriting pages/`index.html` (3.3.7) | Fix |
| Dev server (both modes): router 404s symlinks leaving the docroot; private temp dir replaces `/tmp/staticforge-devserver-router-<pid>.php` | Fix; behavior change only for hostile/odd setups |
| `site:upload` aborts on invalid `upload.*` values | Only affects new keys |
| Site feeds | Additive, off for existing sites (O5). Existing `content/feed.xml` etc. win over generated files |
| Sitemap drops `robots: no`/`noindex`/`sitemap: false`/404 | Behavior change (arguably a fix); release-noted |
| Sitemap `lastmod` prefers `updated` | Behavior change; release-noted |
| `search_index: "false"` (quoted) now honored | Behavior change; release-noted |
| `search.json` minified | Whitespace only |
| Delete guard | Only pathological non-interactive runs; `--force-delete` restores old behavior |
| `UPLOAD_CHECK_FILE` | Unchanged for `in_place`; `atomic` refuses when listeners exist |
| Slugger | No URL changes; RSS empty fallback preserved via `rssCategory()` |
| HTML parser (3.5) | HTML bytes and `content:encoded` change; full re-upload |
| `--watch`, `upload.strategy`, `category_formats`, `search.split` | Opt-in |

## 16. Testing strategy (consolidated)

- **Golden files first (TEST-3/8/9/10):** three commits land before any refactor: category-feed golden from 3.3.6 code (B), HTML characterization corpus and docs-site snapshot (G), slug corpus/matrix (H). Refactors are then verified against them.
- **Config-defaults test (the BC gate):** with no new config keys, a `site:render` of the fixture site produces zero new files and unchanged output.
- **Seams:** `ClockInterface`, `FileSignatureProvider`, `BuildRunnerInterface`, `SftpClientInterface` + in-memory fake (`tests/Mocks/`), `PromptInterface`, TTY check, build-id generator, `HtmlDocumentInterface`, extracted `DevServerRouter`.
- **Per-item cases** are listed in each section (3.4, 4.7, 5.2, 6.4, 7.5, 9, 10, 11.3).
- **Config validation:** every new key/flag/event has an `audit:config` case including bad types.
- **Coverage:** per new class; state explicitly in the PR if no coverage driver is available.
- **Locale:** slug tests set the locale explicitly (`iconv //TRANSLIT` hazard).
- **JS:** no harness, no Node; static PHP checks of shipped JS plus a manual browser checklist.
- **Quality gate per item:** tests green, phpstan no new errors, phpcs no new violations, exercised via a real `site:render`, code-reviewer and security-auditor pass. Podcast manual gate: run 3.4 against the owner's real podcast package and content and diff category feeds.

## 17. Disposition of review findings

Legend: A accepted, M modified, R rejected, D deferred-to-owner.

### SEO
| ID | Disp | Reason |
|----|------|--------|
| SEO-1 | M | 404/noindex/robots/sitemap:false/draft excluded; redirect/non-canonical handled only where core generates them |
| SEO-2 | D | O6: kept Disallow behavior, documented; warn on conflict |
| SEO-3 | A | search_index independent |
| SEO-4 | M | `noindex, follow` for `noindex: true`; X-Robots-Tag documented; robots:no meta left to O6 |
| SEO-5 | A | audit:live soft-404 check, manual |
| SEO-6 | A | noindex, no canonical, local ErrorDocument path |
| SEO-7 | A | feeds excluded from sitemap/search, not Disallowed |
| SEO-8 | A | autodiscovery with three types and titles |
| SEO-9 | A | lastmod = updated ?? date ?? mtime |
| SEO-10 | A | Sitemap line stays absolute; 404/noindex out of search via frontmatter |

### AEO
| ID | Disp | Reason |
|----|------|--------|
| AEO-1 | M | Core sets no_llms in 404; package changes specified (section 8) |
| AEO-2 | M | Optional X-Robots-Tag for `*.md`; package change specified |
| AEO-3 | M | Package change listed, out of scope |
| AEO-4 | M | Package change listed |
| AEO-5 | M | Core lastmod uses updated; package change listed |
| AEO-6 | A | Informational; exclusions are the protection |
| AEO-7 | A | Slug rule kept; golden URL-set equality check |
| AEO-8 | A | Independent, no Disallow |
| AEO-9 | A | Assertion added to G tests |
| AEO-10 | M | Separate knobs documented; single `visibility` key not built (YAGNI) |

### TEST
| ID | Disp | Reason |
|----|------|--------|
| TEST-1 | A | Seams and edge cases in 3.4 |
| TEST-2 | A | Router extracted, table tests |
| TEST-3 | A | Golden first; cases in 4.7 |
| TEST-4 | A | Shared helper, cases in 5.2 |
| TEST-5 | A | Interface + fake + cases in 6.4 |
| TEST-6 | M | JS fixture dropped; PHP grep + manual check |
| TEST-7 | A | Cases in 7.5 |
| TEST-8 | A | Section 10 tests |
| TEST-9 | A | Section 11.3 tests |
| TEST-10 | A | Section 16 |

### FE
| ID | Disp | Reason |
|----|------|--------|
| FE-1 | A | site_base_url everywhere, audit warning |
| FE-2 | A | No new CSS, sample self-contained |
| FE-3 | A | Frontmatter defaults in 7.3 |
| FE-4 | A | Autodiscovery in `<head>`, `feed_links` allowlisted (renamed to avoid `feed` clash) |
| FE-5 | M | noindex meta in both bases; robots:no meta per O6 |
| FE-6 | A | Client script spec (endpoint renamed `/state`, returns status too) |
| FE-7 | D | Shard loader kept for O3 |
| FE-8 | A | Flat stays default |
| FE-9 | A | No JS harness |

### DOC
| ID | Disp | Reason |
|----|------|--------|
| DOC-1 | A | Link from site-management |
| DOC-2 | A | Doc list expanded, whats-new-3-4 |
| DOC-3 | A | Slots in section 13 |
| DOC-4 | A | 404 guide content spec |
| DOC-5 | A | Conventions, no internal labels, audit:config |

### UX
| ID | Disp | Reason |
|----|------|--------|
| UX-1 | A | Body override, single column |
| UX-2 | A | Hierarchy spec |
| UX-3 | A | One search box |
| UX-4 | A | noscript fallback |
| UX-5 | A | 404_links / menu_top / home |
| UX-6 | A | site_base_url required |
| UX-7 | A | Copywriter owns text |
| UX-8 | A | A11y list |
| UX-9 | A | Light theme, variables |
| UX-10 | A | Mobile rules |
| UX-11 | A | Absolute via site_base_url |
| UX-12 | A | Failure banner |
| UX-13 | A | Rebuilding indicator |
| UX-14 | A | Scroll preservation, focus guard, disconnected notice |
| UX-15 | A | Poll logging suppressed, hidden-tab pause |
| UX-16 | A | Shadow root, prefixed ids |
| UX-17 | A | One line per rebuild |
| UX-18 | A | Full-rebuild reasons logged |

### FR
| ID | Disp | Reason |
|----|------|--------|
| FR-1 | A | `upload:` in siteconfig, creds in .env |
| FR-2 | A | Reuse CacheBuster build id |
| FR-3 | A | Category cascade |
| FR-4 | M | Conflict warning, path mismatch fixed; parse unified |
| FR-5 | A | MetadataFlags helper |
| FR-6 | A | Flat `feed:` key |
| FR-7 | A | Slug comparison, tag feeds out of scope, collision test |
| FR-8 | A | No Disallow for 404 needed |
| FR-9 | A | Watch test for template edit |
| FR-10 | A | indexPath from site_base_url in both JS files |
| FR-11 | M | audit checks and lastmod added; watch/drafts via passthrough flag; 301/canonical skipped |
| FR-12 | A | Treated as bug, fixed in 3.3.7 |

### SEC
| ID | Disp | Reason |
|----|------|--------|
| SEC-1 | A | Realpath containment in router |
| SEC-2 | A | Private 0700 dir |
| SEC-3 | A | proc_open, read-only endpoint |
| SEC-4 | A | XMLWriter, JSON_THROW |
| SEC-5 | A | Hidden pages excluded from feeds |
| SEC-6 | A | Id regex, lstat delete, no phpseclib recursion |
| SEC-7 | A | 3.3.7 fix |
| SEC-8 | A | Manifests outside tree, canary |
| SEC-9 | A | Abort without posix-rename |
| SEC-10 | A | Postcondition/throw, 3.3.7 minimal + Slugger |
| SEC-11 | A | HTML5 only, textContent check |
| SEC-12 | A | No location-to-HTML |

### SEC2 (security pass on rev 2)
| ID | Disp | Reason |
|----|------|--------|
| SEC2-1 | M | Sinks confirmed (`deferFile` filename slug incl. `..md` -> `.`, `...md` -> `..`; `categorizeOutputPath` empty -> `dirname//file`; RobotsTxt `//`). Corrected: `TemplateRenderer:57` is lookup-only, not a write sink; `OutputWriter` already jails via `PathGuard` (stops `..`, not `.`); strict regex on definition filenames rejected for 3.3.7 (would break `My_Cat.md`, silent URL change), replaced by "empty or dot-only"; strict form lands with `Slugger` (2.2) |
| SEC2-2 | A | Private dir, temp+rename, `proc_open` argv in both modes (3.2) |
| SEC2-3 | A | Containment for every request; `return false` only after it passes (3.2) |
| SEC2-4 | M | Host allowlist, 5-line scrubbed error accepted; `--host` matrix specified (refuse unless `--allow-remote` or Lando, else warn); Lando hostname taken from environment, variable to be confirmed |
| SEC2-5 | A | Complete-release rule via manifest, relative target, exact `readlink`, rollback regex, tmp-link cleanup (6.3) |
| SEC2-6 | A | Runtime validation, `keep_releases >= 1`, current and rollback target always kept (6.1) |
| SEC2-7 | M | Client rules accepted (7.4). Canary is written and checked by `site:upload` (not `audit:config`, which stays offline); canary uses a non-dot `.txt` name to avoid dotfile-denying false negatives |
| SEC2-8 | A | Hidden-page rules for category Atom/JSON via `MetadataFlags` (4.2, 4.4); `exclude_categories` gets none |
| SEC2-9 | A | XMLWriter for RSS2, `mb_scrub`, autoescaped `feed_links` (4.5, 4.6) |
| SEC2-10 | A | `audit:live` manifest exposure check, manual (12.1, 6.1) |
| SEC2-11 | A | Resolved `OUTPUT_DIR` excluded; overlap refused (3.2) |
| SEC-2, 5, 6, 8, 10 (under-specified) | A | Covered by SEC2-2, 8, 5, 7, 1 |

### DA
| ID | Disp | Reason |
|----|------|--------|
| DA-1 | A | Verified in source; 3.3.7 |
| DA-2 | A | proc_open non-blocking |
| DA-3 | A | Router serves HTML itself |
| DA-4 | D | O1: G alone in 3.5; consequences documented |
| DA-5 | A | RssFeedService untouched, separate collector; real-package gate |
| DA-6 | D | O4: H scope; FileDiscovery separate bug |
| DA-7 | D | O2: atomic flagged; plugin refusal rule added |
| DA-8 | D | O5/O7: default off; podcast exclusion pending marker |
| DA-9 | M | Guard becomes warn under Lando |
| DA-10 | D | O3: split on hold |
| DA-11 | M | `--incremental` verified not to wipe; deletions trigger `--clean` |

### DEV
| ID | Disp | Reason |
|----|------|--------|
| DEV-1 | A | proc_open + stream_select |
| DEV-2 | A | Router resolves HTML |
| DEV-3 | A | Workers dropped, polling |
| DEV-4 | M | Verified: no wipe without `--clean`; claim corrected |
| DEV-5 | A | Section 11.1 map, `rssCategory()` |
| DEV-6 | D | Wrapper designed; release per O1 |
| DEV-7 | A | Separate collector; no refactor of shared state |
| DEV-8 | A | Interface, DI injection, posix_rename |
| DEV-9 | A | Metadata checks in helper |
| DEV-10 | A | Minify; both JS files |
| DEV-11 | A | Explicit 404 status |

### Original open questions
| Question | Outcome |
|----------|---------|
| D-1 server-side copy vs full upload | Full upload (atomic flagged O2) |
| `search_index` and sitemap | No |
| Site feed default | O5: off for existing, on in init |
| Polling vs SSE | Polling |
| G in 3.4 | O1: 3.5 |
