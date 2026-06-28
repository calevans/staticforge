# Plan: Pagination for CategoryIndex

## 1. Overview

### Problem

`CategoryPageService::renderCategoryPage()` (in
`src/Features/CategoryIndex/Services/CategoryPageService.php`) currently collects every file
belonging to a category, sorts it once via `sortFiles()`, and renders exactly one output file
at `{OUTPUT_DIR}/{slug}/index.html` via a single call to `Application::renderSingleFile()`. All
files, regardless of count, land on one HTML page. There is no slicing, no `page/2`, `page/3`,
etc., and no pagination metadata exposed to Twig.

### Goal

Add pagination to the existing `CategoryIndex` feature, entirely within
`src/Features/CategoryIndex/`:

1. A configurable items-per-page value, `category_index.items_per_page` in `siteconfig.yaml`,
   defaulting to `10` if unset (no breaking change for sites that don't set it).
2. When a category has more files than the page size, generate `{slug}/index.html` (page 1)
   plus `{slug}/page/2/index.html`, `{slug}/page/3/index.html`, etc.
3. Every generated page (1..N) exposes pagination metadata to Twig: current page, total pages,
   prev URL (null on page 1), next URL (null on the last page).
4. Categories with file count `<= items_per_page` render exactly as they do today: a single
   `index.html`, no `page/2` directory created, identical template variables as today (with
   the new pagination variables simply present and reflecting a single-page state — see
   Section 6 "Backward Compatibility").
5. No new Composer dependencies, no `src/Core` changes, no modification to
   `src/Features/Categories` (unrelated feature — it rewrites *individual post* output paths
   into category subdirectories; it is not touched by this plan).

### Design Principle (KISS / SOLID / YAGNI)

This is additive slicing logic layered on the *existing* defer → process → render pipeline.
No new event hooks are needed. `CategoryPageService` already does one thing — turn a deferred
category file into rendered output — pagination is just "do that N times instead of once,"
which fits the Single Responsibility the service already has. We will extract a small,
focused `Paginator` helper class (pure, no I/O) so `CategoryPageService` stays a thin
orchestrator and the slicing/page-link math is independently unit-testable.

---

## 2. Data Structures

### 2.1 New value object: `Models/Pagination.php`

A plain DTO describing one page's pagination state. No behavior beyond construction —
keeps with the existing `Models/Category.php` / `Models/CategoryFile.php` style (public
typed properties, no getters).

```php
<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\CategoryIndex\Models;

class Pagination
{
    public int $currentPage;
    public int $totalPages;
    public ?string $prevUrl;
    public ?string $nextUrl;

    public function __construct(
        int $currentPage,
        int $totalPages,
        ?string $prevUrl,
        ?string $nextUrl
    ) {
        $this->currentPage = $currentPage;
        $this->totalPages = $totalPages;
        $this->prevUrl = $prevUrl;
        $this->nextUrl = $nextUrl;
    }
}
```

### 2.2 Deferred file record (existing, unchanged)

`CategoryPageService::$deferredFiles` keeps its current shape:
`array<int, array{file_path: string, output_path: string, metadata: array<string, mixed>}>`.
Pagination is computed at *process* time (inside `renderCategoryPage()`), not at *defer* time,
because the full file count for a category is only known once all files have been collected
(category files accumulate across the whole main loop via `POST_RENDER` →
`CategoryService::collectFile()`, finalized by the time `POST_LOOP` fires). The deferred
record's `output_path` continues to represent page 1's path; page 2..N paths are derived from
it inside the new pagination logic, not stored separately.

### 2.3 Config shape (siteconfig.yaml, new optional block)

```yaml
category_index:
  items_per_page: 10
```

If `category_index` or `items_per_page` is absent, default to `10`. Matches the existing
direct-array-access-with-`??`-default idiom used throughout the codebase (see Section 4.3).

---

## 3. Class Structure

```
src/Features/CategoryIndex/
├── Feature.php                          (unchanged — no new events needed)
├── Models/
│   ├── Category.php                     (unchanged)
│   ├── CategoryFile.php                 (unchanged)
│   └── Pagination.php                   (NEW — DTO, Section 2.1)
└── Services/
    ├── CategoryService.php              (unchanged)
    ├── ImageService.php                 (unchanged)
    ├── MenuService.php                  (unchanged)
    ├── CategoryPageService.php          (MODIFIED — orchestrates multi-page render)
    └── PaginationService.php            (NEW — pure slicing/URL-math helper)
```

### 3.1 `Services/PaginationService.php` (NEW)

Pure, stateless, no I/O, no Container dependency — easy to unit test in isolation. Two
responsibilities only: (a) compute total pages and slice an array into a given page's chunk,
(b) compute prev/next URLs for a given page number and category slug, following the URL
convention from Section 4.1.

```php
<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\CategoryIndex\Services;

use EICC\StaticForge\Features\CategoryIndex\Models\Pagination;

class PaginationService
{
    /**
     * @param array<int, mixed> $items
     * @return array<int, mixed>
     */
    public function sliceForPage(array $items, int $page, int $itemsPerPage): array
    {
        $offset = ($page - 1) * $itemsPerPage;
        return array_slice($items, $offset, $itemsPerPage);
    }

    public function totalPages(int $totalItems, int $itemsPerPage): int
    {
        if ($totalItems === 0) {
            return 1;
        }
        return (int) ceil($totalItems / $itemsPerPage);
    }

    public function buildPagination(int $currentPage, int $totalPages, string $categoryUrl): Pagination
    {
        $prevUrl = $currentPage > 1
            ? $this->pageUrl($categoryUrl, $currentPage - 1)
            : null;

        $nextUrl = $currentPage < $totalPages
            ? $this->pageUrl($categoryUrl, $currentPage + 1)
            : null;

        return new Pagination($currentPage, $totalPages, $prevUrl, $nextUrl);
    }

    /**
     * Page 1 is always the bare category URL ("/{slug}/").
     * Page N>1 is "/{slug}/page/{n}/".
     */
    public function pageUrl(string $categoryUrl, int $page): string
    {
        $base = rtrim($categoryUrl, '/');
        return $page <= 1
            ? $base . '/'
            : $base . '/page/' . $page . '/';
    }
}
```

`itemsPerPage` is passed in by the caller (`CategoryPageService`), which reads it once from
config — `PaginationService` itself has zero knowledge of `Container` or `siteconfig.yaml`,
keeping it a pure, reusable, trivially-testable unit (DRY: the slicing math isn't duplicated
inline in `CategoryPageService`; SRP: config-reading stays in the orchestrator).

### 3.2 `Services/CategoryPageService.php` (MODIFIED)

Constructor gains the new `PaginationService` and the configured `itemsPerPage` int,
injected — not `new`'d inline mid-method — consistent with Golden Rule "Dependency Injection:
NEVER `new` up services inside other services if possible. Use the Container." Because
`PaginationService` has no external dependencies of its own (no logger, no Container), and
`Feature::register()` is the single composition point for this feature already (it currently
does `new ImageService(...)`, `new CategoryService(...)`, etc., directly — there is no DI
container-based service resolution for this feature's internals today), the pattern below
matches existing precedent: `Feature::register()` instantiates `PaginationService` and passes
it into `CategoryPageService`'s constructor, exactly as it already does for `CategoryService`
being passed into `CategoryPageService`.

```php
public function __construct(
    Log $logger,
    CategoryService $categoryService,
    PaginationService $paginationService,
    int $itemsPerPage
) {
    $this->logger = $logger;
    $this->categoryService = $categoryService;
    $this->paginationService = $paginationService;
    $this->itemsPerPage = $itemsPerPage;
}
```

`renderCategoryPage()` is restructured: instead of rendering once, it now:

1. Builds `$filesArray` from `$category->files` (unchanged).
2. Sorts via the existing private `sortFiles()` (unchanged — sorting must happen before
   slicing, otherwise pages would show inconsistent ordering).
3. Computes `$totalPages = $this->paginationService->totalPages(count($filesArray), $this->itemsPerPage)`.
4. Loops `for ($page = 1; $page <= $totalPages; $page++)` and for each page:
   - Slices the sorted array via `sliceForPage()`.
   - Derives this page's `output_path` from the deferred record's *category slug* and page
     number (Section 4.1) — page 1 reuses the original `output_path` exactly as today;
     page 2+ build a new path under `{OUTPUT_DIR}/{slug}/page/{n}/index.html`.
   - Builds `$pagination = $this->paginationService->buildPagination($page, $totalPages, $categoryUrl)`.
   - Merges `category_files`, `category_files_count`, `total_files` (unchanged keys, but now
     holding the *sliced* page's data, not the full set) plus the new pagination keys
     (Section 6) into `$enrichedMetadata`.
   - Calls `$application->renderSingleFile($filePath, [...])` once per page, exactly as today
     but with per-page `output_path` and `file_metadata`.
5. The existing global-context update (`$features['CategoryIndex']['category_files']`) is set
   per-page immediately before that page's `renderSingleFile()` call, mirroring current
   behavior (it's a side-channel for any other feature reading `category_files` off the
   `features` container variable mid-render — keeping this update co-located with each
   render call preserves existing semantics for page 1 and extends it correctly to page N).

No changes to `deferFile()`, `processDeferredFiles()`'s outer loop, or `sortFiles()` itself.

### 3.3 `Feature.php` (register() only — MODIFIED)

```php
public function register(EventManager $eventManager): void
{
    parent::register($eventManager);

    $this->logger = $this->container->get('logger');

    $imageService = new ImageService($this->logger);
    $this->categoryService = new CategoryService($this->logger, $imageService);
    $paginationService = new PaginationService();
    $itemsPerPage = $this->resolveItemsPerPage();
    $this->pageService = new CategoryPageService(
        $this->logger,
        $this->categoryService,
        $paginationService,
        $itemsPerPage
    );
    $this->menuService = new MenuService($this->logger);

    $this->logger->log('INFO', 'CategoryIndex Feature registered');
}

private function resolveItemsPerPage(): int
{
    $siteConfig = $this->container->getVariable('site_config') ?? [];
    $configured = $siteConfig['category_index']['items_per_page'] ?? 10;

    return is_numeric($configured) && (int) $configured > 0
        ? (int) $configured
        : 10;
}
```

This follows the exact existing idiom found in `RssFeedService` / `SearchAssetService` /
`SearchIndexService`: `$container->getVariable('site_config')['some_key']['nested_key'] ?? default`.
No new helper added to `Container` itself (Golden Rule: no `src/Core` modification).

`Feature` additionally implements `ConfigurableFeatureInterface` (it currently implements only
`FeatureInterface`) purely to document the new optional config key for `site:check`:

```php
class Feature extends BaseFeature implements FeatureInterface, ConfigurableFeatureInterface
{
    ...
    public function getRequiredConfig(): array
    {
        return [];
    }

    public function getRequiredEnv(): array
    {
        return [];
    }
}
```

Both return empty arrays — `category_index.items_per_page` is **optional** (has a sane
default), so it must NOT be listed as required, otherwise `site:check` would start failing
sites that rely on the default. This mirrors how `RssFeed` does NOT implement
`ConfigurableFeatureInterface` at all when nothing is strictly required — but `CategoryIndex`
already would benefit from declaring the (optional) key existence for documentation/discovery
purposes via an empty required array. (Open question for implementer — see Section 8 — this
piece is genuinely optional scaffolding, omit it if it adds no real value.)

---

## 4. URL / Output Path Convention

### 4.1 Path convention (new)

Matches the established "pretty URL" convention from `CategoryPageService::deferFile()`
(`{OUTPUT_DIR}/{slug}/index.html`), extended for subsequent pages:

| Page | Output path                                          | Resulting URL           |
|------|-------------------------------------------------------|--------------------------|
| 1    | `{OUTPUT_DIR}/{slug}/index.html`                       | `{site}/{slug}/`         |
| 2    | `{OUTPUT_DIR}/{slug}/page/2/index.html`               | `{site}/{slug}/page/2/`  |
| 3    | `{OUTPUT_DIR}/{slug}/page/3/index.html`               | `{site}/{slug}/page/3/`  |

This was independently confirmed as the only sane convention to use: there is no existing
`page/N` logic anywhere else in `src/` to align with (grep for `paginat`/`page/`/`/page` across
`src/` returns only a stale docblock comment in `CategoryIndex/Feature.php` referencing
"pagination" that was never implemented). The chosen convention is consistent with how
`SitemapService::normalizeUrl()` already treats any `.../index.html` as resolving to its
parent directory URL with a trailing slash — so paginated category URLs fall out of existing
sitemap normalization with zero changes to `Sitemap`.

### 4.2 Category URL (needed for prev/next link computation)

`PaginationService::buildPagination()` needs the category's own base URL (page-1 URL, no
trailing `page/N`) to build prev/next links for any page. This is derived inside
`CategoryPageService::renderCategoryPage()` from the *page-1* `output_path` already present in
the deferred record (`$fileData['output_path']`), using the same relative-URL derivation logic
that `CategoryService::collectFile()` already uses elsewhere (strip `OUTPUT_DIR` prefix,
normalize separators, ensure leading slash) — reused via a small private helper inside
`CategoryPageService`, not duplicated ad hoc. Concretely:

```php
private function deriveCategoryUrl(string $page1OutputPath, Container $container): string
{
    $outputDir = rtrim($container->getVariable('OUTPUT_DIR'), '/\\') . DIRECTORY_SEPARATOR;
    $relative = str_replace('\\', '/', substr($page1OutputPath, strlen($outputDir)));
    $relative = preg_replace('#/?index\.html$#', '/', $relative) ?? $relative;
    return '/' . ltrim($relative, '/');
}
```

This yields `/{slug}/` from `{OUTPUT_DIR}/{slug}/index.html`, which `PaginationService::pageUrl()`
then appends `page/{n}/` onto for n > 1.

### 4.3 Config-read idiom (confirmed existing precedent)

Direct array access against `site_config`, no centralized config-getter exists in this
codebase:

```php
// RssFeedService.php:115-117
$siteConfig = $container->getVariable('site_config') ?? [];
$siteInfo = $siteConfig['site'] ?? [];
$siteName = $siteInfo['name'] ?? $container->getVariable('SITE_NAME') ?? 'My Site';

// SearchIndexService.php:219-221
$config = $container->getVariable('site_config')['search'] ?? [];
$excludePaths = $config['exclude_paths'] ?? [];
```

`resolveItemsPerPage()` in Section 3.3 follows this exact pattern.

---

## 5. Event Pipeline Hooks

**No new events, no changes to existing event registrations.** Pagination is implemented
entirely inside the existing `POST_LOOP` → `processDeferredCategoryFiles()` →
`CategoryPageService::processDeferredFiles()` → `renderCategoryPage()` call chain. Each page
of each category is still rendered via `Application::renderSingleFile()`, which itself fires
`PRE_RENDER` → `RENDER` → `POST_RENDER` per call — meaning:

- `Sitemap`'s `handlePostRender` (priority 100, generic `POST_RENDER` listener) will pick up
  **every** paginated page automatically, with zero changes to `Sitemap\Feature.php` or
  `SitemapService.php`. Each page's distinct `output_path` normalizes to its own canonical URL
  per Section 4.1.
- `Search`'s `handlePostRender` (`SearchIndexService::collectPage`) will likewise index every
  paginated page automatically, including page 2+, since it is also a generic `POST_RENDER`
  listener with no awareness of CategoryIndex internals.

  **Decision**: index/sitemap *all* pages, including page 2+, not just page 1. Rationale: each
  paginated page contains genuinely distinct content (a different slice of category files), so
  it is not a duplicate in the way pure UI-pagination-of-identical-content would be; search
  engines and our own site search should be able to surface a result that happens to live on
  category page 3. No frontmatter (`search_index: false`) or `search.exclude_paths` change is
  needed or recommended. If the site owner wants only page 1 indexed/sitemapped, the existing
  `search.exclude_paths` config and a sitemap-side exclusion (out of scope — would need a small
  `Sitemap` change, not requested here) remain the available escape hatches, but are NOT part
  of this plan's default behavior.
- `CategoryIndex`'s own `handlePreRender` (priority 150) re-checks `bypass_category_defer` on
  every one of these per-page `renderSingleFile()` calls — already passed as `true` today, and
  will continue to be passed for every page, preventing infinite re-deferral. No change needed
  here; just confirming the existing guard remains correct under the new multi-call loop.

---

## 6. Template Variables (Twig) — Additive, Non-Breaking

### Existing variables (unchanged names, semantics now apply per-page)

From `CategoryPageService::renderCategoryPage()`'s `$enrichedMetadata`, consumed by
`templates/staticforce/category.html.twig` and `templates/sample/category-index.html.twig`:

- `category_files` — now holds only the **current page's slice**, not the full category list.
- `category_files_count` — now reflects the **current page's** item count (e.g. 10, or fewer
  on the last page).
- `total_files` — kept as the **grand total** across all pages (not the per-page count), since
  existing templates use this to show "`{{ total_files }} files in this category`" — changing
  its meaning to "count on this page" would look wrong/regress that existing UI copy. This is
  the one place where a judgment call was needed; documented here explicitly per Golden Rule 2.

### New variables (additive — never present before, so no existing template breaks)

Exposed via the same `$enrichedMetadata` array merged into `file_metadata`, available in Twig
the same way `total_files` already is today (i.e. directly, since `MarkdownRenderer`/whatever
consumes `file_metadata` already flattens these into top-level Twig context — confirmed by
existing templates using bare `{{ total_files }}`, not `{{ file_metadata.total_files }}`):

| Variable          | Type        | Description                                              |
|-------------------|-------------|------------------------------------------------------------|
| `current_page`    | int         | 1-indexed page number of this rendered page.              |
| `total_pages`     | int         | Total number of pages for this category (always >= 1).    |
| `pagination_prev_url` | string\|null | URL of the previous page, or `null` on page 1.        |
| `pagination_next_url` | string\|null | URL of the next page, or `null` on the last page.     |
| `per_page`        | int         | The configured (or default) items-per-page value.          |

Naming note: `per_page` already exists as a Twig variable in
`templates/sample/category-index.html.twig` today (`data-per-page="{{ per_page }}"`), but it is
currently **undefined** in the render context (nothing in `CategoryPageService` sets a
`per_page` key today — it would currently render as empty string in that template). This plan
makes `per_page` an actual, populated value for the first time, which is a strict improvement
and not a behavior change for any site (an undefined Twig var renders as empty string either
way; now it renders the real configured number). `current_page` / `total_pages` /
`pagination_prev_url` / `pagination_next_url` are new names chosen to avoid clashing with the
already-templated, differently-scoped `total_files`/`category_files_count`.

### Backward Compatibility (Section 1, requirement 5)

For a category with `count($filesArray) <= itemsPerPage`:
- `totalPages()` returns `1`.
- The render loop runs exactly once (`for ($page = 1; $page <= 1; $page++)`).
- Page 1's `output_path` is unchanged: `{OUTPUT_DIR}/{slug}/index.html` (identical to today).
- `category_files` / `category_files_count` hold the full (only) page, identical to today's
  output.
- `current_page = 1`, `total_pages = 1`, `pagination_prev_url = null`, `pagination_next_url = null`.
- No `page/2/` directory or file is ever created.
- Existing templates that don't reference the new variables render byte-for-byte identically
  to today (the new keys are simply additional, unused entries in the metadata array passed to
  Twig — Twig does not error on unused context variables).

---

## 7. Security Implications

- **Config value sanitization**: `items_per_page` is read from `siteconfig.yaml`, a
  trusted, repo-controlled file (not user input) — same trust level as every other config
  value already read this way (`search.engine`, `site.name`, etc.). Still, defensively coerce
  with `is_numeric($configured) && (int) $configured > 0` before casting (Section 3.3), falling
  back to the default `10` for zero/negative/non-numeric misconfiguration, to avoid a
  pathological `items_per_page: 0` causing a division-by-zero or infinite-page scenario in
  `PaginationService::totalPages()`.
- **No new file-write surface beyond existing pattern**: every paginated page is written via
  the same `Application::renderSingleFile()` → `writeOutputFile()` path already used for page 1
  today; `output_path` for page N is built from the category slug (already sanitized via
  `CategoryService::sanitizeSlug()` at category-discovery time — `preg_replace('/[^a-z0-9]+/', '-', ...)`)
  and a purely-internal integer loop counter (`$page`), never from unsanitized user/content
  input. No path traversal surface is introduced.
- **No new output exposed to templates beyond pagination counters/URLs** — `pagination_prev_url`
  / `pagination_next_url` are derived entirely from the already-sanitized category slug and an
  internally-generated integer; no raw frontmatter or file content flows into these URLs.
- **DoS/resource consideration**: a category with an extremely large file count combined with a
  very small `items_per_page` (e.g. 1) could generate a very large number of output files/pages.
  This is a site-owner-controlled config tradeoff (same class of risk as setting RSS feed item
  counts too high), not a security vulnerability — no mitigation beyond the existing numeric
  sanity check is proposed, per Golden Rule "No error handling for scenarios that cannot
  happen" / YAGNI (this is a configuration-correctness concern for the site owner, not an
  attacker-controlled input).

