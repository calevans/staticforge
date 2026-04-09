# StaticForge - Project Summary

This document provides a comprehensive overview of the **StaticForge** project. It is intended to serve as the primary context for any LLM or developer working on this codebase.

## 1. System Overview

**StaticForge** is a PHP 8.4+ event-driven static site generator. It takes source content (Markdown and HTML files with YAML frontmatter) and compiles it into a full static website inside the `public/` directory.

- **Primary Stack**: PHP 8.4+, Twig (Templating), Symfony Console (CLI).
- **Architecture**: Component-based, highly modular, and heavily reliant on an Event-Driven pipeline.
- **Dependency Injection**: Utilizes `EICC\Utils\Container` to manage state, configuration, and services.
- **Local Development Environment**: Uses **Lando** (a Docker wrapper). All terminal commands should be prefixed with `lando` (e.g., `lando php`, `lando composer`).

## 2. Directory Structure

- `src/Core/`: The core framework kernel (Application bootstrapper, EventManager, interfaces like `FeatureInterface`).
- `src/Features/`: Bundled core features (e.g., Markdown rendering, RSS feeds, SEO).
- `Features/`: External or non-core features developed separately.
- `src/Commands/`: CLI entry points utilizing Symfony Console (`RenderSiteCommand` etc.).
- `src/Services/`: Shared business logic and single-purpose service classes.
- `content/`: The source input files. Supports `.md` and `.html` with YAML frontmatter.
- `templates/`: Twig template files used for layout and rendering.
- `public/`: The build output directory. **Note**: This folder is wiped and regenerated on every build.
- `tests/`: PHPUnit tests organized into `Unit/` and `Integration/`.
- `bin/`: Executable scripts (e.g., `staticforge.php` which is the main CLI runner).

## 3. Core Architecture & Build Life Cycle

The build process (triggered via `lando php bin/staticforge.php site:render`) strictly follows this event sequence:

1. **Bootstrap**: Loads `.env`, `siteconfig.yaml`, configures system definitions, and initializes the DI Container.
2. **`PRE_GLOB`**: Hooks that fire right before the file system is scanned.
3. **File Discovery**: The system scans the `content/` directory for renderable files.
4. **`POST_GLOB`**: Hooks that fire after files are located but before processing begins.
5. **`PRE_LOOP`**: Final setup before entering the main processing loop.
6. **The Loop** (Iterates over every source file):
    - **`PRE_RENDER`**: Prepare content and metadata (e.g., process Shortcodes).
    - **`RENDER`**: Transform content (e.g., Markdown -> HTML).
    - **`POST_RENDER`**: Modify final HTML (e.g., inject Table of Contents or tracking scripts).
7. **`POST_LOOP`**: Global artifact generation that relies on all pages being processed (e.g., Sitemaps, RSS feeds, Category indexes).
8. **`DESTROY`**: Final cleanup operations.

There are also feature-specific custom events like `MARKDOWN_CONVERTED`, `COLLECT_MENU_ITEMS`, `RSS_BUILDER_INIT`, and `SEO_AUDIT_PAGE`.

## 4. Coding Standards & Conventions

- **Strict Typing**: Every PHP file must declare `declare(strict_types=1);` at the top.
- **PSR Standards**: Code must strictly adhere to PSR-12 formatting and PSR-4 Autoloading.
- **Dependency Injection**: Services and dependencies must be retrieved via the DI Container. Avoid using the `new` keyword to instantiate services inside other services.
- **Features over Core Modifications**: New functionality must be implemented as isolated **Features** (implementing `EICC\StaticForge\Core\FeatureInterface`) rather than modifying the core Kernel.
- **Absolute Paths**: Always use absolute paths via `$container->getVariable('app_root')`. Avoid relative path assumptions.

## 5. Standard Lando Commands

When interacting with the workspace, always use the `lando` environment:

- **CLI Usage**: `lando php bin/staticforge.php list` | `lando php bin/staticforge.php site:render`
- **Testing**: `lando phpunit` (or target specific files: `lando phpunit tests/Unit/...`)
- **Code Style**: `lando phpcs src/` | `lando phpcbf`
- **Composer**: `lando composer install` | `lando composer require ...`

*Never run commands like `lando start`, `lando restart`, `lando destroy`, or `lando rebuild` without explicit permission.*

## 6. General Guidelines

- **KISS, SOLID, DRY, YAGNI**: Adhere to core architectural principles. Favor composition over inheritance and interfaces over concrete implementations.
- **Extraction Strategy**: Build core features with the intent that they can be extracted into their own Composer packages in the future.
- Never write manually to `public/` during the render loop unless unavoidable; let the standard renderer output the files.