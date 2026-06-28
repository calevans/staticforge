# Plan: Draft/Publish Gate

## Status: Architecture Already Exists — Plan Revised to Minimal Wiring Change

**Read-first finding (critical, changes scope):** The premise of the task — "nothing in the render pipeline skips draft content during `site:render`" — is no longer accurate against current `HEAD` (commit `dc1d1c2`). `src/Core/FileDiscovery.php::scanDirectory()` (lines 95-105) already excludes any file whose frontmatter has `draft: true` **before it ever enters `discovered_files`**:

```php
$showDrafts = $this->container->getVariable('SHOW_DRAFTS') ?? false;
if (is_string($showDrafts)) {
    $showDrafts = filter_var($showDrafts, FILTER_VALIDATE_BOOLEAN);
}
if (isset($metadata['draft']) && $metadata['draft'] === true && !$showDrafts) {
    $this->logger->log('DEBUG', "Skipping draft file: {$filePath}");
    continue;
}
```

A file excluded at discovery never reaches `FileProcessor::processFile()`, so it cannot appear in `public/`, the sitemap, the RSS feed, category indexes, the search index, or the menu — every one of those consumers reads from `discovered_files` or hooks `PRE_RENDER`/`POST_RENDER`/`POST_LOOP`, all of which run strictly after discovery. This is fully covered by an existing, passing test suite: `tests/Unit/Core/FileDiscoveryDraftTest.php` (`testSkipsDraftFilesByDefault`, `testIncludesDraftFilesWhenConfigured`, `testIncludesFilesWithoutDraftStatus`).

**What is actually missing** is narrower than "a new Feature": there is no way to set `SHOW_DRAFTS` from the CLI. `grep` across `src/` and `tests/` for `include-drafts`, `includeDrafts`, `include_drafts` returns nothing. The container variable exists and is honored, but nothing populates it during `site:render`.

**Recommendation:** Do not build a new `src/Features/DraftGate/` feature. There is no event to hook — by the time `PRE_RENDER` fires, the draft file is already gone from the file list, so a Feature listening on `PRE_RENDER` would never see it and would be dead code. Per CLAUDE.md Section 9 ("Operational Limits — only do what is asked, do not do more") and YAGNI, the correct fix is a 5-line addition to the existing CLI command that already wires similar overrides into the container, mirroring the exact pattern used for `--input`/`--output`.

