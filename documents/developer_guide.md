# StaticForge Developer Guide & Project Wisdom

This document consolidates the core architectural decisions, design philosophies, and "gold standard" references for StaticForge. It serves as the primary reference for developers (and AI agents) working on the core system or creating new features.

## 1. Guiding Principles (The Rules)

**CRITICAL**: Everything we do must adhere to these four pillars. Violations of these principles will be rejected.

*   **KISS (Keep It Simple, Stupid)**: Complexity is the enemy. If a solution feels clever, it's probably wrong. Choose the simplest implementation that works.
*   **SOLID**: Follow standard object-oriented design principles. Single Responsibility is paramount—features should do one thing well.
*   **YAGNI (You Aren't Gonna Need It)**: Do not build features for hypothetical future use cases. Build only what is needed right now.
*   **DRY (Don't Repeat Yourself)**: Abstract common logic into services or utilities. If you copy-paste code, you are creating technical debt.

## 2. Core Philosophy

StaticForge is a PHP-based static site generator designed to bridge the gap between PHP developers and static site generation.

*   **Language**: PHP 8.4+. We build in PHP.
*   **Output**: Completely static HTML/CSS/JS. No PHP or database is required at runtime.
*   **Architecture**: Event-driven pipeline. Content flows through a series of events where "Features" can modify it.

## 3. Gold Standards (Copy These Patterns)

When in doubt, look at these examples for the "right way" to do things. Mimic their structure, style, and patterns.

*   **Code / Feature Implementation**: `src/Features/CategoryIndex`
    *   *Why*: Demonstrates proper event registration, service separation, and container usage.
*   **Documentation**: `content/guide/index.md`
    *   *Why*: Demonstrates the desired tone (conversational, helpful) and structure.

## 4. Architecture Overview

### Core Concepts

*   **Container** (`EICC\Utils\Container`): The central registry for configuration, services, and shared state.
*   **EventManager**: A priority-based event dispatcher. Listeners are executed in order (0-999).
*   **Feature**: A self-contained unit of functionality (e.g., Markdown rendering, Menu generation).
*   **Content File**: A source file (Markdown, HTML) with YAML frontmatter metadata.

### The Build Pipeline (The "Heartbeat")

The `site:render` command executes this specific sequence of events. Features hook into these events to do their work.

1.  **Bootstrap**: Load `.env`, instantiate Container, register Features.
2.  **`CREATE`**: Feature initialization.
3.  **`PRE_GLOB`**: Hooks before file discovery.
4.  **File Discovery**: The system scans `content/` for files.
5.  **`POST_GLOB`**: Hooks after files are found but before processing.
6.  **`PRE_LOOP`**: Setup before the main processing loop.
7.  **The Loop** (For each file):
    *   **`PRE_RENDER`**: Prepare the file (e.g., parse frontmatter).
    *   **`RENDER`**: Convert content to HTML (e.g., Markdown -> HTML).
    *   **`POST_RENDER`**: Modify the HTML (e.g., inject scripts, collect sitemap URLs).
8.  **`POST_LOOP`**: After all files are processed (e.g., generate Sitemap, RSS, Category Indices).
9.  **`DESTROY`**: Final cleanup.

## 5. Feature Development

Features are the primary way to extend StaticForge.

*   **Interface**: Must implement `EICC\StaticForge\Core\FeatureInterface`.
*   **Configuration Validation**: Implement `EICC\StaticForge\Core\ConfigurableFeatureInterface` to define required `siteconfig.yaml` keys and `.env` variables.
*   **Registration**: Registered via `composer.json` `extra.staticforge` key (for external packages) or internal wiring.
*   **Configuration**:
    *   **Secrets**: Use `.env` (accessed via Container).
    *   **Settings**: Use `siteconfig.yaml` (accessed via Container).
    *   **Never** hardcode paths or credentials.

## 6. Data Model

*   **Content Source**: `content/` directory.
*   **Output**: `public/` directory (wiped on every build).
*   **Metadata**: YAML frontmatter in files is the source of truth for titles, dates, categories, etc.

## 7. Feature Specifics

### Shortcodes
*   **Syntax**: `[[shortcode attr="value"]]` or `[[shortcode]]content[[/shortcode]]`.
*   **Implementation**: `src/Shortcodes/` and `src/Features/ShortcodeProcessor`.
*   **Priority**: Runs on `PRE_RENDER` (High priority) to process before Markdown/HTML rendering.

### Robots.txt
*   **Control**: Add `robots: no` (case-insensitive) to frontmatter to exclude a page.
*   **Categories**: Add `robots: no` to category definition files to exclude entire categories.
*   **Defaults**: Pages default to `robots: yes`.

## 8. Future Ideas & Roadmap

These are "nuggets" from previous planning documents that are not yet implemented but are good ideas:

*   **Image Optimization**: A pipeline to resize/crop images and convert to WebP/AVIF automatically.
*   **Asset Minification**: Minify CSS/JS in `public/` after the build.
*   **Data Directory**: Break `siteconfig.yaml` into multiple files in a `data/` directory for better organization.

## 9. Historical Context

*   **Origin**: Created to give PHP developers a native tool for static sites.
*   **Evolution**: Started as a simple script, evolved into a Symfony Console application with a robust event system.
