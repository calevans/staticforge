# Plan: Incremental/Cached Build for `site:render`

## Recommendation to Pause and Confirm Scope

**Read this before implementing.**

This feature is not safe to implement as a simple "skip unchanged files" optimization,
because of how the aggregate features are wired into the event pipeline today. Specifically:

- `Sitemap`, `RssFeed`, and `CategoryIndex` (collection step) all hook **`POST_RENDER`**,
  which only fires for a file when that file actually goes through the `RENDER` step in the
  *current* process run. They do not re-derive their data from `discovered_files` independently.
- `Search` also hooks `POST_RENDER` and additionally needs `rendered_content` (the actual HTML)
  to build its index — metadata alone is not enough for Search.
- `Tags` is the only fully safe aggregate today: it derives everything from `discovered_files`
  metadata at `POST_GLOB`, never touches `rendered_content`.

This means: if we skip the `RENDER`/`POST_RENDER` steps for an unchanged file, **Sitemap, RSS,
CategoryIndex, and Search will silently drop that file from their aggregate output**, even though
nothing about that file changed. This is exactly the silent-staleness risk flagged in the task.

To make incremental builds safe, this plan requires one of two approaches:

1. **(Required, and the one this plan adopts)** Decouple "skip the expensive render" from
   "fire POST_RENDER with full data". When a file is skipped, the feature must still synthesize
   a `POST_RENDER`-equivalent payload — including `rendered_content` — by reading it back from
   the previously-written output file in `public/` (which is exactly what was rendered last time).
   This keeps every aggregate feature's existing `POST_RENDER` listener unmodified and ignorant
   of caching. It does require **one small, surgical change to `FileProcessor::processFile()`** in
   `src/Core` (see "Core Modification" below) — this is the only place capable of deciding to
   skip-and-substitute before firing `RENDER`/`POST_RENDER`.

2. **(Rejected for this plan, noted for completeness)** Modify every aggregate feature individually
   to read `discovered_files` + the on-disk output file directly instead of relying on
   `POST_RENDER` parameters. This avoids touching `src/Core` but requires changing five separate
   features (Sitemap, RssFeed, CategoryIndex, Search, and any future aggregate) and duplicates
   "read cached HTML back from disk" logic five times instead of once in the core loop. This
   violates DRY and increases the risk surface (five places to get it wrong instead of one).
   Rejected in favor of option 1.

Because option 1 requires a `src/Core` change (`FileProcessor`), and because this is explicitly
flagged as the highest-risk item in this batch, **implementation should not proceed without
explicit user confirmation that a `src/Core` change is acceptable**, even though it is small,
additive, and backward compatible (see "Core Modification" section for exact diff scope).
If the user prefers zero Core changes, fall back to option 2 above and re-scope as a larger,
multi-feature change — flag that explicitly as a follow-up decision.

The rest of this document is written assuming option 1 is approved, so the decision is
well-informed either way.

---

## 1. Overview

### Problem
`site:render` always processes every discovered file through the full `PRE_RENDER` → `RENDER`
→ `POST_RENDER` → write pipeline, regardless of whether the source file changed since the last
build. For large sites this wastes time re-running Markdown→HTML conversion, Twig rendering, and
disk writes for files that are byte-identical to last time.

### Proposed Solution
Add an **opt-in** `--incremental` flag to `site:render`. When enabled, `FileProcessor` compares
each source file's mtime against its previously-written output file's mtime (the same idiom
already used by `CategoryIndex\Services\ImageService` and
`ResponsiveImages\Services\ImageVariantGenerator`). If the output file exists and is newer than
the source, the **expensive** `RENDER` step (and the disk write) is skipped, and the **previously
written output HTML** is read back from disk and used as the substitute `rendered_content` so
that `POST_RENDER` listeners (Sitemap, RssFeed, CategoryIndex, Search) receive a fully-formed
payload identical in shape to what they'd get from a real render.

**Frontmatter/metadata parsing for every discovered file always happens, unconditionally, on
every build, incremental or not** (it already does — `FileDiscovery::scanDirectory()` parses
frontmatter for every file regardless of any cache state, because it's needed for navigation,
categories, tags, drafts/future-date filtering, etc. *before* the render loop even starts). This
plan does not change that behavior — it is the foundation the safety invariant rests on.

No persistent cache database is introduced. No new Composer dependencies.

---

## 2. Data Structures