This plan documents that minimal change. If the user specifically wants a self-contained `Features/DraftGate` package for the extraction roadmap (Section 6) — e.g., because they want draft logic to be a removable, optional concern rather than baked into Core — that is a separate, larger undertaking (moving `FileDiscovery`'s draft/future-date logic out of Core into a Feature that hooks `POST_GLOB` to filter `discovered_files` after the fact). That alternative is described in Section "Alternative B" below for completeness, but is **not recommended** unless explicitly requested, since it means modifying/removing working Core code that already has test coverage, which carries regression risk for no functional gain.

---

## Recommended Approach (Alternative A — Minimal, in scope)

### Overview

Add a `--include-drafts` option to `site:render` (`src/Features/SiteBuilder/Commands/RenderSiteCommand.php`). When passed, it sets the `SHOW_DRAFTS` container variable to `true` before the `Application` is constructed/generation runs, so `FileDiscovery` (which already reads `SHOW_DRAFTS`) includes draft files in `discovered_files`. No other file changes are needed. No new Feature, no new namespace, no new events.

This satisfies all four stated requirements:
1. Draft files are excluded from `public/` by default — already true today.
2. Excluded from sitemap/RSS/category indexes/search index — already true today, because exclusion happens before discovery, upstream of every consumer.
3. A CLI flag renders drafts for local preview — added by this plan.
4. No new Composer dependency, no Core modification beyond the one new `addOption()` call and the existing `updateVariable()` pattern already used in this exact file for `--input`/`--output`.

### Data Structures

No new data structures. Reuses the existing container variable `SHOW_DRAFTS` (bool, already consumed by `FileDiscovery::scanDirectory()`).

### Class Structure

**Modified file:** `src/Features/SiteBuilder/Commands/RenderSiteCommand.php`

In `configure()`, add one more `InputOption` alongside the existing `clean`, `template`, `input`, `output` options:

```php
->addOption(
    'include-drafts',
    null,
    InputOption::VALUE_NONE,
    'Render files marked draft: true (for local preview only)'
)
```

In `execute()`, following the exact existing pattern used for `$inputOverride`/`$outputOverride` (read the option, then `$this->container->updateVariable(...)`), add:

```php
if ($input->getOption('include-drafts')) {
    $output->writeln('<comment>Including draft content in this build</comment>');
    $this->container->updateVariable('SHOW_DRAFTS', true);
}
```

Placement: this must run **before** `$application->generate()` is called (line ~139), since `Application::generate()` is what eventually invokes `FileDiscovery::discoverFiles()`. The existing `$inputOverride`/`$outputOverride` block runs after `new Application(...)` but before `generate()` — same placement works for `SHOW_DRAFTS` since `FileDiscovery` reads the container lazily at discovery time, not at construction time. Confirmed by reading `FileDiscovery::__construct()` (stores container reference only) and `scanDirectory()` (reads `SHOW_DRAFTS` at scan time).

No new classes. No new tests directory. No `Feature.php`, no `Services/`, no `Models/` — there is no business logic to encapsulate beyond the one-line option read.

### Event Pipeline Hooks

None required. `FileDiscovery` runs during `POST_GLOB`/file-discovery phase (before `PRE_LOOP`), already gated on `SHOW_DRAFTS`. No new event listeners.

### Configuration

No `ConfigurableFeatureInterface` needed — this is not a Feature, it is a CLI option on an existing Command. No new `siteconfig.yaml` keys, no new env vars. (If the user later wants `SHOW_DRAFTS` settable via `.env` for a persistent "staging" build, that's a 1-line addition to whatever bootstraps env vars into the container — out of scope unless requested, per Operational Limits.)

### Security Implications

- `SHOW_DRAFTS` only affects local CLI invocation; it is not exposed via any web-facing input, form, or API. No injection surface.
- Risk is purely operational: a developer could accidentally run `site:render --include-drafts` against the production deploy path and ship draft content. Mitigation: the `<comment>` warning line printed to console makes this visible in build logs; no further guardrail is justified (CLAUDE.md: no error handling for scenarios that cannot happen / no future-proofing beyond what's asked). If the user wants a hard stop in CI/production deploys, that would need an explicit `--environment` or similar concept that does not currently exist — flag this to the user rather than inventing it.

### Testing Strategy

- **No new unit tests needed for `FileDiscovery`** — already covered by `tests/Unit/Core/FileDiscoveryDraftTest.php`.
- **New/updated test:** `tests/Unit/Features/SiteBuilder/RenderSiteCommandTest.php` (check if this file exists first; if not, this would be the first test for this command — confirm whether the project tests Symfony Console commands elsewhere, e.g. via `CommandTester`, before adding). Assert that passing `--include-drafts` results in `$container->getVariable('SHOW_DRAFTS') === true` after `configure`/`execute`, using Symfony's `CommandTester`.
- **Integration check:** Manually run `lando php bin/staticforge.php site:render --include-drafts` against a temp content dir containing a `draft: true` file, confirm output HTML is written; then run without the flag, confirm it's absent. This mirrors the existing `FileDiscoveryDraftTest` assertions at the CLI level.
- Run full `lando phpunit` to confirm no regressions in `RenderSiteCommand`-adjacent tests.

---

## Alternative B (NOT recommended, documented for completeness only)

If a future requirement demands that draft-filtering be a removable, optional, extractable concern rather than permanent Core behavior (per CLAUDE.md Section 6, "Future Extraction Strategy"), the shape would be:

1. Revert `FileDiscovery`'s draft-skip block (lines 95-105) back to including all files unconditionally — this is a Core modification and would break the existing `FileDiscoveryDraftTest` test names/expectations (they'd need to move/be deleted), which is a regression risk against working, tested code for zero functional gain at present.
2. New Feature `src/Features/DraftGate/Feature.php`, implementing `FeatureInterface` only (no config needed — a single CLI-driven boolean does not warrant `ConfigurableFeatureInterface`'s `siteconfig.yaml`/env validation).
3. Listens on `POST_GLOB` at a priority that runs after file discovery populates `discovered_files`, filtering the container's `discovered_files` array in place to remove entries whose `metadata['draft'] === true` unless `SHOW_DRAFTS` is set.
4. Same `--include-drafts` CLI wiring as Alternative A.

This is strictly more code, more risk, and more indirection than Alternative A for an identical end result, and actively un-does already-tested Core behavior. **Do not implement Alternative B unless the user explicitly states they want draft-handling extracted out of Core for packaging reasons** — flag this trade-off to the user rather than assuming it.

---

## Decision Needed Before Implementation

This plan recommends Alternative A (5-line change to an existing Command, no new Feature). The original task framing assumed a Feature was needed; that assumption does not hold against the current codebase. Confirm with the user before the Developer agent proceeds, since the deliverable ("a new Feature at `src/Features/DraftGate/`") as literally specified conflicts with the simpler, already-tested reality on disk.
