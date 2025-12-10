# Feature Ideas

## 3. Image Optimization Pipeline
**Priority:** Medium
**Why:** Need built-in feature to automatically resize, crop, or convert images (to WebP/AVIF).
**Implementation:** A feature that intercepts image tags or processes an `assets/images` folder.

## 4. Asset Minification & Bundling
**Priority:** Medium
**Why:** Improve performance by minifying CSS and JS files.
**Implementation:** A feature to minify assets in `public/` after the build.

## 5. Refactor all features to make sure we are instantiating them in a standard way.

## 6. Breakup siteconfig.yml into data directory
**Priority:** Low
**Why:** Easier to manage large configurations.
**Implementation:** Support loading multiple YAML files from `data/` and merging them.

## 7. Validate yml before parsing.
**Priority:** Medium
**Why:** Prevent runtime errors due to misconfigurations.
**Implementation:** Validate that the YAML in a file is valid and will read before trying to load it.

## Feature Scaffolding Command
**Priority:** High
**Why:** Simplify the process of creating new features.
**Implementation:** A console command `feature:create <feature-name>` that generates boilerplate code for a new feature, including:
- Directory structure
- Basic class implementing `FeatureInterface`
- Example event listener registration
- Sample configuration file
- Unit test template
- Documentation stub