---

## 8. Testing Strategy

All new logic lands under `tests/Unit/Features/CategoryIndex/`, mirroring the existing test
layout for this feature (confirm exact existing test file names before writing — read
`tests/Unit/Features/CategoryIndex/` directory listing first; do not guess naming).

### 8.1 `PaginationServiceTest.php` (NEW — pure unit tests, no mocks needed)

- `totalPages()`: 0 items → 1 page; exactly `itemsPerPage` items → 1 page; `itemsPerPage + 1`
  items → 2 pages; exact multiples (e.g. 20 items / 10 per page → exactly 2 pages, not 3).
- `sliceForPage()`: page 1 returns first N items; page 2 returns next N; last partial page
  returns the remainder only (e.g. 25 items / 10 per page / page 3 → 5 items); out-of-range
  page (e.g. page 5 of a 2-page set) returns an empty array (defensive — caller's loop bound
  prevents this in practice, but the method itself should not throw).
- `pageUrl()`: page 1 → `/{slug}/`; page 2 → `/{slug}/page/2/`; trailing-slash idempotency
  (passing `/{slug}/` vs `/{slug}` as input both produce the same output).
- `buildPagination()`: page 1 of 3 → `prevUrl === null`, `nextUrl === '/{slug}/page/2/'`;
  page 2 of 3 → both prev/next set; page 3 of 3 (last) → `nextUrl === null`; single-page case
  (1 of 1) → both null.

