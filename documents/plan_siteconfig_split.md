# Plan: Split siteconfig.yaml into siteconfig.d/ per-feature files

Status: EXPLORATORY — no implementation. Written to evaluate feasibility only.

## 1. Overview

### Current state (verified by reading code)

`siteconfig.yaml` is loaded synchronously in `src/bootstrap.php` (lines ~147-175), long before any
`EventManager`, `FeatureManager`, or feature is instantiated:

```
$eventManager = new EventManager($container);       // line 250
$featureManager = new FeatureManager($container, $eventManager); // line 256
```

Sequence in `bootstrap.php`:
1. Load `.env` (Dotenv)
2. Compute `app_root`
3. Copy `$_ENV` into `Container`
4. **Load `siteconfig.yaml`** (first hit of `getcwd()/siteconfig.yaml` or `$appRoot/siteconfig.yaml`, parsed with `Symfony\Component\Yaml\Yaml::parseFile()`, stored as `Container::setVariable('site_config', $siteConfig)`)
5. Resolve `TEMPLATE` from `site_config['site']['template']` → `$_ENV['TEMPLATE']` → default
6. Register `logger`, `twig` as lazy services (`Container::stuff`)
7. Instantiate `EventManager`, `AssetManager`, `FeatureManager`, `ExtensionRegistry`, `FileDiscovery`, `ErrorHandler`, `FileProcessor` and add them to the container
8. Register a handful of shared services (`MarkdownProcessor`, `ContentExtractor`, `TemplateVariableBuilder`, `TemplateRenderer`)

`bootstrap.php` returns the fully wired `Container`. `FeatureManager::loadFeatures()` (called later, outside bootstrap.php, presumably by the command/Application layer before `PRE_GLOB`) is what actually walks `src/Features`, `Features/`, and Composer-installed feature packages to discover `Feature.php` classes. **Feature discovery happens strictly after `site_config` already exists.**

`ConfigurableFeatureInterface::getRequiredConfig()` lets a feature declare dot-notation keys it expects to find in `site_config` (e.g. `forms.contact.provider_url`), validated later by `site:check`/`ConfigCommand`. This is a *read* contract, not a loading mechanism — features never write to `siteconfig.yaml` today.

`siteconfig.yaml` currently mixes core site settings (`site:`, `social:`, `menu:`) with feature-specific blocks (`chapter_nav:`, `forms:`) in one flat file, keyed by convention (each feature "owns" a top-level key).

### Problem statement

As more features accumulate feature-specific config blocks, a single hand-maintained `siteconfig.yaml`
becomes a merge-conflict-prone, hard-to-scan file, and it works against the project's stated goal
of extracting features into standalone Composer packages (Section 6, CLAUDE.md) — a fully self-contained
feature currently cannot ship its own default/example config without a human hand-editing the central
`siteconfig.yaml`.

### Proposed idea (as given)

Add a `siteconfig.d/` directory. At bootstrap: load `siteconfig.yaml` first (base), then deep-merge
every `siteconfig.d/*.yaml` file on top, feature files winning on key collision. Result is still one
merged array in `Container::getVariable('site_config')`, so nothing downstream of bootstrap changes.

## 2. Directory Convention: siteconfig.d/ vs. co-located feature config

Two candidate approaches, evaluated:

### Option A — Central `siteconfig.d/{FEATURENAME}.yaml`
- Pros: single, discoverable location for site operators to find/edit all feature overrides; mirrors
  common Unix `*.d/` convention (`sudo.d`, `apache2/conf.d`) that ops-minded users already understand;
  trivially `glob()`-able at bootstrap without any knowledge of feature classes; keeps `src/Features/*`
  free of *site-specific* config, which matters because those directories get wiped/replaced on
  Composer package extraction/update.
