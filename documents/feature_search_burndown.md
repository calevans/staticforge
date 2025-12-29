# Feature: Native Search (MiniSearch) Burndown List

## Overview
Implement a native, client-side search feature for StaticForge using [MiniSearch](https://github.com/lucaong/minisearch). This feature will generate a `search.json` index at build time and provide a lightweight JavaScript helper for the frontend.

## Principles
- **KISS**: Uses a simple JSON index and a small JS library (MiniSearch). No external services required.
- **Zero Config**: Works out of the box. Indexes all pages by default.
- **Centralized Config**: Configuration (exclusions) lives in `siteconfig.yaml`.
- **Safety**: Fails gracefully if the index cannot be built. Does not modify user templates automatically.

## Architecture
- **Directory**: `src/Features/Search/`
- **Events**:
    - `POST_RENDER`: Collect page data (title, content, URL, tags) for the index.
    - `POST_BUILD`: Write `search.json` to the output directory and copy `search.js` asset.
- **Exclusion Logic**:
    - **Drafts**: Automatically excluded (handled by Core `FileDiscovery`).
    - **Frontmatter**: `search_index: false` excludes specific pages.
    - **Config**: `exclude_paths` (e.g., `/tags/`) and `exclude_content_in` (e.g., `/private/`) in `siteconfig.yaml`.

## Tasks

### 1. Core Implementation (`src/Features/Search/`)
- [ ] **Scaffold Directory**: Create `src/Features/Search/` structure (Services, assets, etc.).
- [ ] **Create `Services/SearchIndexService.php`**:
    - Implement `collectPage(Page $page)`: Extracts title, excerpt, tags, and URL.
    - Implement `shouldIndex(Page $page)`: Checks frontmatter (`search_index: false`) and config exclusions.
    - Implement `buildIndex()`: Returns the JSON structure for MiniSearch.
- [ ] **Create `Feature.php`**:
    - Register `POST_RENDER` listener to call `SearchIndexService::collectPage`.
    - Register `POST_BUILD` listener to write `search.json` and copy assets.

### 2. Client-Side Assets (`src/Features/Search/assets/`)
- [ ] **Create `search.js`**:
    - Initialize MiniSearch.
    - Fetch `search.json` on demand (lazy load on hover/focus recommended).
    - Provide a simple API for templates to use (e.g., `window.StaticForgeSearch.init()`).
- [ ] **Download MiniSearch**: Include the `minisearch.min.js` library (or fetch from CDN in the template, but bundling is safer for offline dev). *Decision: Bundle a specific version to ensure stability.*

### 3. Configuration
- [ ] **Update `siteconfig.yaml`**: Add default search configuration.
    ```yaml
    search:
      enabled: true
      exclude_paths:
        - /tags/
        - /categories/
      exclude_content_in: []
    ```
- [ ] **Update `siteconfig.yaml.example`**: Reflect the new configuration options.

### 4. Testing
- [ ] **Unit Test `SearchIndexService`**:
    - Test that `search_index: false` excludes a page.
    - Test that `exclude_paths` works.
    - Test that drafts are not present (if passed to service).
- [ ] **Integration Test**:
    - Run a build and verify `search.json` exists in `public/`.
    - Verify `search.json` contains expected data.

### 5. Documentation
- [ ] **User Guide**: Create `content/features/search.md` explaining how to use the search feature, configure exclusions, and implement the UI in templates.
- [ ] **Developer Docs**: Update `documents/features.md` to list the new feature.

## Dependencies
- `lucaong/minisearch` (Client-side JS library)