No new YAML config shapes are required for correctness. One optional config block, purely for
tuning, may be added to `siteconfig.yaml` (YAGNI: only add if there's an immediate need —
otherwise skip entirely for v1):

```yaml
# OPTIONAL — only if needed; v1 can ship without this block entirely.
incremental_build:
  enabled: false   # CLI flag --incremental always overrides this
```

No DTOs/Models are needed. The render context array (`$renderContext`) already carries everything
needed; we add one transient key:

```php
$renderContext = [
    'file_path'        => string,
    'file_url'         => string,
    'file_metadata'    => array,
    'rendered_content' => string|null,
    'metadata'         => array,
    'output_path'      => string|null,
    'skip_file'         => bool,
    'cache_hit'         => bool,   // NEW, informational only — true if HTML was reused from disk
];
```

`cache_hit` is purely informational (for logging/stats); no feature is required to read it, and
its absence (false/unset) must never change any feature's behavior — every consumer already only
looks at `rendered_content`, `metadata`, and `output_path`, which are populated identically in
both the cache-hit and full-render cases.

---

## 3. Class Structure

This is implemented as a small enhancement inside the existing core render loop, not as a new
Feature, because the decision of "should this file's HTML render be skipped" must happen *before*
`RENDER` fires and *before* `POST_RENDER` fires — there is no existing event hook positioned
early enough in the per-file loop to intercept this from outside `FileProcessor` without resorting
to fragile global mutable state. (See "Core Modification" for why this can't be done as a pure
Feature.)

### `src/Core/FileProcessor.php` (modified)

```php
final class FileProcessor
{
    // existing properties...
    private bool $incrementalEnabled = false; // set via constructor or setter from container

    protected function processFile(array $fileData): void
    {
        $filePath = $fileData['path'];
        $expectedOutputPath = $this->calculateOutputPath($filePath);

        // ... existing conflict check unchanged ...

        $renderContext = [ /* unchanged initial shape, + 'cache_hit' => false */ ];

        $renderContext = $this->eventManager->fire('PRE_RENDER', $renderContext);
        if ($renderContext['skip_file'] ?? false) {
            return;
        }

        if ($this->incrementalEnabled && $this->canReuseCachedOutput($filePath, $expectedOutputPath)) {
            $renderContext = $this->substituteCachedRender($renderContext, $expectedOutputPath);
        } else {
            $renderContext = $this->eventManager->fire('RENDER', $renderContext);
            if (!isset($renderContext['rendered_content'], $renderContext['output_path'])) {
                throw new FileProcessingException(/* unchanged */);
            }
        }

        // ... existing processedOutputPaths tracking unchanged ...

        $renderContext = $this->eventManager->fire('POST_RENDER', $renderContext);

        // Only write to disk if we did NOT reuse the cached file (it's already correct on disk).
        if (!($renderContext['cache_hit'] ?? false)
            && isset($renderContext['rendered_content'], $renderContext['output_path'])) {
            $this->writeOutputFile($renderContext['output_path'], $renderContext['rendered_content']);
        }
    }

    /**
     * Mirrors the established mtime-comparison idiom used by
     * CategoryIndex\Services\ImageService and
     * ResponsiveImages\Services\ImageVariantGenerator.
     */
    private function canReuseCachedOutput(string $sourcePath, string $outputPath): bool
    {
        if (!is_file($outputPath)) {
            return false;
        }
        $sourceMtime = filemtime($sourcePath);
        $outputMtime = filemtime($outputPath);
        if ($sourceMtime === false || $outputMtime === false) {
            return false; // fail safe -> full render
        }
        return $outputMtime >= $sourceMtime;
    }

    private function substituteCachedRender(array $renderContext, string $outputPath): array
    {
        $cachedHtml = file_get_contents($outputPath);
        if ($cachedHtml === false) {
            // Fail safe: if we can't read it back, do a full render instead.
            $renderContext = $this->eventManager->fire('RENDER', $renderContext);
            return $renderContext;
        }

        $renderContext['rendered_content'] = $cachedHtml;
        $renderContext['output_path'] = $outputPath;
        $renderContext['cache_hit'] = true;
        return $renderContext;
    }
}
```

Key points:
- The `RENDER` event is **skipped entirely** on a cache hit — this is what saves the time
  (Markdown parsing, Twig rendering).
- `POST_RENDER` **always fires**, for every file, cache hit or not — this is what keeps Sitemap /
  RssFeed / CategoryIndex / Search correct, because they only ever read `rendered_content`,
  `metadata`, and `output_path` from the context, all three of which are present and correct in
  both paths.
- `PRE_RENDER` is **unaffected** — CategoryIndex's defer-to-POST_LOOP mechanism and Tags'
  `tag_data` injection both run exactly as before, before the cache check, so category/tag pages
  that are *themselves* rendered via `Application::renderSingleFile()` in `POST_LOOP` are out of
  scope for caching in v1 (see "Out of Scope" below) and always do a full render.
- No new class, no new Feature, no new Service. This is intentionally the smallest possible
  change to the one place that has to make this decision.

### `src/Features/SiteBuilder/Commands/RenderSiteCommand.php` (modified)

Add one new flag, wire it into the container before `Application::generate()` runs:

```php
->addOption(
    'incremental',
    null,
    InputOption::VALUE_NONE,
    'Skip re-rendering files whose source has not changed since last build (opt-in, experimental)'
);

// in execute():
if ($input->getOption('incremental')) {
    $output->writeln('<comment>Incremental build enabled (experimental)</comment>');
    $this->container->setVariable('INCREMENTAL_BUILD', true);
}
```

`FileProcessor` reads `INCREMENTAL_BUILD` from the container in its constructor (default `false`
if unset), consistent with how other feature flags (`SHOW_DRAFTS`) are read today.

### No other class changes

CategoryIndex, RssFeed, Sitemap, Search, Tags features are **not modified at all**. This is the
entire point of doing the skip-and-substitute at the `FileProcessor` level: every aggregate
feature keeps working unmodified because the contract it depends on (`POST_RENDER` always fires
with a complete, correct payload for every discovered, non-draft, non-future-dated file) is
preserved exactly.

---

## 4. mtime-only vs SQLite — Decision

**Decision: mtime-only comparison against the existing output file in `public/`. No SQLite, no
persistent cache database, no new state file at all.**

Rationale:
- The existing codebase already has two precedents for this exact idiom
  (`CategoryIndex\Services\ImageService::generateThumbnail()` and
  `ResponsiveImages\Services\ImageVariantGenerator::renderVariant()`), both doing
  `file_exists($out) && filemtime($out) >= filemtime($source)`. Extending this idiom to page
  rendering is consistent and requires zero new infrastructure.
- A SQLite cache database would need to store, at minimum, a per-source-file hash or mtime
  snapshot from the *previous successful build*. That introduces a new failure mode this
  mtime-only approach doesn't have: **a stale or corrupt cache database that disagrees with what's
  actually on disk in `public/`**. The mtime approach has no such failure mode, because it
  compares against the literal artifact (`public/...html`) it would otherwise write — if that
  artifact doesn't exist, isn't readable, or is older than the source, it falls back to a full
  render automatically, every time, with no separate state to get out of sync.
  - Per the user's global instruction ("SQLite whenever possible... if you need more than that
    we'll have to discuss it first"), SQLite is appropriate when something genuinely needs a
    queryable persistent store. This feature doesn't: the filesystem itself (source mtime vs.
    output mtime) is a perfectly sufficient, already-durable "cache index" — building a second,
    parallel index that has to be kept in sync with the filesystem would violate YAGNI.
- mtime comparison is O(1) per file (two `filemtime()` calls), exactly as cheap as a SQLite lookup
  would be, with no schema migration, no `PDO` wiring, and nothing new to corrupt or migrate.

**What mtime-only does NOT give us**, which a hash-based or SQLite system would: detecting a
content change that doesn't update mtime (e.g., a file restored from backup with old mtime but
different content), or a *template* change that should invalidate all pages using that template.
Both are explicitly **out of scope** for this plan (see below) — the conservative, opt-in framing
and the "Rollback/Safety" section's automatic fallback story exist specifically to make those
edge cases safe-by-default (worst case: a stale page is regenerated unnecessarily — never the
reverse).

---

## 5. Opt-in vs Opt-out — Decision

**Decision: opt-in via a new `--incremental` flag. Full rebuild remains the default behavior with
no flags passed, exactly as today.** `--clean` is unaffected and continues to mean "wipe `public/`
first" — note `--clean` and `--incremental` are **mutually exclusive in effect**: if `--clean` is
passed, `public/` is wiped before the loop runs, so every output file will be missing and
`canReuseCachedOutput()` will correctly return `false` for everything, causing a full render
regardless of `--incremental`. No special-case code is needed for this combination — it falls out
of the mtime check naturally. (Optionally emit a warning if both flags are passed together,
since combining them is a probable user mistake, but this is not required for correctness.)

Rationale for opt-in:
- This mirrors the conservative stance already taken for `ResponsiveImages` in this same session.
- This is new, unproven functionality touching the central render loop — a regression here
  silently breaks SEO-critical artifacts (sitemap, RSS) and search. Defaulting to "off" means
  existing `site:render` invocations (CI pipelines, deploy scripts) are completely unaffected
  until someone explicitly opts in.
- Opt-out (incremental-by-default) would require near-certainty in correctness before shipping;
  opt-in lets it ship, get used deliberately, and be hardened over a few real builds before ever
  being considered as a future default.

---

## 6. The Core Safety Invariant

**Invariant: Frontmatter/metadata for every discovered, eligible file (not draft, not future-dated)
is parsed and present in `discovered_files` on every single build, unconditionally — regardless of
incremental mode, and regardless of which individual files have their HTML render skipped.**

Where this is guaranteed in code today, unchanged by this plan:

- `FileDiscovery::discoverFiles()` → `scanDirectory()` → `parseFrontmatter()` runs for **every**
  file matched by `ExtensionRegistry::canProcess()`, before any render-loop logic exists. This is
  step 3 of the 9-step pipeline (`Application::executeEventPipeline()`), which always runs in
  full, every build. Incremental mode introduced by this plan does not touch `FileDiscovery` at
  all.
- `Tags::handlePostGlob()` and `CategoryService::scanCategories()` both run at `POST_GLOB`
  (step 4), reading directly from `discovered_files` — entirely before the render loop, entirely
  unaffected by per-file render caching.
- The second-order invariant this plan **adds**: for every file that proceeds into the render loop
  (`FileProcessor::processFile()`), `POST_RENDER` fires exactly once per file, with a context that
  always contains valid `rendered_content`, `metadata`, and `output_path` — whether that
  `rendered_content` came from a fresh `RENDER` event or from `substituteCachedRender()` reading
  the prior build's output file back off disk.

Because Sitemap, RssFeed, CategoryIndex, and Search build their aggregate state exclusively by
accumulating data inside their `POST_RENDER` handlers across the full loop (see `SitemapService::
collectUrl()`, `RssFeedService::collectCategoryFiles()`, `CategoryService::collectFile()`,
`SearchIndexService::collectPage()` — all confirmed by reading the source), and because
`POST_RENDER` fires unconditionally for every non-skipped file with a complete payload, **every
aggregate output is built from the full set of discovered files on every build, never a subset**,
even when most files' HTML was reused rather than regenerated.

This is the exact place the invariant is enforced — `FileProcessor::processFile()`, where
`POST_RENDER` is fired unconditionally directly after either branch of the `RENDER`/cache-hit
fork, never inside either branch, never conditionally skipped.

---

## 7. Event Pipeline Hooks

No new custom events are introduced. No existing feature's event registration changes.

- `PRE_RENDER` — unaffected; still fires for every file before the cache check.
- `RENDER` — **conditionally skipped** per-file when `--incremental` is set and the cache check
  passes. This is the only event affected.
- `POST_RENDER` — **always fires**, unconditionally, exactly as today, for both cache-hit and
  full-render files. This is the lynchpin of the safety invariant.
- `POST_LOOP` — unaffected. `CategoryIndex::processDeferredCategoryFiles()`,
  `Tags::generateTagPages()`, `Sitemap::handlePostLoop()`, `RssFeed::handlePostLoop()`, and
  `Search::handlePostLoop()` all run exactly as today, consuming the (always-complete) aggregate
  state built up across `POST_RENDER`.

---

## 8. Out of Scope (v1)

- **Category/tag archive page caching.** These are rendered via
  `Application::renderSingleFile()` directly from `POST_LOOP` (`CategoryPageService::
  renderCategoryPage()`, `TagPageService`), bypassing `FileProcessor::processFile()` entirely.
  They are comparatively cheap to regenerate (template render only, no Markdown parsing) and
  depend on aggregate state that can change even when no individual content file's HTML changed
  (e.g., file added/removed, pagination boundaries shifting). Always fully regenerated, every
  build, regardless of `--incremental`. This is the conservative choice — correctness over speed
  for the aggregate pages, since they're the highest blast-radius if wrong.
