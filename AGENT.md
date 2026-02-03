# StaticForge Agent Context

This document serves as the primary context for AI agents working on StaticForge. It encapsulates the system's architecture, conventions, and operational rules.

## 1. System Overview

**StaticForge** is a PHP 8.4+ static site generator. It compiles content (Markdown/HTML) into a static site (`public/`) using a component-based, event-driven architecture.

-   **Runtime**: Lando (Docker wrapper). **ALL** commands must be prefixed with `lando`.
-   **Dependency Injection**: `EICC\Utils\Container` stores configuration, services, and state.
-   **Event System**: Priority-based execution (0-999). Lower numbers run first.
-   **Templating**: Twig.

## 2. Directory Structure

-   **`src/Core`**: The framework kernel (Application, EventManager, FeatureInterface).
-   **`src/Features`**: Self-contained modules (e.g., `MarkdownRenderer`, `RssFeed`). **New development happens here.**
-   **`src/Commands`**: CLI entry points (e.g., `RenderSiteCommand`).
-   **`src/Services`**: Shared business logic (e.g., `TemplateVariableBuilder`).
-   **`content/`**: Source files (Markdown, HTML) with YAML frontmatter.
-   **`templates/`**: Twig templates.
-   **`public/`**: Build output (wiped on every build).
-   **`tests/`**: PHPUnit tests (`Unit` and `Integration`).

## 3. Core Architecture & Life Cycle

The build process (`site:render`) follows this strict event sequence:

1.  **Bootstrap**: Load `.env`, `siteconfig.yaml`, initialize Container.
2.  **`PRE_GLOB`**: Hooks before file scanning.
3.  **File Discovery**: Scans `content/` for files.
4.  **`POST_GLOB`**: Hooks after files are found but before processing.
5.  **`PRE_LOOP`**: Setup before the main processing loop.
6.  **The Loop** (Iterates over every source file):
    -   **`PRE_RENDER`**: Prepare content/metadata (e.g., Shortcodes).
    -   **`RENDER`**: Transform content (e.g., Markdown -> HTML).
    -   **`POST_RENDER`**: Modify final HTML (e.g., injection).
7.  **`POST_LOOP`**: Global artifact generation (Sitemap, RSS, Categories).
8.  **`DESTROY`**: Final cleanup.

## 4. Feature Development Standards ("The Golden Rules")

New functionality **MUST** be implemented as a **Feature**.

### Structure
A Feature resides in `src/Features/{FeatureName}` and must contain:
-   **`Feature.php`**: The entry point.
    -   Must implement `EICC\StaticForge\Core\FeatureInterface`.
    -   Should implement `EICC\StaticForge\Core\ConfigurableFeatureInterface` if it needs config.
    -   Registers listeners in `register(EventManager $events)`.
-   **`Services/`**: Business logic classes.
-   **`Models/`**: DTOs (if needed).

### Coding Conventions
-   **Strict Types**: `declare(strict_types=1);` at the top of **every** PHP file.
-   **PSR-12**: Adhere strictly to PSR-12 formatting.
-   **Naming**:
    -   Classes: `PascalCase`
    -   Methods/Variables: `camelCase`
    -   Constants: `UPPER_SNAKE_CASE`
-   **Dependency Injection**: NEVER `new` up services inside other services if possible. Use the Container.

### Configuration
-   **`siteconfig.yaml`**: User settings (e.g., `rss.title`). Accessed via `$container->getVariable('site_config')`.
-   **`.env`**: Secrets/Environment (e.g., `API_KEY`). Accessed via `$container->getVariable('API_KEY')`.

## 5. Development & Testing Workflow

**Lando is MANDATORY.**

-   **Run Tests**: `lando phpunit`
-   **Run Specific Test**: `lando phpunit tests/Unit/Features/MyFeature/MyTest.php`
-   **Lint Code**: `lando phpcs src/`
-   **Fix Linting**: `lando phpcbf`
-   **Run Build**: `lando php bin/staticforge.php site:render`

## 6. Future Extraction Strategy

We are building new features with the explicit goal of extracting them into standalone Composer packages later.

-   **Self-Containment**: The feature must be entirely contained within `src/Features/{FeatureName}`.
-   **No Core Modification**: Do not modify `src/Core` to support the feature unless absolutely unavoidable.
-   **Explicit Dependencies**: If the feature needs a library, check `composer.json`. If adding one, note that it will need to move with the feature.
-   **Extraction Tool**: We will eventually use `scripts/extract_feature.php` to automate the extraction.

## 7. Critical Gotchas

-   **Paths**: Always use **absolute paths**. Use `$container->getVariable('app_root')` as the base.
-   **Output**: Never write directly to `public/` manually inside the loop unless strictly necessary. Let the renderer handle standard file output.
## 8. Agent Workflows
**CRITICAL**: The user mandates a strict, multi-agent validation process.

### A. New Feature / Architecture Workflow
**Trigger**: Creating new features, commands, or complex refactoring.

1.  **Plan (Architect Agent)**:
    -   **MANDATORY**: Use the `ARCHITECT_AGENT` to research and draft a detailed plan in `documents/plan_{feature}.md`.
    -   The plan must define: Data structures, Configuration, Class structure, and Security implications.
    -   Wait for user approval of the plan (implicit or explicit).

2.  **Implement (Developer)**:
    -   Write the code following the approved plan and "Feature Development Standards" (Section 4).

3.  **Review (Code Reviewer Agent)**:
    -   **MANDATORY**: Use the `code-reviewer` agent.
    -   Action: Fix *all* issues.

4.  **Audit (Security Auditor Agent)**:
    -   **MANDATORY**: Use the `security-auditor` agent.
    -   Action: Fix *all* security issues.

5.  **Verify (QA)**:
    -   **MANDATORY**: Run `lando php bin/staticforge.php site:render` and tests.

### B. Bug Fix / Maintenance Workflow
**Trigger**: Debugging, bug fixes, or minor tweaks.

1.  **Analyze & Fix (Developer)**:
    -   Locate the issue and apply the fix.
    -   (Planning agent is optional/skipped for speed).

2.  **Review (Code Reviewer Agent)**:
    -   **MANDATORY**: Use the `code-reviewer` agent.

3.  **Audit (Security Auditor Agent)**:
    -   **MANDATORY**: Use the `security-auditor` agent.

4.  **Verify (QA)**:


