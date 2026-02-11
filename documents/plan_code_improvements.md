# Plan: Src Code Improvements

Date: 2026-02-11

## Scope
This plan addresses the improvement ideas identified in src, with a focus on security correctness, URL consistency, scaffolding standards, performance, and reliability. The changes include tests and documentation updates where applicable.

## Goals
- Make audit tools trustworthy by default (TLS verification on), while preserving a safe escape hatch.
- Make category URLs consistent across generation and navigation.
- Ensure feature scaffolding matches project standards (strict types, docblocks, DI guidance).
- Reduce template rendering overhead and align with container configuration.
- Make Weather shortcode network and cache handling safer and more predictable.

## Non-Goals
- Large refactors of the event pipeline or feature architecture.
- Changes to public APIs beyond configuration flags and consistent URL behavior.
- Frontend design changes beyond what is required for correctness or documentation.

## Work Plan

### 1) Make TLS verification default for audits

#### Rationale
The live and link audit commands currently disable TLS verification, which can hide real certificate issues and allow MITM to skew results.

#### Code changes
- Add a CLI flag `--insecure` (or similar) to explicitly disable verification.
- Default to verification enabled for both cURL and HttpClient.
- Update error messages to clarify verification failures and how to bypass in dev.

#### Files
- src/Commands/Audit/LiveCommand.php
- src/Commands/Audit/LinksCommand.php

#### Tests
- Add unit tests for option parsing and default config values in audit commands.
- Add integration tests (if existing harness supports) verifying that `--insecure` flips verification flags.

#### Documentation
- Update docs describing audit commands and the new flag.
- Note that default behavior validates certificates.

---

### 2) Unify category URL conventions

#### Rationale
Category menu items currently point to `/slug.html` while category pages are rendered to `/slug/index.html`. This mismatch can cause broken navigation depending on server configuration.

#### Code changes
- Choose a canonical category URL format (recommend `/slug/`).
- Update menu link generation to match the output path used by category pages.
- Ensure any other category URL sources are aligned (RSS, sitemap, internal links if relevant).

#### Files
- src/Features/CategoryIndex/Services/MenuService.php
- src/Features/CategoryIndex/Services/CategoryPageService.php
- (Optional) verify other features that assume `.html` for categories

#### Tests
- Unit test for menu URL generation for category items.
- Integration test (if available) verifying category pages are discoverable and linked correctly.

#### Documentation
- Update user docs describing category pages and menu link formats.

---

### 3) Bring feature scaffolding in line with standards

#### Rationale
Feature scaffolding omits `declare(strict_types=1)` and docblocks, and directly instantiates services. This conflicts with project standards.

#### Code changes
- Update scaffold templates to include `declare(strict_types=1);`.
- Add PHPDoc for public classes and methods, including `@param`, `@return`, `@throws` as needed.
- Provide DI-friendly constructor patterns (e.g., accept dependencies in constructor and document container use).

#### Files
- src/Features/FeatureTools/Commands/FeatureCreateCommand.php

#### Tests
- Unit test for generated template contents to ensure strict types and docblocks exist.

#### Documentation
- Update developer guide or feature creation docs to reflect updated scaffolding and DI expectations.

---

### 4) Reuse Twig environment / reduce per-render overhead

#### Rationale
TemplateRenderer creates a new Twig environment for each render, which is extra overhead and can diverge from container configuration.

#### Code changes
- Reuse `twig` from the container when available.
- Optionally cache a local Twig instance in TemplateRenderer keyed by active template.
- Ensure behavior stays consistent for `renderTemplate()` and primary render path.

#### Files
- src/Services/TemplateRenderer.php

#### Tests
- Unit test verifying the renderer uses the container Twig instance when provided.
- Regression test to ensure template resolution and includes still work.

#### Documentation
- Add a short note in developer docs about Twig instance usage and cache behavior.

---

### 5) Harden Weather shortcode networking and cache

#### Rationale
Weather shortcode uses `file_get_contents` with no timeout, uses HTTP for ZIP lookup, and uses `unserialize` on cache files. This can be slow and unsafe.

#### Code changes
- Use a simple HTTP client with timeouts (cURL or Symfony HttpClient if already present in deps).
- Switch ZIP lookup to HTTPS endpoint if available.
- Replace `serialize`/`unserialize` with JSON and strict decoding.
- Validate and sanitize API response fields before use.

#### Files
- src/Shortcodes/WeatherShortcode.php

#### Tests
- Unit tests with mocked HTTP responses for both ZIP and weather endpoints.
- Unit tests for cache read/write behavior and fallback handling.

#### Documentation
- Document weather shortcode configuration and note external API usage and caching behavior.

---

## Test Strategy Summary
- Add/extend unit tests for audit commands, scaffolding templates, category URL generation, TemplateRenderer behavior, and Weather shortcode.
- Add/extend integration tests where practical (site render and audit behavior, category navigation).
- Ensure tests can be run with `lando phpunit`.

## Documentation Updates Summary
- Audit command docs: TLS verification defaults and `--insecure` flag.
- Category feature docs: canonical URL format.
- Feature tools docs: updated scaffolding standards.
- Template rendering docs: Twig reuse and configuration.
- Weather shortcode docs: networking, caching, and endpoints.

## Rollout Notes
- These changes are backward compatible except category URL format, which may impact existing links. Consider a short migration note or optional compatibility mode if needed.

## Open Questions
- Which canonical category URL format should be enforced (`/slug/` vs `/slug.html`)? Recommendation: `/slug/` to align with index.html output.
- Is `--insecure` naming preferred, or should it be `--skip-tls-verify` for explicitness?
- Should Twig caching be enabled in non-dev mode, and if so, what toggle should control it?
