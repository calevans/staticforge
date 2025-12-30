# Burndown: Social Metadata Feature

## Overview
Implement a `SocialMetadata` feature that automatically generates Open Graph (OG) and Twitter Card metadata for pages based on their frontmatter. This feature will eventually be extracted into a standalone package `calevans/staticforge-social-metadata`.

## Goals
- Automatically inject `<meta>` tags for Open Graph and Twitter into the `<head>` of generated pages.
- Support standard frontmatter keys (`title`, `description`, `image`, `url`).
- Allow configuration via `siteconfig.yaml` for site-wide defaults (e.g., default image, twitter handle).
- Follow strict SOLID principles to facilitate future extraction.

## Prerequisites
- [ ] Read `documents/developer_guide.md` to ensure compliance with project standards.
- [ ] Review `src/Features/RssFeed` as the "Gold Standard" for implementation patterns.

## Implementation Plan

### 1. Feature Structure Setup
- [x] Create directory `src/Features/SocialMetadata`.
- [x] Create `src/Features/SocialMetadata/README.md` (Feature documentation).
- [x] Create `src/Features/SocialMetadata/Feature.php` implementing `FeatureInterface` and `ConfigurableFeatureInterface`.
- [x] Create `src/Features/SocialMetadata/Services/MetadataGenerator.php` (The logic class).

### 2. Core Logic Implementation
- [x] Implement `MetadataGenerator::generate(array $frontmatter, array $siteConfig): string`.
    - [x] Logic to map frontmatter keys to OG/Twitter tags.
    - [x] Logic to fall back to `siteconfig.yaml` defaults.
    - [x] Logic to construct the HTML `<meta>` tags.
- [x] Implement `Feature::handleRender` (or `POST_RENDER` hook).
    - [x] Extract frontmatter from the file.
    - [x] Call `MetadataGenerator`.
    - [x] Inject the generated HTML into the `<head>` of the content.

### 3. Configuration & Validation
- [x] Implement `getRequiredConfig()` in `Feature.php`.
    - [x] Define optional/required keys (e.g., `social.twitter_handle`, `social.default_image`).
- [x] Update `siteconfig.yaml.example` with sample configuration.

### 4. Testing
- [x] Create unit tests for `MetadataGenerator` in `tests/Unit/Features/SocialMetadata/MetadataGeneratorTest.php`.
    - [x] Test with full frontmatter.
    - [x] Test with missing frontmatter (fallbacks).
    - [x] Test with no frontmatter and no defaults.

### 5. Integration
- [x] Verify feature auto-discovery.
    - [x] Ensure `FeatureManager` loads the new feature from `src/Features/SocialMetadata`.
    - [x] Verify `site:render` output contains the tags.

## Future Extraction Steps (For Reference)
- Move `src/Features/SocialMetadata` to a new repo.
- Create `composer.json` for the package.
- Register via `extra.staticforge` in `composer.json` (if using external package discovery).

## Questions/Notes
- **Event Hook**: `POST_RENDER` seems appropriate as we are modifying the HTML output. We need to find `<head>` and append to it.
- **Frontmatter Mapping**:
    - `title` -> `og:title`, `twitter:title`
    - `description` -> `og:description`, `twitter:description`
    - `image` -> `og:image`, `twitter:image`
    - `url` (computed) -> `og:url`
    - `type` -> `og:type` (default to `website` or `article`)