### 8.2 `Services/CategoryPageServiceTest.php` (MODIFY existing — read it first)

Read the existing test file in full before modifying (Golden Rule 2: read before writing).
Add/extend cases:

- Category with file count `<= itemsPerPage` (e.g. 5 files, default 10/page): assert exactly
  one `renderSingleFile()` call (mock/spy on `Application`), with `output_path` identical to
  today's expected single-page path, and `current_page === 1`, `total_pages === 1` in the
  passed `file_metadata`.
- Category with file count `> itemsPerPage` (e.g. 25 files, 10/page): assert exactly 3
  `renderSingleFile()` calls, with output paths `.../slug/index.html`,
  `.../slug/page/2/index.html`, `.../slug/page/3/index.html` respectively, and each call's
  `file_metadata['category_files']` containing the correct slice (10, 10, 5 items
  respectively), correct `current_page`/`total_pages` per call, and correct
  `pagination_prev_url`/`pagination_next_url` per call (null at the two boundaries).
- Confirm `total_files` in every page's metadata equals the grand total (25), not the per-page
  slice count, per the Section 6 design decision.
- Confirm sort order is computed once on the full set *before* slicing (e.g. with
  `sort_direction: desc` by date, the newest 10 files land on page 1, not a re-sorted subset).

### 8.3 `Feature.php` config resolution (NEW small test, or extend existing `FeatureTest.php`)

