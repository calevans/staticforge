# Feature Ideas

## 1. Sitemap.xml Generation
**Priority:** High
**Why:** Critical for SEO. Search engines rely on this to index the site correctly.
**Implementation:** Similar to `RssFeed` feature, listen to `POST_RENDER` to collect URLs and `POST_LOOP` to write the XML file.

## 2. Draft Content Support
**Priority:** High
**Why:** Essential for publishing workflows. Need to exclude files with `draft: true` in frontmatter.
**Implementation:** Update `FileDiscovery` or create a new `Drafts` feature to filter files based on frontmatter.

## 3. Image Optimization Pipeline
**Priority:** Medium
**Why:** Need built-in feature to automatically resize, crop, or convert images (to WebP/AVIF).
**Implementation:** A feature that intercepts image tags or processes an `assets/images` folder.

## 4. Asset Minification & Bundling
**Priority:** Medium
**Why:** Improve performance by minifying CSS and JS files.
**Implementation:** A feature to minify assets in `public/` after the build.