- **Sitemap, RSS, search.json regeneration itself.** These are always regenerated in full from
  the (always-complete) in-memory aggregate state on every build. They're cheap (string
  concatenation / JSON encode), so there's no reason to cache them, and doing so would reintroduce
  exactly the staleness risk this plan exists to avoid.
- **Content-hash-based change detection** (vs. mtime). Not needed for v1; see section 4.
- **Template-change invalidation** (detecting that a `.twig` template changed and invalidating
  all pages that use it). Not handled — `--incremental` should be documented as "use with caution
  after editing templates" or the user should omit the flag (or pass `--clean`) after template
  changes. This could be a future enhancement (comparing template file mtimes too) but is YAGNI
  for v1.
- **Asset/image pipeline caching** — already handled by existing mtime logic in `ImageService`
  and `ImageVariantGenerator`; untouched by this plan.

---

## 9. Security Implications

- **Path handling**: `canReuseCachedOutput()` and `substituteCachedRender()` only operate on
  `$expectedOutputPath`, which is derived by the existing, unmodified `calculateOutputPath()`
  method — no new user-controlled path construction is introduced.
- **Information disclosure**: Reading back `public/<file>.html` and re-injecting it as
  `rendered_content` does not expose anything not already present in last build's public output —
  it's already a published file in the deploy target. No new attack surface.
- **Cache poisoning**: There is no separate cache store to poison (this is the core argument for
  avoiding SQLite/a state file in section 4). The only "cache" is the output file itself; an
  attacker able to write into `public/` already has equivalent or greater capability via direct
  modification of the deployed site.
- **Stale aggregate output as a security/SEO concern**: addressed exhaustively above (sections
  1, 6) — this was the primary risk and is mitigated by always firing `POST_RENDER` with complete
  data.
