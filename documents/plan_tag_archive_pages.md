# Plan: Tag Archive Pages

## Status: Draft for review

## 1. Overview

`src/Features/Tags/Services/TagsService.php` already collects every tag found in
content frontmatter (`POST_GLOB`) and exposes `tag_index`, `tag_counts`,
`all_tags`, and per-file `related_files` to templates (`PRE_RENDER`). What it
does **not** do is emit an actual HTML page per tag. `CategoryIndex` solves the
analogous problem for categories by deferring rendering of a matched content
file and re-invoking `Application::renderSingleFile()` once per pagination
page during `POST_LOOP`, with `output_path` and `file_metadata` overridden.

This plan extends the existing `Tags` feature (no new top-level feature) to
generate `/tags/{tag-slug}/index.html` and, when a tag has more files than the
configured page size, `/tags/{tag-slug}/page/{n}/index.html`, using the same
deferred-render mechanism as `CategoryIndex` — but **without requiring any
backing content file**, because tags (unlike categories) have no optional
"definition file" convention today and none should be introduced.

### Why extend `Tags` rather than create a new feature

- `Tags/Feature.php` already implements `FeatureInterface`, already has
  `POST_GLOB` (collect) and `PRE_RENDER` (expose) wiring, and already owns the
  `tagIndex` data structure that page generation needs. Splitting tag-page
  generation into a separate feature would force either (a) duplicating tag
  collection, or (b) an inter-feature dependency on `TagsService` internals —
  both worse than just adding `POST_RENDER` / `POST_LOOP` listeners to the
  feature that already owns the data, matching the precedent of `CategoryIndex`
  itself being one feature that does collection + paging + rendering together.
- Self-containment rule (Section 6 of `CLAUDE.md`) is satisfied either way; the
  deciding factor is avoiding duplicate tag-extraction logic and an unnecessary
  cross-feature coupling between `Tags` and a new `TagArchive`-type feature.

## 2. The exact synthesis mechanism to reuse (no backing file)

Confirmed by reading `src/Core/Application.php::renderSingleFile()`:

```php
public function renderSingleFile(string $filePath, array $additionalContext = []): array
```

There is no `is_file()`/`file_exists()`/`file_get_contents()` guard inside
`renderSingleFile()` itself. It builds the render context, merges
`$additionalContext` on top (including `file_metadata` and `output_path`),
and fires `PRE_RENDER` → `RENDER` → `POST_RENDER`. Whether disk is touched
depends entirely on which downstream listener tries to re-read `$filePath`.

`CategoryIndex` exploits this for **implicit categories**
(`CategoryService::collectFile()` creates a `Category` on the fly from
frontmatter `category: Foo` with zero `type: category` definition file ever
existing for it), proving the mechanism tolerates a `$filePath` argument that
is arbitrary/non-existent as long as:

1. `bypass_category_defer` (for tags: an analogous `bypass_tag_defer` flag) is
   set in the context so the feature's own `PRE_RENDER` listener doesn't
   re-defer the synthetic render into an infinite loop.
2. `file_metadata` is supplied directly, so `MarkdownRendererService` (or
   whichever feature would otherwise try to read+parse the file at
   `$filePath`) is bypassed — **this must be verified empirically in Testing
   Strategy** (see §6) since `MarkdownRenderer`'s own `PRE_RENDER`/`RENDER`
   priority and exact short-circuit condition were not re-read line-by-line in
   this research pass; the plan assumes it behaves like it does for
   CategoryIndex's implicit-category case, which already ships in production.
3. `output_path` is supplied, so no path derivation from `$filePath` is
   needed.

**Decision: Tags require no "tag definition file" convention.** Every tag
page is purely data-driven from `$this->tagIndex` (already populated by
`TagsService::extractTagsFromFile()`). There is one synthetic "virtual file
path" per tag, e.g. `__tag__:{slug}` (a string that can never collide with a
real discovered file path, analogous to how implicit categories use the
category name as the synthesis key instead of a real path). This sidesteps
needing any `pathinfo($filePath, PATHINFO_FILENAME)` slug derivation off a
real file — the slug comes directly from the tag string itself.

## 3. Data Structures

### New: `Models/TagFile.php` (mirrors `CategoryFile` exactly — same shape, new namespace)

```php
namespace EICC\StaticForge\Features\Tags\Models;

class TagFile
{
    public string $title;
    public string $url;
    public string $date;
    /** @var array<string, mixed> */
    public array $metadata = [];

    public function __construct(string $title, string $url, string $date, array $metadata = [])
    {
        $this->title = $title;
        $this->url = $url;
        $this->date = $date;
        $this->metadata = $metadata;
    }
}
```

No `image` field unless a future need arises (YAGNI — `CategoryFile.image`
exists because category pages show hero images; nothing in today's Tags
templates requests this, so omit it; add later if a template needs it).

### New: `Models/Tag.php` (mirrors `Category.php`, minus `menuPosition` — no menu integration in this plan, see §7)

```php
namespace EICC\StaticForge\Features\Tags\Models;

class Tag
{
    public string $slug;
    public string $name;       // original (lowercased) tag string, e.g. "php"
    /** @var TagFile[] */
    public array $files = [];

    public function __construct(string $slug, string $name)
    {
        $this->slug = $slug;
        $this->name = $name;
    }

    public function addFile(TagFile $file): void
    {
        $this->files[] = $file;
    }
}
```

### Existing structures — unchanged, still populated exactly as today

- `tagIndex`: `array<string tag, array<int, string filePath>>` — built in
  `TagsService::extractTagsFromFile()`. **Reused as-is** to drive page
  generation; no change to its shape or population logic.
- `tag_counts`: `array<string tag, int count>` via `getTagCounts()` —
  unchanged.
- `all_tags`: `array<int, string>` sorted alphabetically — unchanged.
- `related_files`: per-file array of related file paths via
  `getRelatedFilesByTags()` — unchanged.
- `parameters['features']['Tags']` (`all_tags`, `tag_index`, `tag_counts`) and
  `parameters['tag_data']` (`tags`, `related_files`, `all_tags`,
  `tag_counts`) exposed today **continue to be exposed identically** — this
  plan is purely additive.

### New, page-generation-only data (not exposed to non-tag-page templates)

Built fresh in the new `TagPageService` by resolving each `tagIndex` entry's
file paths against `discovered_files` metadata (same pattern as
`CategoryPageService::renderCategoryPage()` converts `Category->files` into
template-ready arrays):

```php
/** @var array<int, array{title:string,url:string,date:string,metadata:array<string,mixed>}> */
$filesArray
```

## 4. Class Structure

```
src/Features/Tags/
├── Feature.php                         (extended — see §5)
├── Models/
│   ├── Tag.php                         (new)
│   └── TagFile.php                     (new)
└── Services/
    ├── TagsService.php                 (unchanged)
    ├── TagPageService.php              (new — mirrors CategoryPageService)
    └── PaginationService.php           (new — see §4a, reuse-vs-duplicate decision)
```

### 4a. Pagination reuse decision: **duplicate, don't reuse `CategoryIndex\Services\PaginationService`**

`PaginationService` (`src/Features/CategoryIndex/Services/PaginationService.php`)
is pure, stateless, has zero `Container` dependency, and is already unit
tested (`tests/Unit/Features/CategoryIndex/Services/PaginationServiceTest.php`)
— on pure mergeability grounds it's reusable. However:

- Per `CLAUDE.md` §6, every feature must be **entirely self-contained within
  its own `src/Features/{FeatureName}` directory** for future extraction via
  `scripts/extract_feature.php`. If `Tags` imports
  `EICC\StaticForge\Features\CategoryIndex\Services\PaginationService`, then
  extracting `Tags` into a standalone package later breaks unless
  `CategoryIndex` is extracted first (or duplicated at that point anyway) —
  i.e. reuse today only defers the same duplication to extraction time, while
  *also* creating a live cross-feature coupling that violates "No Core
  Modification... unless absolutely unavoidable" in spirit (it's not core, but
  the principle of feature isolation is the same).
- The class is ~30 lines, has no dependencies, and copying it is strictly
  cheaper than introducing a `Tags → CategoryIndex` dependency that the
  extraction tooling cannot easily resolve.

**Decision: duplicate the small pure helper into
`src/Features/Tags/Services/PaginationService.php` and
`src/Features/Tags/Models/Pagination.php`, byte-for-byte identical in logic.**
Mirror the existing unit tests
(`tests/Unit/Features/CategoryIndex/Services/PaginationServiceTest.php`) into
`tests/Unit/Features/Tags/Services/PaginationServiceTest.php`. If duplication
across features becomes a recurring pattern (a third feature needing it), that
is the trigger to promote it to a shared, framework-level utility — not now
(YAGNI).

### `Models/Pagination.php` (new, identical shape to `CategoryIndex\Models\Pagination`)

```php
class Pagination
{
    public int $currentPage;
    public int $totalPages;
    public ?string $prevUrl;
    public ?string $nextUrl;

    public function __construct(int $currentPage, int $totalPages, ?string $prevUrl, ?string $nextUrl)
    {
        $this->currentPage = $currentPage;
        $this->totalPages = $totalPages;
        $this->prevUrl = $prevUrl;
        $this->nextUrl = $nextUrl;
    }
}
```

### `Services/TagPageService.php` (new)

Responsibilities, mirroring `CategoryPageService` 1:1 in structure:

```php
class TagPageService
{
    public function __construct(
        Log $logger,
        TagsService $tagsService,
        PaginationService $paginationService,
        int $itemsPerPage = 10
    ) {}

    // Called from Feature::handlePostLoop (NEW listener — Tags has no
    // POST_LOOP today). Iterates $tagsService->getAllTagsSorted(), and for
    // each tag with at least one file, calls renderTagPage().
    public function generateTagPages(Container $container): void;

    // Mirrors CategoryPageService::renderCategoryPage(), but:
    //  - $virtualFilePath = '__tag__:' . $slug   (no real file backing it)
    //  - resolves tagIndex[$tag] file paths into TagFile[] by cross-referencing
    //    $container->getVariable('discovered_files') metadata (title, date,
    //    metadata) the same way CategoryService::collectFile() builds
    //    CategoryFile from already-rendered content — EXCEPT tag pages don't
    //    have a "rendered_content" hook to extract a hero image from (no
    //    image field; see Models §3), so this is simpler than CategoryService.
    private function renderTagPage(string $tag, array $filePaths, Container $container): void;

    // Identical pattern to CategoryPageService::deriveCategoryUrl /
    // buildPagedOutputPath, with "/tags/{slug}/" instead of "/{slug}/".
    private function deriveTagUrl(string $page1OutputPath, Container $container): string;
    private function buildPagedOutputPath(string $page1OutputPath, int $page): string;

    private function sanitizeSlug(string $tag): string; // see §5 slug convention
}
```

Output path for page 1: `{OUTPUT_DIR}/tags/{slug}/index.html`
Output path for page N>1: `{OUTPUT_DIR}/tags/{slug}/page/{n}/index.html`

This is the same `/tags/` prefix nesting pattern category pages use for their
own slug (categories live at `/{slug}/`; tags are deliberately namespaced
under `/tags/{slug}/` to avoid URL collisions with category/page slugs of the
same name — e.g. a category "php" and a tag "php" must not both try to write
`/php/index.html`).

## 5. Event Pipeline Hooks

Extend `Tags/Feature.php`'s `$eventListeners`:

```php
protected array $eventListeners = [
    'POST_GLOB'  => ['method' => 'handlePostGlob',  'priority' => 150], // unchanged
    'PRE_RENDER' => ['method' => 'handlePreRender',  'priority' => 100], // unchanged
    'POST_LOOP'  => ['method' => 'generateTagPages', 'priority' => 110], // NEW
];
```

- **No `PRE_RENDER`-based deferral / `skip_file` is needed for tags**, unlike
  `CategoryIndex`. Categories defer because a real content file
  (`type: category`) exists in the loop and must be intercepted before normal
  rendering. Tags have no definition file in the loop to intercept — tag pages
  are generated entirely out-of-band in `POST_LOOP`, directly from
  `tagIndex`, with no real file ever reaching `PRE_RENDER` for a tag page.
  This is a **simpler** mechanism than `CategoryIndex`'s, not a smaller
  version of the same one.
- `generateTagPages` priority `110`: runs after `TagsService`'s own
  `tagIndex` is fully populated (done at `POST_GLOB`, long before `POST_LOOP`)
  and is independent of `CategoryIndex`'s `POST_LOOP` priority `100` — no
  ordering dependency between the two features exists or is needed, since each
  calls `Application::renderSingleFile()` for its own synthetic pages
  independently.
- Internally, `TagPageService::renderTagPage()` calls
  `$application->renderSingleFile($virtualFilePath, [...])` exactly like
  `CategoryPageService::renderCategoryPage()` does, with:
  ```php
  $application->renderSingleFile($virtualFilePath, [
      'file_metadata' => $enrichedMetadata,
      'output_path'   => $outputPath,
      'bypass_tag_defer' => true,   // analogous safety flag; Tags' own
                                     // PRE_RENDER handler must check this and
                                     // return early to avoid acting on its
                                     // own synthetic render pass (mirrors
                                     // CategoryIndex's bypass_category_defer)
  ]);
  ```
  Tags' `handlePreRender()` must add a one-line guard at the top:
  ```php
  if (!empty($parameters['bypass_tag_defer'])) {
      return $parameters;
  }
  ```
  matching `CategoryIndex\Feature::handlePreRender()`'s existing guard
  pattern exactly.

### Why `POST_RENDER`/Sitemap/Search automatically pick up tag pages

