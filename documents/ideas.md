# Feature Ideas

## 3. Image Optimization Pipeline
**Priority:** Medium
**Why:** Need built-in feature to automatically resize, crop, or convert images (to WebP/AVIF).
**Implementation:** A feature that intercepts image tags or processes an `assets/images` folder.

## 4. Asset Minification & Bundling
**Priority:** Medium
**Why:** Improve performance by minifying CSS and JS files.
**Implementation:** A feature to minify assets in `public/` after the build.


## 6. Breakup siteconfig.yml into data directory
**Priority:** Low
**Why:** Easier to manage large configurations.
**Implementation:** Support loading multiple YAML files from `data/` and merging them.

## 7. Validate yml before parsing.
**Priority:** Medium
**Why:** Prevent runtime errors due to misconfigurations.
**Implementation:** Validate that the YAML in a file is valid and will read before trying to load it.