- **Denial of incremental benefit via clock skew**: if the system clock is wrong or files are
  copied with mismatched mtimes (e.g. via certain `git checkout` / CI checkout strategies that
  don't preserve mtimes), `canReuseCachedOutput()` fails safe to `false` → full render. Never fails
  unsafe (never causes incorrect skip due to a missing-mtime edge case, only causes unnecessary
  full renders, which is the safe direction).

---

## 10. Rollback / Interrupted-Build Safety

**No separate cache database means no separate corruption mode to detect.** The state being
consulted (`public/<file>.html`'s mtime) *is* the build output itself.

Scenarios:

1. **Build killed mid-way (e.g. `kill -9` during file N of M).** Files 1..N-1 have already been
   written with fresh mtimes (newer than source) — correct. File N may be partially written or
   missing — on the next build (incremental or not), `canReuseCachedOutput()` for file N will see
   either a missing file (`is_file()` false → full render) or, in the pathological case of a
   truncated write, a file that still exists with an mtime newer than source — **this is the one
   genuine gap**: a truncated/corrupt output file with a fresh mtime would be wrongly treated as
   cacheable.

   Mitigation: `writeOutputFile()` should write to a temp path and `rename()` into place atomically
   (rename is atomic on POSIX filesystems within the same directory) rather than writing directly
   via `file_put_contents()`. This is a **small, additive, backward-compatible change** to
   `FileProcessor::writeOutputFile()` (write to `$outputPath . '.tmp'`, then `rename()`), which
   eliminates the truncated-file risk entirely: a kill mid-write either leaves the old file
   untouched (if there was one) or leaves no file at all (if there wasn't) — both are safe states
   for the mtime check. This is the second (and last) `src/Core` change this plan requires.

2. **Files N+1..M were never reached.** They simply don't exist in `public/` yet (first build) or
   retain their previous output (subsequent build) — in the first-build case, `is_file()` is false
   so they get fully rendered on the next run, regardless of mode. No special "was this build
   complete" flag is needed because there is no full-build/partial-build distinction being
   tracked — every file is independently, idempotently checked against its own output artifact.

3. **No manual flag is needed to recover from an interrupted build.** The next `site:render`
   invocation (with or without `--incremental`) self-heals: any file lacking a valid, fresher
   output artifact is rendered fresh. This is strictly safer than a SQLite-based "last successful
   build" marker, which could itself be left in an inconsistent state by the same kill signal.

---

## 11. Testing Strategy

### Unit Tests (`tests/Unit/Core/FileProcessorIncrementalTest.php`)

1. `testSkipsRenderWhenOutputNewerThanSource()` — given a source file and a pre-existing output
   file with a newer mtime, assert the `RENDER` event is never fired (use a spy/mock
   `EventManager` or a test double event listener that increments a counter) and `POST_RENDER`
   *is* fired with `rendered_content` equal to the existing output file's contents.
2. `testFullRendersWhenSourceNewerThanOutput()` — touch the source file after creating the
   output file; assert `RENDER` fires and the new content is written.
3. `testFullRendersWhenOutputMissing()` — no pre-existing output file; assert `RENDER` fires.
4. `testFallsBackToFullRenderWhenCachedFileUnreadable()` — output file exists but
   `file_get_contents()` would fail (e.g., permissions); assert fallback to full `RENDER` rather
   than throwing.
5. `testIncrementalDisabledByDefault()` — with `INCREMENTAL_BUILD` unset/false in the container,
   assert every file always goes through `RENDER` regardless of output mtimes (i.e., confirm the
   opt-in gate itself).
6. `testAtomicWriteSurvivesPartialFailure()` — simulate a write failure mid-`writeOutputFile()`
   (e.g. inject a failure after temp write, before rename) and assert the original output file
   (if any) is left untouched.

### Integration Test — the explicit safety-invariant proof (point 6 of the brief)

`tests/Integration/IncrementalBuild/AggregateDataIntegrityTest.php`:

1. Create 3 content files (A, B, C) in a temp `content/` dir (via `vfsstream` or a real temp dir,
   consistent with existing integration test conventions — check
   `tests/Integration/` for the established pattern and mirror it), each with `category: news` and
   distinct `tags`.
2. Run `site:render` once (full build, no `--incremental`). Assert `sitemap.xml` contains 3 URLs,
   the `news` category page lists 3 files, the relevant tag archive pages list all 3, and
   `search.json` has documents from all 3.
3. Modify only file A's content (and update its mtime via `touch`). Leave B and C untouched.
4. Run `site:render --incremental` a second time.
5. Assert:
   - File A's rendered HTML reflects the new content (proves the change was picked up).
   - Files B and C's HTML output files are **byte-identical** to step 2's output and their mtimes
     are **unchanged** (proves their `RENDER` was actually skipped, not just "happened to produce
     the same output").
   - `sitemap.xml` **still contains exactly 3 URLs**, including B and C's, with correct `<loc>`
     values (proves the safety invariant: B and C's aggregate contribution survived their HTML
     being skipped).
   - The `news` category index page **still lists all 3 files**, including B and C with their
     correct titles/dates (proves `CategoryIndex`'s `POST_RENDER`-driven collection still saw B
     and C).
   - The relevant tag archive pages **still list B and C** (this one should pass even without any
     fix, since Tags is `POST_GLOB`-driven — but assert it anyway as a regression guard).
   - `search.json` **still contains documents for B and C**, not just A (proves `Search`'s
     `POST_RENDER`-driven collection survived).
6. Repeat the same assertions for `RssFeed` (`{category}/rss.xml` contains items for all 3 files,
   not just A).

This single integration test is the direct proof requested in the task brief: 3 files, 1 changed,
confirming every aggregate still correctly includes the 2 unchanged files despite their HTML not
being regenerated.

### Regression coverage for existing behavior

- Confirm all existing `tests/Unit/Features/{Sitemap,RssFeed,CategoryIndex,Tags,Search}/*Test.php`
  continue to pass unmodified — they exercise `POST_RENDER`/`POST_LOOP` handlers directly with
  hand-built parameter arrays, which this plan does not change the shape of.
- Confirm `--clean` and `--incremental` together still produce a full rebuild (per section 5),
  via a small integration test asserting `RENDER` fires for all files when both flags are passed.

---

## 12. Summary of Core Modifications Required

This plan requires exactly two changes inside `src/Core`, both confined to `FileProcessor.php`:

1. `processFile()`: insert the cache-check fork between `PRE_RENDER` and `RENDER`/`POST_RENDER`,
   gated behind a constructor/container flag, defaulting to fully-disabled (no behavior change)
   when the flag is off.
2. `writeOutputFile()`: switch from direct `file_put_contents()` to write-temp-then-`rename()` for
   atomicity, protecting against partial writes on interruption (this also benefits non-incremental
   builds for free — it's a general robustness improvement, not incremental-specific).

No other file in `src/Core` changes. No Feature outside `src/Features/SiteBuilder` (the command)
changes. Sitemap, RssFeed, CategoryIndex, Tags, and Search are untouched.
