# Feature Refactoring Burndown List

We have elected `CategoryIndex` as the "Gold Standard" for feature architecture in StaticForge. This document tracks the progress of refactoring all other features to match this standard.

## The "CategoryIndex Standard" Checklist

To meet the standard, a feature must adhere to the following criteria:

### 1. Architecture & Separation of Concerns (SOLID)
- [ ] **Thin Feature Class**: The `Feature.php` class acts **only** as a controller/wiring mechanism. It should not contain business logic.
- [ ] **Service-Oriented**: All business logic (data processing, file generation, parsing) is moved to dedicated Service classes in a `Services/` namespace.
- [ ] **State Management**: The `Feature` class should hold **no state** (arrays of data, counters, etc.) other than references to its Services. State belongs in the Services.

### 2. Dependency Injection & Wiring
- [ ] **Explicit Dependencies**: Services are instantiated or retrieved from the Container in the `register()` method.
- [ ] **Constructor Injection**: Dependencies (Logger, other Services) are passed into Services via their constructor.
- [ ] **Method Injection**: The `Container` is passed as an argument to Service methods that need access to application state (e.g., `process(Container $container)`), rather than being stored as a property on the Service.
- [ ] **Feature Dependencies**: If the feature relies on another feature, it uses `$this->requireFeatures(['OtherFeature'])` in its event handlers.

### 3. Code Quality (DRY, KISS, YAGNI)
- [ ] **Single Responsibility**: Each Service should do one thing well (e.g., `ImageService`, `MenuService`).
- [ ] **No Duplication**: Common logic is shared via Services or Core utilities, not repeated in the Feature.
- [ ] **Logging**: The feature uses the `Logger` service for info and error reporting, passed down to Services.

### 4. Testing
- [ ] **Unit Tests**: Services are unit tested in isolation.
- [ ] **Feature Tests**: The Feature class is tested to ensure it wires events correctly.
- [ ] **Meaningful Tests**: Tests must verify actual feature behavior and business logic. Avoid trivial tests (e.g., testing PHP's ability to add numbers) or testing mocks against themselves.


---

## Feature Status

| Feature | Status | Notes |
| :--- | :--- | :--- |
| **CategoryIndex** | ✅ **Standard** | The reference implementation. |
| **CacheBuster** | ✅ **Standard** | Refactored to use `CacheBusterService`. |
| **Categories** | ✅ **Standard** | Refactored to use `CategoriesService`. |
| **ChapterNav** | ✅ **Standard** | Refactored to use `ChapterNavService`. |
| **Forms** | ✅ **Standard** | Refactored to use `FormsService`. |
| **HtmlRenderer** | ✅ **Standard** | Refactored to use `HtmlRendererService`. |
| **MarkdownRenderer** | ✅ **Standard** | Refactored to use `MarkdownRendererService` and `BaseRendererService`. |
| **MenuBuilder** | ✅ **Standard** | Refactored to use `MenuBuilderService`. |
| **RobotsTxt** | ✅ **Standard** | Refactored to use `RobotsTxtService`. |
| **RssFeed** | ✅ **Standard** | Refactored to use `RssFeedService`. |
| **ShortcodeProcessor** | ✅ **Standard** | Refactored to use `ShortcodeProcessorService`. |
| **Sitemap** | ✅ **Standard** | Refactored to use `SitemapService`. |
| **TableOfContents** | ✅ **Standard** | Refactored to use `TableOfContentsService`. |
| **Tags** | ✅ **Standard** | Refactored to use `TagsService`. |
| **TemplateAssets** | ✅ **Standard** | Refactored to use `TemplateAssetsService`. |

## Refactoring Priority

All features have been refactored to the CategoryIndex Standard.


