---
description: Architectural guidelines for developing new self-contained Features in StaticForge.
applyTo: "src/Features/**/*.php"
---

# StaticForge Feature Architecture

This skill defines the mandatory approach to writing new core capabilities inside the project.

## Core Directives

1. **Features over Core Modifications**:
   All new logic must be encapsulated in a Feature class within `src/Features/{FeatureName}/Feature.php`. Do not modify models, controllers, or logic in `src/Core/` directly unless absolutely necessary.

2. **Implementation Interface**:
   - The primary entry point MUST implement `EICC\StaticForge\Core\FeatureInterface`.
   - If configuration is needed, it must also implement `EICC\StaticForge\Core\ConfigurableFeatureInterface`.

3. **Event-Driven Hooking**:
   Features interact with the lifecycle via Event Mapping (priorities 0–999, lower runs earlier). Inside your `register(EventManager $events)` method, listen to the necessary events such as:
   - `PRE_GLOB`, `POST_GLOB`, `PRE_LOOP`
   - `PRE_RENDER`, `RENDER`, `POST_RENDER`
   - Custom extensions like `MARKDOWN_CONVERTED`, `COLLECT_MENU_ITEMS`, or `RSS_BUILDER_INIT`.

4. **Self-Containment Strategy**:
   The `src/Features/{FeatureName}` directory must remain self-contained. The eventual goal for all core features is automated extraction into standalone Composer packages (via `scripts/extract_feature.php`).
   - Put all specific business logic inside `Services/`.
   - Put any DTOs inside `Models/`.

5. **Vendors & External Classes**:
   NEVER manually modify any package inside the `vendor/` directory. All overrides must happen at the architecture level (overriding container bindings or listening to events with high priority).