- `resolveItemsPerPage()` (or equivalent, if made testable via a small refactor — e.g. extract
  it to a tiny pure function/static method if the existing `FeatureTest.php` pattern mocks
  `Container::getVariable()` already) returns `10` when `site_config` has no `category_index`
  key at all; returns `10` when `category_index.items_per_page` is present but `0`, negative,
  or non-numeric; returns the configured int when valid (e.g. `5`).

### 8.4 Integration check (manual QA step per CLAUDE.md Section 8.B.4)

After implementation, run `lando php bin/staticforge.php site:render` against a test category
with > `items_per_page` files in `content/`, and manually verify:
- `public/{slug}/index.html` exists and shows only the first page of items, with working
  "next" link.
- `public/{slug}/page/2/index.html` exists, shows the next slice, with working "prev" and
  "next" (or no "next" if it's the last page) links.
- `public/sitemap.xml` contains entries for both `/{slug}/` and `/{slug}/page/2/` (and beyond).
- A category with file count `<= items_per_page` produces no `page/` subdirectory at all.

---

## 9. Open Questions for Developer / Reviewer (per Operational Limits, Section 9 of CLAUDE.md)

These are flagged rather than decided unilaterally, since they go slightly beyond the literal
ask and the user should confirm before more scope is added:

1. Should `Feature.php` implement `ConfigurableFeatureInterface` with empty required arrays
   purely for documentation (Section 3.3), or is that unnecessary ceremony for an optional
   config key? Lean: skip it unless the developer finds it genuinely useful — it adds no
   runtime behavior, only documentation value via `site:check`.
2. Section 6 flags that `search.exclude_paths` / sitemap-exclusion for page 2+ is NOT part of
   this plan's default behavior (all pages get indexed/sitemapped). If the user actually wants
   only page 1 canonical URLs in the sitemap/search index, that is additional scope beyond what
   was asked and should be raised explicitly before implementing.
3. The exact existing test file names/paths under `tests/Unit/Features/CategoryIndex/` were not
   read in this planning pass (only inferred from project convention) — the Developer must read
   them first per Golden Rule 2 before writing/extending tests.
