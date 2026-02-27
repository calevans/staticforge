# Design Document: Dependency Injection Refactoring for Features

## 1. Problem Statement

Currently, many Feature classes in StaticForge (e.g., `MarkdownRenderer\Feature`) instantiate their own complex dependencies using the `new` keyword inside their `register()` methods. Furthermore, `FeatureManager` dynamically instantiates features using a zero-argument constructor (`new $className()`).

**Issues with the Current State:**
1.  **Violation of Dependency Inversion (SOLID):** Features are tightly coupled to specific concrete implementations rather than abstractions.
2.  **Service Locator Anti-Pattern:** Passing the `Container` around to pull dependencies manually hides actual dependencies and violates DIP.
3.  **Untestable Code:** Hardcoded dependencies make it impossible to inject mock objects during unit testing.
4.  **Eager Loading & Resource Inefficiency:** Services are instantiated immediately, even if their specific events are never triggered.
5.  **Security Risks:** Mutable containers passed around can lead to dependency hijacking, and dynamic instantiation of unvalidated class names poses a risk.

## 2. Proposed Solution

We will refactor the system to use **Constructor Injection** for all Features and Services, leveraging the `Container` strictly during the bootstrap phase and within `FeatureManager`. We will adhere to PSR-11 (`ContainerInterface`) and bind to interfaces rather than concrete classes.

### Step 1: Secure Bootstrap & Interface Binding
During the application bootstrap phase, we will register shared services into the Container using factory closures for lazy evaluation. We will bind to interfaces and configure secure defaults.

```php
// Example Bootstrap Registration (Lazy Loaded via Closures)
$container->add(MarkdownProcessorInterface::class, function() {
    // Secure default: configure to sanitize HTML/prevent XSS
    return new MarkdownProcessor(['sanitize_html' => true]);
});

$container->add(TemplateRendererInterface::class, function($c) {
    // Secure default: strict context-aware escaping
    return new TemplateRenderer(
        $c->get(TemplateVariableBuilderInterface::class),
        $c->get(LoggerInterface::class),
        $c->has(AssetManagerInterface::class) ? $c->get(AssetManagerInterface::class) : null
    );
});

// Register Feature-specific services
$container->add(MarkdownRendererServiceInterface::class, function($c) {
    return new MarkdownRendererService(
        $c->get(LoggerInterface::class),
        $c->get(MarkdownProcessorInterface::class),
        $c->get(ContentExtractorInterface::class),
        $c->get(TemplateRendererInterface::class)
    );
});
```

### Step 2: Refactor `FeatureManager` for Safe Resolution
`FeatureManager` will be updated to resolve Feature classes directly from the Container instead of using `new $className()`. It will also implement strict whitelisting/validation for `$className` to prevent arbitrary object instantiation.

### Step 3: Refactor Features to use Constructor Injection
Features will declare their dependencies in their constructors using PHP 8.0 Constructor Property Promotion and PHP 8.4 Asymmetric Visibility (`public private(set)`). The `register()` method will revert to its strict interface definition.

```php
use Psr\Container\ContainerInterface;

class Feature extends BaseRendererFeature implements FeatureInterface
{
    // PHP 8.4 Asymmetric Visibility & PHP 8.0 Constructor Property Promotion
    public function __construct(
        public private(set) LoggerInterface $logger,
        public private(set) MarkdownRendererServiceInterface $service
    ) {
        $this->name = 'MarkdownRenderer';
    }

    // Interface preserved: strictly for event binding
    public function register(EventManager $eventManager): void
    {
        $eventManager->listen('RENDER', [$this, 'handleRender'], 100);
    }
    
    public function handleRender(array $parameters): array
    {
        // Service is already injected and ready to use
        return $this->service->processMarkdownFile($parameters);
    }
}
```

---

## 3. Subagent Review & Feedback (Incorporated)

### 🏗️ Architect's Review
*   **Constructor Injection:** Shifted from Method Injection (Service Locator anti-pattern) to true Constructor Injection.
*   **FeatureManager Refactor:** `FeatureManager` now resolves Features from the Container.
*   **Interface Preservation:** The `FeatureInterface::register(EventManager $events)` signature is preserved.
*   **Lazy Evaluation:** Factory closures in the bootstrap phase ensure services are only instantiated when requested.
*   **PSR-11:** We now type-hint against `Psr\Container\ContainerInterface` and domain interfaces instead of concrete classes.

### 🛡️ Security Expert's Review
*   **Dependency Hijacking:** By using Constructor Injection and removing the mutable `Container` from the `register()` method, features can no longer overwrite core services.
*   **Instantiation Safety:** `FeatureManager` will implement strict validation/whitelisting before resolving class names from the container.
*   **Secure Defaults:** Bootstrap registration explicitly configures services with secure defaults (e.g., XSS prevention in `MarkdownProcessor`).

### 🧪 QA Engineer's Review
*   **Fail-Fast Mechanism:** Since `EICC\Utils\Container::get()` returns `null` on missing keys, the Container wrapper/factory must explicitly check for `null` and throw a `CoreException` to fail fast.
*   **Null-Safety:** Optional dependencies (like `AssetManagerInterface`) must use nullable type hints (`?AssetManagerInterface`) and implement internal null-safe logic.
*   **Mocking Strategy:** Unit tests will use a mocked instance of `ContainerInterface` configured to return mock dependencies.
*   **Test Isolation:** The mock Container and all mock dependencies must be freshly instantiated in the `setUp()` method of every test class to prevent state bleed.

### 💻 Senior Developer's Review
*   **PHP 8.4 Features:** Adopted PHP 8.4 Asymmetric Visibility (`public private(set)`) for injected properties.
*   **Interface Binding:** Container keys now use interfaces (e.g., `MarkdownProcessorInterface::class`) instead of concrete classes.
*   **Full Container Resolution:** Removed `new MarkdownRendererService(...)` from the Feature. It is now registered in the container and injected.
*   **Correction:** Acknowledged that Constructor Property Promotion is a PHP 8.0 feature, not 8.4.

---

## 4. Final Implementation Plan

1.  **Define Interfaces:** Extract interfaces for core services (`MarkdownProcessorInterface`, `TemplateRendererInterface`, etc.) if they don't already exist.
2.  **Update Bootstrap:** Modify `src/bootstrap.php` to register services and feature-services into the Container using factory closures and interfaces. Implement fail-fast `null` checks throwing `CoreException`.
3.  **Refactor `FeatureManager`:** Update `findFeatureClassInFile` to validate the class name and resolve it via `$container->get($className)`.
4.  **Refactor Features & Services:** Update constructors to use Constructor Property Promotion and PHP 8.4 Asymmetric Visibility. Remove `Container` from `register()`.
5.  **Update Tests:** Refactor unit tests to mock `ContainerInterface` and inject mock dependencies in `setUp()`.