- Cons: a site author who wants to override the `Forms` feature must know the file is named
  `siteconfig.d/Forms.yaml` (naming convention must be documented/enforced); nothing stops a typo'd
  filename from being silently ignored (no validation that a `siteconfig.d/*.yaml` file corresponds to
  an actually-loaded feature, since features aren't loaded yet — see Section 4).

### Option B — Config lives inside the feature's own directory, e.g. `src/Features/{Name}/config.yaml`
- Pros: keeps a feature 100% self-contained (aligns with Section 6 "Future Extraction Strategy" —
  the file moves with the feature automatically when extracted to a Composer package); no separate
  naming convention to remember.
- Cons: **breaks the site/vendor separation** — `src/Features/{Name}/config.yaml` would need to hold
  the *default* feature config (fine, ships with the feature) but a *site's* local overrides
  (API keys, per-site copy) cannot live inside a vendor-controlled/`composer update`-managed directory
  without being clobbered on update. This option only works as a place for feature *defaults*, not
  site-specific overrides, which is a different problem than the one posed by the user. It would also
  require distinguishing `src/Features` (bundled) from `Features/` (external) from Composer-installed
  feature packages under `vendor/`, three different base paths, all of which must be walkable at
  bootstrap time before `FeatureManager` exists — effectively duplicating `FeatureManager`'s directory
  discovery logic inside `bootstrap.php`.

### Recommendation
Use **Option A** (`siteconfig.d/*.yaml` at the project root, sibling to `siteconfig.yaml`), scoped to
*site-level overrides only*. If feature-shipped defaults are wanted later, that is a **separate**
concern (a feature could ship `src/Features/{Name}/config.default.yaml` and have `FeatureManager`
merge it in during `loadFeatures()`, which runs after bootstrap and does have feature identity) —
out of scope for this plan, noted only so the two concerns aren't conflated.

Filenames in `siteconfig.d/` should NOT be required to match feature names 1:1 (the loader has no way
to validate that at bootstrap — see Section 4), but the convention (`{FeatureName}.yaml`, PascalCase,
matching `src/Features/{FeatureName}`) should be documented so `site:check`/tooling can later flag
orphaned files (a file with no matching loaded feature) as a warning, not an error.

## 3. Merge Strategy

### Order
1. `siteconfig.yaml` (base) loaded first, exactly as today.
2. `siteconfig.d/*.yaml` files loaded in **alphabetical filename order** (`glob()` + `sort()`,
   `SCANDIR_SORT_ASCENDING` — deterministic, OS-independent, and does not depend on feature
   registration order, which does not exist yet at this point in the lifecycle — see Section 4).
3. Later files in the sort order win over earlier ones on scalar key collision, and both `siteconfig.yaml`
   and every `siteconfig.d/*.yaml` file are subject to the same deep-merge rule (base is just "file
   number zero" in the merge sequence).

### Deep merge semantics
- **Associative arrays (maps)**: merge recursively, key by key. A key present in a later file
  overrides/extends the key from an earlier file at that same nesting level.
- **Sequential arrays (lists)**: **replace wholesale**, not concatenate/append. This matches the
  principle of least surprise (`Yaml::parseFile` doesn't distinguish list vs map at merge time,
  so the merge helper must use `array_is_list()` (PHP 8.1+) to detect and short-circuit sequential
  arrays instead of recursing key-by-key, which would produce confusing partial-index merges).
  Example: `forms.contact.fields` (a list) in a `siteconfig.d/Forms.yaml` completely replaces the
  base file's `fields` list rather than merging by numeric index.
- **Scalars**: last-writer-wins.
- **Type mismatch** (e.g. base has `chapter_nav: "off"` (scalar) and an override file has
  `chapter_nav: {menus: "2,3"}` (map)): last-writer-wins, override replaces entirely, no attempt to
  coerce/merge across types. This should be logged (see below) since it usually indicates operator
  error.

### Non-array top-level YAML (coercion to `[]`)
Today's inline code coerces a non-array parse result of `siteconfig.yaml` to `[]`
(`if (!is_array($siteConfig)) { $siteConfig = []; }`, guarding against a `siteconfig.yaml` that is a
bare scalar, a bare string, or empty/`null`). **The same coercion applies to every individual
`siteconfig.d/*.yaml` file.** If a given `siteconfig.d/Foo.yaml` parses to a non-array (e.g. someone
accidentally writes a bare string or the file is empty), that single file is treated as `[]` (a no-op
merge — contributes nothing) rather than raising a type error, consistent with how the base file is
already handled today. This is a distinct case from "malformed/unparseable YAML" (a hard syntax error),
which is covered in Section 7.

### On key collisions
- **Silent override for scalar/list replacement** is the normal, expected case (that's the entire
  point of the feature) — no log noise for ordinary overrides.
- **Logging is NOT available at merge time.** Correcting an earlier draft of this plan: the `logger`
  container service is registered via `Container::stuff('logger', ...)` at `bootstrap.php` line 195,
  which is *after* `site_config` is currently built (lines 147-175). `SiteConfigLoader` runs even
  earlier than that in the proposed design, so it cannot call the `logger` service directly (see
  Section 5, which already accounts for this constraint). Practically: `SiteConfigLoader::load()`
  optionally accepts a nullable `?\EICC\Utils\Log $logger` (or a simple no-op default) and skips
  logging entirely when null is passed, which is what `bootstrap.php` will pass on the initial call
  (YAGNI — do not build a log-buffering/deferred-flush mechanism just to log merge activity earlier
  than the logger exists). If desired later, `bootstrap.php` could re-log a summary of merged keys
  once the `logger` service becomes available a few lines later, but that is an optional enhancement,
  not part of this plan's core scope.
- **Type-mismatch collisions** (scalar overriding a map or vice versa) are still detected and would be
  logged as a WARNING *if* a logger is available, but must not throw/abort the build regardless
  (YAGNI — don't build a config-schema-validation system as part of this). The override still wins;
  only the logging is best-effort/conditional on logger availability.

## 4. Event Lifecycle Fit / The Chicken-and-Egg Problem

Per CLAUDE.md's own lifecycle description and the code read in Section 1: Bootstrap happens **before**
`PRE_GLOB`, and `PRE_GLOB` happens before feature registration matters for the event pipeline; but more
importantly, `FeatureManager::loadFeatures()` — which is what actually instantiates `Feature.php`
classes and lets them call `EventManager::register()` — is invoked from outside `bootstrap.php`,
strictly after `bootstrap.php` returns the `Container` with `site_config` already populated.

**Consequence**: `siteconfig.d/` cannot be "populated" or validated by features at load time, because
features do not exist yet when `site_config` is built. This must be a **pure directory-glob mechanism**,
not a feature-registration-based one. Concretely:

- The `siteconfig.d/` merge happens entirely inside `bootstrap.php`, in the same place `siteconfig.yaml`
  is currently loaded (between the existing lines 147-175), with **no dependency on `EventManager` or
  `FeatureManager`**.
- No new event is dispatched for this (there is no `EventManager` instance yet at this point — it's
  literally created a few lines later at line 250). This directly contradicts a naive "let features
  hook a `SITECONFIG_LOAD` event" design; that event cannot exist yet. Do not attempt it.
- This means `siteconfig.d/*.yaml` is inherently **decoupled** from which features are actually enabled/
  disabled (`disabled_features` in `site_config`, read later inside `FeatureManager::loadFeatures()`).
  A `siteconfig.d/Forms.yaml` file will be merged in and available in `site_config['forms']` even if
  the `Forms` feature itself ends up disabled. This is consistent with how `siteconfig.yaml` already
  behaves today (disabled features' config blocks aren't purged from `site_config` either), so it is
  not a regression, just worth documenting explicitly.

## 5. Class/Service Structure

Following the Container-based DI convention and KISS/YAGNI, this does not need a "Feature" (it runs
before `FeatureManager` exists) — it needs a small, single-purpose, stateless helper class invoked
directly from `bootstrap.php`, mirroring how `bootstrap.php` already inlines the `Yaml::parseFile`
call today rather than routing through a service.

Proposed location: `src/Core/Config/SiteConfigLoader.php` (new `src/Core/Config` namespace directory;
this is core bootstrap machinery, not a Feature, so it does not belong under `src/Features`).

```php
namespace EICC\StaticForge\Core\Config;

final class SiteConfigLoader
{
    /**
     * Loads siteconfig.yaml (if present) then deep-merges every
     * siteconfig.d/*.yaml file (if the directory exists) on top, alphabetically.
     *
     * @return array<string, mixed>
     */
    public function load(string $appRoot, string $cwd, ?\EICC\Utils\Log $logger = null): array;

    /** @param array<string,mixed> $base @param array<string,mixed> $override */
    private function deepMerge(array $base, array $override, ?\EICC\Utils\Log $logger, array $path = []): array;
}
```

- `bootstrap.php` replaces its current inline `foreach ($siteConfigPaths ...)` block (lines 150-172)
  with a single call: `$siteConfig = (new SiteConfigLoader())->load($appRoot, getcwd(), null);`
  (logger isn't registered yet at that point in bootstrap.php either — same ordering constraint
  applies to `SiteConfigLoader` as applied to the current inline code; logger param stays nullable
  and defaults to no-op logging, OR the merge log lines are deferred/buffered and flushed once the
  `logger` service is registered a few lines later — simplest is nullable + skip logging, YAGNI).
- No `Container` dependency inside `SiteConfigLoader` itself — keep it a plain, easily-unit-testable
  class that takes primitives in and returns an array out; `bootstrap.php` remains the only place that
  touches `Container::setVariable('site_config', ...)`, unchanged from today.
- Existing consumers (`TemplateVariableBuilder`, `MenuBuilderService`, `StaticMenuProcessor`,
  `FormsService`, `ConfigCommand`, `ContentCommand`, `InitCommand`, `FeatureSetupCommand`) all read
  `site_config` via `Container::getVariable('site_config')` and require **zero changes** — the merged
  array has the identical shape/contract as today's `site_config`.

## 6. Backward Compatibility

- If `siteconfig.d/` does not exist (or exists but is empty), `SiteConfigLoader::load()` must produce
  byte-for-byte the same result as today's inline code when only `siteconfig.yaml` is present. This is
  the primary regression test.
- If neither `siteconfig.yaml` nor `siteconfig.d/` exist, behavior is unchanged: `site_config = []`.
- **`siteconfig.yaml` absent but `siteconfig.d/` present**: explicitly supported. Base starts as `[]`
  (matching today's "no siteconfig.yaml" behavior), then every `siteconfig.d/*.yaml` file is merged on
  top of that empty base in the usual alphabetical order. A site can therefore be configured entirely
  via `siteconfig.d/` with no top-level `siteconfig.yaml` at all — useful for a site that wants every
  config block cleanly separated per feature from day one.
- Path lookup order preserved: `getcwd()/siteconfig.yaml` takes precedence over `$appRoot/siteconfig.yaml`
  exactly as today (first match wins, `break` after first hit) — `siteconfig.d/` should follow the
  same precedence rule: check `getcwd()/siteconfig.d/` first, fall back to `$appRoot/siteconfig.d/`,
  do not merge both.

## 7. Security Implications

- **YAML parsing safety**: Already using `Symfony\Component\Yaml\Yaml::parseFile()`
  (`composer.json`: `symfony/yaml: ^6.0`), which by default does **not** deserialize arbitrary PHP
  objects (no `!php/object` tag support unless `Yaml::PARSE_OBJECT` flag is explicitly passed, which
  the current code does not pass). `SiteConfigLoader` must call `Yaml::parseFile()` with the same
  default (no `PARSE_OBJECT`/`PARSE_OBJECT_FOR_MAP`/`PARSE_CUSTOM_TAGS` flags) for every file in
  `siteconfig.d/`, matching current behavior — no new risk introduced here as long as flags aren't added.
- **Malformed YAML in a `siteconfig.d/*.yaml` file: THROW AND ABORT, same as the base file (medium
  severity if not followed).** Today, a syntax error in `siteconfig.yaml` throws a `RuntimeException`
  and aborts the build (`bootstrap.php` lines 158-169, catching `Yaml::parseFile()`'s
  `\Symfony\Component\Yaml\Exception\ParseException` and rethrowing with context). `SiteConfigLoader`
  MUST apply the identical fail-loud behavior to *every* file it parses, including each individual
  `siteconfig.d/*.yaml` file — do not silently skip or warn-and-continue past a broken override file.
  Silently skipping a malformed file would be a **regression** from today's model and could mask a
  corrupted or maliciously-truncated config file (e.g. an override file that fails to parse and is
  silently dropped, leaving stale/default values in effect without the operator's knowledge). The
  exception message should include the specific failing file path (mirroring the existing
  `"Failed to parse siteconfig.yaml at {$configPath}"` pattern) so the operator can immediately locate
  which `siteconfig.d/*.yaml` file is broken.
- **Path traversal**: `siteconfig.d/` is discovered via a fixed, hardcoded directory name relative to
  `$appRoot`/`getcwd()` (no user input, no dynamic path construction from request data or CLI args) —
  same trust model as the existing `siteconfig.yaml` lookup. `glob($dir . '/*.yaml')` should be used
  (not user-suppliable patterns) and results should be filtered to plain files only
  (`is_file()`) to avoid following unexpected symlinks/directories named `*.yaml`.
- **File count/DoS**: Not a meaningful concern — this is a build-time CLI tool reading local
  filesystem config, not a network-facing service; no rate limiting or size caps needed (YAGNI).
- **Trust boundary unchanged**: `siteconfig.d/*.yaml` files are committed to the same repo as
  `siteconfig.yaml` (per existing docs: "Unlike .env, this file can be committed to version control").
  Anyone who can write to `siteconfig.d/` already has write access to `siteconfig.yaml` and thus to
  the whole build — no new privilege boundary is crossed by adding more mergeable files in the same
  trust zone.
- **Forward-looking note (informational only, no action needed now)**: The current trust model assumes
  every `siteconfig.d/*.yaml` file is VCS-committed and reviewed alongside the rest of the codebase,
  same as `siteconfig.yaml` today. If `siteconfig.d/` is ever repurposed as a drop target for
  feature-installer-generated files or remote-fetched config (neither of which is proposed here), that
  would introduce a new trust boundary, and a denylist/allowlist for security-relevant keys (e.g.
  `debug` flags, CORS/allowed-origins settings, deployment paths, credentials) should be reconsidered
  at that time. Not needed for the VCS-committed trust model this plan targets.

## 8. Effort Estimate

Small. Rough breakdown:

| Task | Estimate |
|---|---|
| `SiteConfigLoader` class + deep-merge logic (`array_is_list()` handling, path-based logging) | 1.5-2 hrs |
| `bootstrap.php` wiring change (replace lines 147-175) | 0.5 hr |
| Unit tests (`tests/Unit/Core/Config/SiteConfigLoaderTest.php`): no-dir, empty-dir, scalar override, list-replace, map-deep-merge, type-mismatch warning, path precedence (cwd vs appRoot) | 2-3 hrs |
| Integration test: full bootstrap with a fixture `siteconfig.d/` | 1 hr |
| Documentation update (README/CLAUDE.md mention of `siteconfig.d/` convention, PascalCase naming) | 0.5 hr |
| **Total** | **~1 focused day (5.5-7 hrs)** |

Not included / explicitly out of scope for this estimate (flag to user if desired later):
- Feature-shipped default config files (Option B follow-up, Section 2) — separate, larger effort.
- `site:check`/`ConfigCommand` warnings for orphaned `siteconfig.d/*.yaml` files with no matching
  feature — small follow-up, not needed for the core mechanism to work.