Confirmed: `src/Features/Sitemap/Feature.php` and `src/Features/Search/Feature.php`
both register `POST_RENDER` listeners (`handlePostRender`, priority 100) that
collect URL/content data, and separate `POST_LOOP` listeners (priority 100)
that write `sitemap.xml`/`search.json`. Because `renderSingleFile()` fires the
full `PRE_RENDER → RENDER → POST_RENDER` pipeline internally for every
synthetic tag page, **Sitemap and Search will pick up tag pages automatically
with no changes to either feature**, exactly as they already do for
`CategoryIndex`'s deferred category pages. The only requirement is that
`Tags`' `POST_LOOP` (`generateTagPages`, priority 110) executes — which it
will, since `Application` fires all registered `POST_LOOP` listeners
regardless of relative priority ordering between unrelated features; priority
only orders listeners *within* the same event, and each feature's `POST_LOOP`
work is independent (Sitemap/Search don't need tag pages to exist *before*
their own `POST_LOOP` runs, because page collection already happened
synchronously during each `renderSingleFile()` call inside `generateTagPages`,
not in Sitemap/Search's own `POST_LOOP`).

## 6. Slug Sanitization

`Tags` does **not** currently have its own slug sanitizer — tags today are
only lowercased/trimmed (`extractTagsFromFile()`: `strtolower(trim($tag))`),
never converted to a URL-safe slug, because no URL is generated for a tag
today.

**Decision: reuse the exact sanitization convention from
`CategoryService::sanitizeSlug()`**, duplicated (not imported, per the same
self-containment reasoning as §4a) into
`TagPageService::sanitizeSlug()`:

```php
private function sanitizeSlug(string $name): string
{
    $slug = strtolower($name);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug ?? '', '-');
}
```

Applied to the already-lowercased tag string from `tagIndex` keys (tags with
spaces or punctuation, e.g. `"web dev"`, become `web-dev`). This must be
applied consistently everywhere a tag-derived URL is built or compared, to
avoid two differently-spelled tags colliding on the same slug (e.g. `"web-dev"`
and `"web dev"` both sanitize to `web-dev` — acceptable collision, treated as
the same tag page, consistent with how `CategoryService` already handles
this for categories).

## 7. Templates

Confirmed: no `tag_index`, `tag_counts`, `related_files`, `tag_data`, or
`all_tags` references exist anywhere under `templates/` today — these are
already exposed to all templates via `parameters['tag_data']` /
`parameters['features']['Tags']` but nothing consumes them yet. A tag archive
page template is entirely new.

### New: `templates/sample/tag-index.html.twig` (mirrors `category-index.html.twig`)

New template variables exposed via `file_metadata` merge (same mechanism as
`CategoryPageService`'s `enrichedMetadata`):

| Variable | Type | Description |
|---|---|---|
| `tag` | string | The tag name (unsanitized, for display, e.g. `"Web Dev"` title-cased or as-stored) |
| `tag_slug` | string | The sanitized slug used in the URL |
| `tag_files` | array of `{title, url, date, metadata}` | Files for the current page only |
| `tag_files_count` | int | Count of files on current page |
| `total_files` | int | Total files across all pages for this tag |
| `current_page` | int | 1-indexed current page |
| `total_pages` | int | Total pages for this tag |
| `pagination_prev_url` | ?string | URL of previous page, or null |
| `pagination_next_url` | ?string | URL of next page, or null |
| `per_page` | int | Configured items-per-page |

Naming choice: `tag_files`/`tag_files_count` (not reusing `category_files`)
to avoid any risk of template variable collision if a future page somehow
ends up with both category and tag context merged (defensive naming only,
not a real scenario today).

### Backward compatibility (explicit)

`tag_index`, `tag_counts`, `all_tags`, `related_files`, and `tag_data` remain
populated and exposed **exactly as today**, unchanged in shape or timing
(`POST_GLOB`/`PRE_RENDER`), for every normal content page. The new
`tag_files`/`tag_slug`/etc. variables exist **only** on the synthetic tag
archive pages created in `POST_LOOP`, via `file_metadata` merge — they never
appear on ordinary content pages and do not alter `TagsService`'s existing
public methods (`getAllTagsSorted()`, `getTagCounts()`,
`getRelatedFilesByTags()` keep their current signatures).

## 8. Configuration

New, optional, with the same shape/precedent as `category_index.items_per_page`:

```yaml
tags:
  items_per_page: 10
```

Resolved in `Feature::register()` identically to
`CategoryIndex\Feature::resolveItemsPerPage()`:

```php
private function resolveItemsPerPage(): int
{
    $siteConfig = $this->container->getVariable('site_config') ?? [];
    $configured = $siteConfig['tags']['items_per_page'] ?? 10;
    return is_numeric($configured) && (int) $configured > 0 ? (int) $configured : 10;
}
```

No new Composer dependency. No `src/Core` modification.

## 9. Security Implications

- **Slug injection into output path**: `sanitizeSlug()` strips everything
  except `[a-z0-9-]`, so a malicious tag value (e.g. `../../etc/passwd`) in
  frontmatter cannot escape `{OUTPUT_DIR}/tags/{slug}/` — identical guarantee
  already relied upon by `CategoryService::sanitizeSlug()` in production.
  No additional path-traversal risk beyond what already exists for
  categories.
- **Tag/category slug collision**: namespacing tag pages under `/tags/{slug}/`
  (§4) rather than `/{slug}/` prevents a tag silently overwriting a category's
  (or any other top-level page's) output file. This is a deliberate design
  choice, not an incidental side effect — call out in code review.
- **No user input at request time**: all data originates from frontmatter
  already trusted at build time (same trust boundary as every other Feature
  in this codebase — StaticForge has no runtime request handling for content).
- **No new external dependencies, no new I/O beyond writing files already
  within `OUTPUT_DIR`** (enforced the same way `CategoryPageService` enforces
  it: `$outputDir . DIRECTORY_SEPARATOR . ...`, no `..` traversal possible
  post-sanitization).

## 10. Testing Strategy

Mirror `tests/Unit/Features/CategoryIndex/` structure exactly:

- `tests/Unit/Features/Tags/Services/PaginationServiceTest.php` — port
  `CategoryIndex`'s `PaginationServiceTest.php` cases verbatim against the new
  duplicated `Tags\Services\PaginationService`.
- `tests/Unit/Features/Tags/Services/TagPageServiceTest.php` — new. Cover:
  - Single tag, single page (no pagination links).
  - Single tag, files exceeding `itemsPerPage` → correct page count, correct
    `pagination_prev_url`/`pagination_next_url` on first/middle/last pages.
  - Slug sanitization for tags containing spaces/punctuation/uppercase.
  - Output path correctness for page 1 vs page N>1.
  - Verify `bypass_tag_defer` flag is passed through to `renderSingleFile()`
    context.
- `tests/Unit/Features/TagsFeatureTest.php` (existing file — extend) —
  add assertion that `POST_LOOP` listener is registered at the documented
  priority, and that `handlePreRender` returns parameters unchanged when
  `bypass_tag_defer` is set (mirrors the equivalent CategoryIndex test for
  `bypass_category_defer`).
- `tests/Unit/Features/Tags/Services/TagsServiceTest.php` (existing — no
  changes required; confirms backward compatibility claim in §7 by continuing
  to pass unmodified).
- **Integration verification (manual, before sign-off)**: run
  `lando php bin/staticforge.php site:render` against content with at least
  one tag whose file count exceeds the configured `items_per_page`, then
  confirm:
  1. `public/tags/{slug}/index.html` exists and lists the first page of files.
  2. `public/tags/{slug}/page/2/index.html` exists with the remainder and a
     working `pagination_prev_url` back to page 1.
  3. `public/sitemap.xml` contains both tag page URLs.
  4. `public/search.json` (or equivalent Search output) contains entries for
     both tag page URLs.
  5. Confirm empirically (per the caveat in §2) that no spurious
     `file_get_contents()`/markdown-parse error occurs for the virtual
     `__tag__:{slug}` path during `renderSingleFile()` — if `MarkdownRenderer`
     or any other `PRE_RENDER`/`RENDER` listener attempts to read the
     non-existent virtual path, add a `bypass_tag_defer`-style short-circuit
     to that listener (same as would already be required for
     `CategoryIndex`'s implicit categories if it weren't already proven
     working in production — if CategoryIndex's implicit-category pages
     render correctly today, by construction this concern is already
     resolved for the identical mechanism and needs no new code, only a
     confirmation step).

## 11. Explicit Non-Goals (deferred, not built in this plan)

- **Tag-cloud / tag-listing `COLLECT_MENU_ITEMS` integration.** `MenuBuilder`
  dispatches `COLLECT_MENU_ITEMS` from `MenuBuilderService::buildMenus()`
  (confirmed) and `CategoryIndex` already hooks it via
  `handleCollectMenuItems` → `MenuService::addCategoriesToMenu()`. A future
  `Tags` `MenuService` could follow the identical pattern. Not requested,
  not built here — noted as the natural next extension.
- **Tag hero images** (`TagFile.image`) — no template need identified; add
  only if/when a template requires it.
- **Cross-linking categories and tags** (e.g. "tags within this category") —
  out of scope; no requirement surfaced.
