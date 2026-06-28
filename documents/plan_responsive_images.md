# Plan: ResponsiveImages Feature

## 0. Research Findings (read before implementing)

**Imagick precedent** — `src/Features/CategoryIndex/Services/ImageService.php`:
- Guards with `class_exists('Imagick')`, wraps in `try { ... } catch (\Exception $e)`.
- API sequence: `new Imagick($path)` → `thumbnailImage($w, $h, true)` (third arg = "best fit", preserves aspect ratio) → `setImageFormat('jpeg')` → `setImageCompressionQuality(85)` → `writeImage($path)` → `clear()`.
- Cache check pattern: `file_exists($thumbPath) && filemtime($thumbPath) >= filemtime($imagePath)` — skip regeneration if the output is newer than the source. **This is the exact mtime-cache pattern to reuse.**
- On failure: logs `ERROR` via `Log::log()` and falls back to returning the original/unmodified reference — never throws, never breaks the build.
- Output directory created with `mkdir($dir, 0755, true)` guarded by `is_dir()` check.

**HTML parsing precedent** — `src/Features/TableOfContents/Services/TableOfContentsService.php`:
- Uses core `DOMDocument` + `DOMXPath`, not `symfony/dom-crawler` (that package is in `composer.json` but this precedent doesn't use it for content HTML manipulation).
- Load pattern: `libxml_use_internal_errors(true)`; `$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)`; `libxml_clear_errors()`. The `LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD` flags avoid DOMDocument auto-wrapping the fragment in `<html><body>`, and the `<?xml encoding="utf-8"?>` prefix avoids mangling multi-byte characters — both needed because the input is an HTML *fragment*, not a full document.
- Mutates DOM in place (e.g., removes a child node, clones nodes) then the caller re-serializes. For ResponsiveImages we need `$dom->saveHTML()` (or per-node `saveHTML($node)`) to get back a fragment, then splice that into the original content (see Class Structure below for exact splice strategy, since `saveHTML()` on a `LIBXML_HTML_NOIMPLIED` doc returns the full fragment cleanly).

**composer.json**: no new dependency needed. `DOMDocument`/`DOMXPath` are core PHP (`ext-dom`, always present). `ext-imagick` already confirmed enabled. `symfony/dom-crawler` exists in composer.json but is not the established precedent for content HTML — we follow `DOMDocument`/`DOMXPath` per TableOfContents, for consistency with the only existing in-repo precedent for parsing rendered HTML.

**Event timing** (`src/Core/FileProcessor.php::processFile()`):
1. `PRE_RENDER` fires → `RENDER` fires → at this point `rendered_content` and `output_path` are both set in `$renderContext` (enforced: if either is missing, a `FileProcessingException` is thrown and POST_RENDER never fires).
2. `POST_RENDER` fires next, **before** `writeOutputFile()` is called. Any feature listening on `POST_RENDER` can mutate `$renderContext['rendered_content']` and that mutation is what gets written to disk. This is the correct and only hook point to rewrite `<img>` tags in final HTML before it lands in `public/`.
3. Core then calls `writeOutputFile($renderContext['output_path'], $renderContext['rendered_content'])` directly — no further events.

**Critical timing gotcha — asset copy order**: `TemplateAssets` feature (`src/Features/TemplateAssets/Services/TemplateAssetsService.php`) copies `content/assets/` and `templates/{template}/assets/` into `OUTPUT_DIR/assets/` on the **`POST_LOOP`** event (priority 100) — which fires only *after* the entire per-file loop (and thus every file's `POST_RENDER`) has completed. This means: **at POST_RENDER time, content images referenced via `/assets/...` URLs do not yet exist under `OUTPUT_DIR`.** ResponsiveImages must read source images from their pre-copy location — `SOURCE_DIR/assets/...` (content assets) — not from `OUTPUT_DIR`. It must NOT depend on `OUTPUT_DIR/assets/...` existing at POST_RENDER time. (Template-provided images under `templates/{template}/assets/` are a secondary, lower-priority source — see Section on Source Resolution.)

Confirmed `<img>` convention from existing content (`content/features/template-assets.md`): `<img src="/assets/images/hero.jpg" alt="Hero Image">` — root-relative URL, mapping to `content/assets/images/hero.jpg` on disk (or template assets dir as fallback, mirroring `TemplateAssetsService`'s own two-tier copy order: template assets first, then content assets override).

**Event listener / handler signature precedent** (`Tags`, `TableOfContents`, `TemplateAssets`, `RssFeed` Features): All event handlers share the signature `handleX(Container $container, array $parameters): array`, registered via the `protected array $eventListeners` map on `BaseFeature`, instantiated/wired in `register(EventManager $eventManager)` using `$this->container->get('logger')` then manually `new`-ing the Service (composition root pattern — this is the one place `new` is acceptable per Golden Rules).

**Config precedent** (`siteconfig.yaml.example`, `Tags::resolveItemsPerPage()`): top-level key named after the feature (snake_case), read via `$container->getVariable('site_config')[...] ?? <default>`, with type/range validation before casting.

---

## 1. Overview

`ResponsiveImages` is a new core Feature (`src/Features/ResponsiveImages/`) that post-processes the final rendered HTML of each page (on `POST_RENDER`) to:

1. Find local (non-external) `<img>` tags in the rendered HTML.
2. Resolve each `src` to a real file on disk (content assets, falling back to template assets).
3. Generate a configurable set of resized raster variants (and optionally WebP) via Imagick, written directly into `OUTPUT_DIR`, skipping regeneration when an up-to-date variant already exists (mtime check, same pattern as `CategoryIndex\ImageService`).
4. Rewrite the `<img>` tag into a `<picture>` element with `<source>` entries for WebP/`srcset` and a `srcset`+`sizes` attribute on a fallback `<img>`, so browsers select the appropriately sized/encoded asset.
5. On any failure (missing file, corrupt image, unsupported format, Imagick exception) for a given `<img>`, log a `WARNING` and leave that tag completely untouched — never fail the build, never touch other tags on the page.

The feature is **opt-in, disabled by default** (`responsive_images.enabled: false`). Rationale in Section 7.

This feature does not depend on, call into, or share a class with `CategoryIndex\Services\ImageService`. The small Imagick-wrapping logic is duplicated into this feature's own service, consistent with the established precedent of `Tags` duplicating `CategoryIndex`'s `PaginationService` rather than creating a cross-feature dependency — features must remain extractable in isolation per Section 6 of `CLAUDE.md`.

## 2. Data Structures

No new persistent data structures or database tables (StaticForge has no DB requirement for this). All state is either:

- **Config** (parsed from `siteconfig.yaml`, see Section 3).
- **Transient in-memory** during a single `POST_RENDER` invocation: a list of `<img>` candidates found in the DOM for the current page.
- **On-disk cache implied by mtime comparison**: generated variant files in `OUTPUT_DIR` themselves are the cache; no separate manifest/index file. (A general hash-based build cache is explicitly out of scope — see Gap 5 elsewhere; this feature uses only simple mtime comparison, matching `CategoryIndex\ImageService::generateThumbnail()`.)

### Internal value object (not persisted, just a typed return shape inside the Service)

```php
/**
 * @phpstan-type ImageVariant array{width: int, path: string, url: string, format: string}
 */
```
Plain associative arrays are sufficient here — no need for a dedicated Model class per YAGNI; the codebase's existing precedent (`Tags`, `TableOfContents`) also avoids DTOs for simple internal shapes.

## 3. Configuration

Added to `siteconfig.yaml.example`, following the `category_index` / `tags` block precedent:

```yaml
# Responsive Images Configuration
# Generates resized image variants and rewrites <img> tags to <picture>/srcset
# for content images referenced in rendered HTML (markdown or raw HTML pages).
responsive_images:
  # Master switch. Disabled by default: changes asset output substantially
  # and adds build time proportional to image count. Enable deliberately.
  enabled: false

  # Breakpoint widths (px) to generate. Each generates one resized file
  # per configured format. Source images narrower than a given width are
  # skipped for that width (no upscaling).
  widths: [400, 800, 1200]

  # Also generate WebP variants alongside the original format.
  webp: true

  # JPEG/WebP compression quality (1-100).
  quality: 82

  # Directory (relative to OUTPUT_DIR) where generated variants are written.
  output_dir: "assets/images/responsive"

  # Minimum source image width (px) below which the feature leaves the
  # <img> tag untouched (not worth generating srcset for tiny icons).
  min_source_width: 400
```

`ResponsiveImages\Feature` implements `ConfigurableFeatureInterface`:
- `getRequiredConfig(): array` → `[]` (all keys optional; feature is fully opt-in with safe defaults; `enabled` itself is the only meaningful gate and absence of the whole block simply means disabled).
- `getRequiredEnv(): array` → `[]` (no environment dependency).

## 4. Class Structure

```
src/Features/ResponsiveImages/
├── Feature.php
└── Services/
    ├── ResponsiveImageConfig.php
    ├── ImageVariantGenerator.php
    └── HtmlImageRewriterService.php
```

### `Feature.php`
- `protected string $name = 'ResponsiveImages';`
- `protected array $eventListeners = ['POST_RENDER' => ['method' => 'handlePostRender', 'priority' => 150]];`
  - Priority 150 chosen to run *after* feature-internal HTML mutations that might still be adding/normalizing markup (e.g. TableOfContents augments heading HTML on `MARKDOWN_CONVERTED`, which is earlier in the pipeline and unrelated; within `POST_RENDER` itself, no other core feature currently listens — 150 leaves headroom for any feature that wants to run before image rewriting, and our work should be one of the last HTML mutations since it operates on final markup).
- `register(EventManager $eventManager)`:
  - `parent::register($eventManager)`.
  - Reads `site_config` from container once: `$config = ResponsiveImageConfig::fromSiteConfig($this->container->getVariable('site_config') ?? [])`.
  - If `!$config->enabled`, logs `INFO` "ResponsiveImages Feature disabled via config" and returns early — **does not even construct the generator/rewriter services**, so a disabled feature has near-zero overhead beyond the config read.
  - Else constructs `ImageVariantGenerator` and `HtmlImageRewriterService` (composition root `new`, per established precedent) and logs `INFO` "ResponsiveImages Feature registered".
- `handlePostRender(Container $container, array $parameters): array` — delegates to `HtmlImageRewriterService::handlePostRender($container, $parameters)`. Returns `$parameters` unchanged if config disabled (defensive: the early-return in `register()` already prevents the listener from being registered at all when disabled, so this path is effectively unreachable, but the service itself also no-ops safely on empty/non-HTML content as cheap insurance).

### `Services/ResponsiveImageConfig.php`
Plain value object parsed once at registration time (avoids re-parsing config on every page).

```php
final class ResponsiveImageConfig
{
    public function __construct(
        public readonly bool $enabled,
        public readonly array $widths,        // int[]
        public readonly bool $webp,
        public readonly int $quality,
        public readonly string $outputDir,    // relative to OUTPUT_DIR
        public readonly int $minSourceWidth,
    ) {}

    public static function fromSiteConfig(array $siteConfig): self
    {
        $cfg = $siteConfig['responsive_images'] ?? [];
        // validate widths: array of positive ints, sorted ascending, deduped
        // validate quality: clamp 1-100
        // ... returns self with sane fallbacks matching the documented defaults
    }
}
```

### `Services/ImageVariantGenerator.php`
Owns all Imagick interaction. Mirrors `CategoryIndex\Services\ImageService` patterns exactly but is self-contained (no shared class).

```php
final class ImageVariantGenerator
{
    public function __construct(private Log $logger, private ResponsiveImageConfig $config) {}

    /**
     * Generate (or reuse cached) variants for one source image.
     * Returns [] if the source is missing/corrupt/unsupported — caller treats
     * empty array as "leave the <img> tag alone".
     *
     * @return ImageVariant[] sorted ascending by width; includes both
     *         original-format and webp entries if webp enabled.
     */
    public function generateVariants(string $sourcePath, string $outputBaseDir, string $urlBaseDir): array
    {
        if (!class_exists('Imagick') || !is_readable($sourcePath)) {
            $this->logger->log('WARNING', "ResponsiveImages: source unreadable, skipping: {$sourcePath}");
            return [];
        }

        try {
            $probe = new Imagick($sourcePath);
            $sourceWidth = $probe->getImageWidth();
            $probe->clear();
        } catch (\Exception $e) {
            $this->logger->log('WARNING', "ResponsiveImages: failed to read image dimensions for {$sourcePath}: " . $e->getMessage());
            return [];
        }

        if ($sourceWidth < $this->config->minSourceWidth) {
            return []; // too small to bother
        }

        $variants = [];
        foreach ($this->config->widths as $width) {
            if ($width >= $sourceWidth) {
                continue; // never upscale
            }
            $variants[] = $this->renderVariant($sourcePath, $width, 'original', $outputBaseDir, $urlBaseDir);
            if ($this->config->webp) {
                $variants[] = $this->renderVariant($sourcePath, $width, 'webp', $outputBaseDir, $urlBaseDir);
            }
        }

        return array_filter($variants); // drop any that failed individually
    }

    private function renderVariant(string $sourcePath, int $width, string $mode, string $outputBaseDir, string $urlBaseDir): ?array
    {
        $hash = substr(md5($sourcePath), 0, 8); // collision-safe-enough basename disambiguator
        $basename = pathinfo($sourcePath, PATHINFO_FILENAME) . "-{$hash}-{$width}w";
        $ext = $mode === 'webp' ? 'webp' : pathinfo($sourcePath, PATHINFO_EXTENSION);
        $outPath = $outputBaseDir . '/' . $basename . '.' . $ext;
        $outUrl  = $urlBaseDir . '/' . $basename . '.' . $ext;

        if (file_exists($outPath) && filemtime($outPath) >= filemtime($sourcePath)) {
            return ['width' => $width, 'path' => $outPath, 'url' => $outUrl, 'format' => $mode];
        }

        if (!is_dir($outputBaseDir)) {
            mkdir($outputBaseDir, 0755, true);
        }

        try {
            $imagick = new Imagick($sourcePath);
            $imagick->thumbnailImage($width, 0); // 0 height = preserve aspect ratio
            if ($mode === 'webp') {
                $imagick->setImageFormat('webp');
            }
            $imagick->setImageCompressionQuality($this->config->quality);
            $imagick->writeImage($outPath);
            $imagick->clear();
            return ['width' => $width, 'path' => $outPath, 'url' => $outUrl, 'format' => $mode];
        } catch (\Exception $e) {
            $this->logger->log('WARNING', "ResponsiveImages: variant generation failed for {$sourcePath} @ {$width}w ({$mode}): " . $e->getMessage());
            return null;
        }
    }
}
```

Note vs. `CategoryIndex\ImageService::generateThumbnail()`: that code uses `thumbnailImage($w, $h, true)` (fixed box, "best fit" crop-to-fit). ResponsiveImages instead uses `thumbnailImage($width, 0)` — height `0` tells Imagick to scale proportionally to the given width only, which is the correct call for responsive `srcset` variants (we want proportional scaling, not cropping to a fixed box).

### `Services/HtmlImageRewriterService.php`
Owns DOM parsing/rewriting and source-path resolution. Mirrors `TableOfContentsService`'s DOMDocument/DOMXPath usage.

```php
final class HtmlImageRewriterService
{
    public function __construct(
        private Log $logger,
        private ImageVariantGenerator $generator,
        private ResponsiveImageConfig $config,
    ) {}

    public function handlePostRender(Container $container, array $parameters): array
    {
        $html = $parameters['rendered_content'] ?? null;
        if (!is_string($html) || stripos($html, '<img') === false) {
            return $parameters; // cheap bail-out before any DOM work
        }

        $sourceDir = $container->getVariable('SOURCE_DIR');
        $outputDir = $container->getVariable('OUTPUT_DIR');
        $templateDir = $container->getVariable('TEMPLATE_DIR');
        $templateName = $container->getVariable('TEMPLATE') ?? 'sample';

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $imgs = $xpath->query('//img[@src]');
        if ($imgs === false || $imgs->length === 0) {
            return $parameters;
        }

        $changed = false;
        foreach ($imgs as $img) {
            if (!$img instanceof \DOMElement) {
                continue;
            }
            if ($this->rewriteOneImage($dom, $img, $sourceDir, $outputDir, $templateDir, $templateName)) {
                $changed = true;
            }
        }

        if ($changed) {
            $parameters['rendered_content'] = $dom->saveHTML();
        }

        return $parameters;
    }

    private function rewriteOneImage(\DOMDocument $dom, \DOMElement $img, string $sourceDir, string $outputDir, string $templateDir, string $templateName): bool
    {
        $src = $img->getAttribute('src');

        if ($this->isExternalOrSkippable($src)) {
            return false;
        }

        $sourcePath = $this->resolveSourcePath($src, $sourceDir, $templateDir, $templateName);
        if ($sourcePath === null) {
            return false; // can't find a local file backing this src — leave untouched
        }

        $outputBaseDir = $outputDir . '/' . $this->config->outputDir;
        $urlBaseDir = '/' . $this->config->outputDir;

        $variants = $this->generator->generateVariants($sourcePath, $outputBaseDir, $urlBaseDir);
        if (empty($variants)) {
            return false; // generator already logged the reason; leave tag untouched
        }

        $this->replaceWithPicture($dom, $img, $variants, $src);
        return true;
    }

    private function isExternalOrSkippable(string $src): bool
    {
        return $src === ''
            || str_starts_with($src, 'http://')
            || str_starts_with($src, 'https://')
            || str_starts_with($src, '//')
            || str_starts_with($src, 'data:');
    }

    /**
     * Resolve a root-relative /assets/... src to a real filesystem path.
     * Mirrors TemplateAssetsService's two-tier precedence: content assets
     * override template assets, so check content first.
     */
    private function resolveSourcePath(string $src, string $sourceDir, string $templateDir, string $templateName): ?string
    {
        $relative = ltrim($src, '/');

        $contentPath = $sourceDir . '/' . $relative;
        if (is_readable($contentPath)) {
            return $contentPath;
        }

        $templatePath = $templateDir . '/' . $templateName . '/' . $relative;
        if (is_readable($templatePath)) {
            return $templatePath;
        }

        return null;
    }

    /**
     * @param ImageVariant[] $variants
     */
    private function replaceWithPicture(\DOMDocument $dom, \DOMElement $img, array $variants, string $originalSrc): void
    {
        $picture = $dom->createElement('picture');

        $webpVariants = array_values(array_filter($variants, fn ($v) => $v['format'] === 'webp'));
        if (!empty($webpVariants)) {
            $source = $dom->createElement('source');
            $source->setAttribute('type', 'image/webp');
            $source->setAttribute('srcset', $this->buildSrcset($webpVariants));
            $picture->appendChild($source);
        }

        $originalVariants = array_values(array_filter($variants, fn ($v) => $v['format'] === 'original'));
        $newImg = $img->cloneNode(false); // keep alt/class/etc., drop nothing
        if (!empty($originalVariants)) {
            $newImg->setAttribute('srcset', $this->buildSrcset($originalVariants));
            $newImg->setAttribute('sizes', '100vw');
            // largest original-format variant becomes the fallback src for
            // browsers without srcset support
            $largest = end($originalVariants);
            $newImg->setAttribute('src', $largest['url']);
        }
        $picture->appendChild($newImg);

        $img->parentNode->replaceChild($picture, $img);
    }

    /**
     * @param ImageVariant[] $variants
     */
    private function buildSrcset(array $variants): string
    {
        return implode(', ', array_map(
            fn ($v) => "{$v['url']} {$v['width']}w",
            $variants
        ));
    }
}
```

## 5. Event Pipeline Hooks

| Event | Priority | Handler | Purpose |
|---|---|---|---|
| `POST_RENDER` | 150 | `Feature::handlePostRender` | Parse final per-page HTML, generate variants, rewrite `<img>` → `<picture>` before Core writes the file to `OUTPUT_DIR`. |

No `PRE_GLOB`/`POST_GLOB`/`PRE_LOOP`/`POST_LOOP` hooks are needed: this feature is purely per-file and has no cross-file aggregation step (unlike `Tags`/`CategoryIndex`, which build archive pages from all files). It deliberately does **not** hook `POST_LOOP`, because by the time `POST_LOOP` fires, every page's HTML has already been written to disk — too late to rewrite tags in the output files without re-reading/re-writing every HTML file from `OUTPUT_DIR` (messy, redundant I/O, and out of step with how every other content-mutating feature in this codebase operates within the per-file loop).

This also confirms the source-of-truth decision: source images are read from `SOURCE_DIR`/`TEMPLATE_DIR` (pre-copy locations), **never** from `OUTPUT_DIR/assets`, because `OUTPUT_DIR/assets` is not populated until `TemplateAssets`'s `POST_LOOP` listener runs, which is strictly after every page's `POST_RENDER` has already completed.

No new custom events are introduced — this is a leaf consumer of the existing per-file pipeline, not something other features need to hook into.

## 6. Security Implications

- **Path traversal**: `src` attributes come from rendered HTML (ultimately authored in `content/`, a trusted source within this project's threat model — same trust level as existing Markdown/HTML content). However, `resolveSourcePath()` must still defend against `../` traversal in case of accidental or malicious frontmatter/HTML: normalize with `realpath()` after concatenation and verify the resolved real path still starts with `realpath($sourceDir)` (or `realpath($templateDir/$templateName)`) before treating it as valid. Reject (`return null`) if `realpath()` fails or escapes the expected root.
- **Resource exhaustion**: Imagick operations on attacker-controlled or massive images can be slow/memory-heavy. Mitigated by: (a) feature is opt-in/disabled by default, (b) `min_source_width` and `widths` config bound how much work happens per image, (c) all Imagick calls are wrapped in try/catch so a single pathological image cannot abort the build, only that one image's enhancement. No explicit memory/time limit is added beyond PHP's own `memory_limit`/`max_execution_time` — consistent with how `CategoryIndex\ImageService` handles the same risk today (no additional limits there either).
- **Output path collisions**: variant filenames are derived from `pathinfo($sourcePath, PATHINFO_FILENAME)` plus an 8-char md5 hash of the full source path plus the width — this avoids two different source images with the same basename (e.g. `content/assets/hero.jpg` vs `content/blog/post1/hero.jpg`) overwriting each other's generated variants in the flat `output_dir`.
- **No new external network calls, no new Composer dependencies, no credentials involved.** Pure local filesystem + Imagick.
- **DOM parsing of own rendered output**: `libxml_use_internal_errors(true)` prevents malformed HTML (e.g., from a markdown edge case) from emitting warnings/errors that could leak into logs or break the build; consistent with `TableOfContentsService` precedent.

## 7. Enabled-by-Default Decision

**Recommendation: disabled by default (`responsive_images.enabled: false`).**

Rationale:
1. **Build time cost is multiplicative and not always wanted.** Every configured width × format (× WebP) is a separate Imagick `thumbnailImage`+`writeImage` call per image, per build. For sites with many images this could meaningfully slow `site:render`, and that cost should be an explicit opt-in, not a silent regression for every existing StaticForge site that upgrades.
2. **Output footprint changes.** It adds a new `assets/images/responsive/` directory tree to every build's `public/` output. Existing sites/deployments (e.g. the `Deployment`/`SiteUploader` feature, which has its own `EVENT_UPLOAD_CHECK_FILE` hook) would suddenly have new files to sync/upload on every deploy unless the site owner opted in deliberately.
3. **Imagick availability isn't guaranteed everywhere.** Although confirmed enabled in this project's Lando environment, StaticForge is a general-purpose generator; other deployments of it may not have `ext-imagick`. Defaulting to off avoids any surprise log noise (`WARNING: source unreadable...`) on environments without the extension — the feature simply never registers its listener when disabled.
4. **Precedent**: other recently-added opt-in/heavier features in this codebase (`answer_engine_optimization`) also default `enabled: true` only because they're low-cost flag injections; by contrast, `s3_offload` (a similarly build-output-altering feature) has no `enabled` flag shown but is clearly intended as deliberately-configured-when-used. Treating image regeneration as belonging to that same "opt-in, configure deliberately" category is the more conservative and consistent choice given its build-time/output-footprint impact.

Site owners who want this turned on simply set `responsive_images.enabled: true` plus any width/quality overrides in their own `siteconfig.yaml`.

## 8. Testing Strategy

Per `CLAUDE.md` Section 5: `tests/Unit/Features/ResponsiveImages/...` and `tests/Integration/...` as appropriate. No network calls, no DB — all tests run against fixture images and fixture HTML strings, using `vfsstream` (already a dev dependency) for filesystem isolation where convenient, or real temp directories under the OS temp dir for Imagick (Imagick generally needs real file paths, not stream wrappers, for `writeImage`/constructor — confirm during implementation whether vfsstream interop works for Imagick specifically; if not, use `sys_get_temp_dir()` fixture dirs cleaned up in `tearDown()`).

### `ResponsiveImageConfig` (Unit)
- Defaults applied when `responsive_images` key absent entirely.
- `enabled: true` honored when explicitly set.
- Invalid `widths` (non-array, negative numbers, non-numeric) fall back to default `[400, 800, 1200]`.
- `quality` clamped to `[1, 100]`.

### `ImageVariantGenerator` (Unit)
- Given a real fixture JPEG/PNG wider than all configured widths: produces one variant per width (× 2 if webp enabled), each with correct `width`/`format`/existing file on disk.
- Source narrower than `min_source_width`: returns `[]`.
- Configured width ≥ source width: that width is skipped (no upscaling), confirmed via output variant count.
- Corrupt/non-image file at `sourcePath`: returns `[]`, logs a `WARNING`, no exception propagates.
- Missing/unreadable `sourcePath`: returns `[]`, logs `WARNING`.
- Re-running `generateVariants()` with an unchanged source and pre-existing up-to-date output file: confirms via a spy/mock that `Imagick`'s constructor is *not* invoked a second time for that variant (mtime-cache short-circuit) — or, more simply at the integration level, confirm the output file's mtime is unchanged across two runs.
- Touching the source file (newer mtime) before a second run: confirms the variant *is* regenerated (mtime is updated).

### `HtmlImageRewriterService` (Unit)
- HTML with no `<img>` tags: `rendered_content` unchanged, no DOM work attempted (cheap bail-out verified via a spy generator that asserts zero calls).
- HTML with an external `<img src="https://...">`: tag left completely untouched.
- HTML with `data:` URI `<img>`: left untouched.
- HTML with a valid local `/assets/images/x.jpg` matching a content-asset fixture: tag replaced with `<picture>` containing expected `<source type="image/webp" srcset="...">` and fallback `<img srcset="..." sizes="100vw" src="...">`, preserving original `alt`/`class` attributes from the source tag.
- HTML with a local image whose backing file does not exist on disk: tag left untouched, `WARNING` logged, build does not throw.
- Path-traversal attempt (`src="/assets/../../../../etc/passwd"`): `resolveSourcePath()` rejects it (returns `null`), tag left untouched.
- Multiple `<img>` tags on one page, one valid + one invalid: only the valid one is rewritten; the invalid one is untouched; `rendered_content` reflects exactly that mixed state.

### Integration (`tests/Integration/Features/ResponsiveImages/...`)
- Full `Application::renderSingleFile()` (or equivalent CLI invocation against a fixture content/template tree) with `responsive_images.enabled: true` in a fixture `siteconfig.yaml`: confirms end-to-end that a fixture markdown file with an `<img>` referencing a fixture image produces (a) a rewritten `<picture>` tag in the final `OUTPUT_DIR` HTML file, and (b) the expected variant files physically present under `OUTPUT_DIR/assets/images/responsive/`.
- Confirms feature is a no-op (no `<picture>` rewriting, no variant files generated) when `responsive_images.enabled` is omitted/false in `siteconfig.yaml` — verifying the default-off behavior end-to-end.
- `lando phpunit` run as final QA step per Section 4 of `CLAUDE.md` Agent Workflow B.
