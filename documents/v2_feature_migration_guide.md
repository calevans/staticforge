# StaticForge v2.0 Feature Migration Guide

This document provides instructions for AI agents and developers to upgrade external StaticForge Features to be compatible with the StaticForge v2.0 Dependency Injection (DI) refactor.

## 1. The Breaking Changes in v2.0

StaticForge v2.0 introduces a strict Dependency Injection architecture to improve testability, security, and resource efficiency. 

**Key Changes:**
1.  **`FeatureInterface` Signature Change:** The `register()` method no longer accepts the `Container`. It is now strictly for event binding.
    *   *v1.x:* `public function register(EventManager $eventManager, Container $container): void;`
    *   *v2.0:* `public function register(EventManager $eventManager): void;`
2.  **Constructor Injection Required:** Features must now declare their dependencies (including the Logger and any custom Services) in their `__construct()` method. `FeatureManager` will automatically resolve these from the Container.
3.  **No More `new` for Services:** Features should no longer instantiate complex services using the `new` keyword inside the `register()` method.

## 2. Migration Steps for AI Agents

When upgrading a v1.x Feature to v2.0, follow these exact steps:

### Step 1: Update the Feature Class Signature
Remove the `Container` from the `register()` method signature.

**Before (v1.x):**
```php
public function register(EventManager $eventManager, Container $container): void
{
    parent::register($eventManager, $container);
    $this->logger = $container->get('logger');
    // ...
}
```

**After (v2.0):**
```php
public function register(EventManager $eventManager): void
{
    // Only register event listeners here
    $eventManager->listen('RENDER', [$this, 'handleRender'], 100);
}
```

### Step 2: Implement Constructor Injection
Move all dependency resolution into the `__construct()` method using PHP 8.0 Constructor Property Promotion and PHP 8.4 Asymmetric Visibility.

**Before (v1.x):**
```php
class MyFeature extends BaseFeature 
{
    private MyService $service;
    
    public function register(EventManager $eventManager, Container $container): void 
    {
        $this->service = new MyService($container->get('logger'));
    }
}
```

**After (v2.0):**
```php
use Psr\Log\LoggerInterface;

class MyFeature extends BaseFeature 
{
    public function __construct(
        public private(set) LoggerInterface $logger,
        public private(set) MyServiceInterface $service
    ) {
        $this->name = 'MyFeature';
    }
}
```

### Step 3: Create a Service Provider (If Applicable)
If your external feature provides complex services that need to be registered in the Container (so they can be injected into your Feature class), you must create a `ServiceProvider` class or document how the user should register your service in their `siteconfig.yaml` or bootstrap file.

*Note: StaticForge v2.0 automatically registers core services like `TemplateRendererInterface` and `LoggerInterface`.*

### Step 4: Update Unit Tests
Because dependencies are now injected via the constructor, you must update your PHPUnit tests. You no longer need to pass a mock Container to `register()`. Instead, pass mock dependencies directly into the Feature's constructor.

**Before (v1.x):**
```php
$feature = new MyFeature();
$feature->register($mockEventManager, $mockContainer);
```

**After (v2.0):**
```php
$feature = new MyFeature($mockLogger, $mockService);
$feature->register($mockEventManager);
```

### Step 5: Update Event Handlers to Receive Container (If Needed)
Because the `Container` is no longer stored as a class property (`$this->container`) during `register()`, event handlers that require the container must now accept it as an argument. The StaticForge v2.0 `EventManager` automatically passes the `Container` as the first argument to all event listeners.

**Before (v1.x):**
```php
public function handleRender(array $parameters): array
{
    $config = $this->container->getVariable('site_config');
    // ...
}
```

**After (v2.0):**
```php
use Psr\Container\ContainerInterface;

public function handleRender(ContainerInterface $container, array $parameters): array
{
    $config = $container->get('site_config');
    // ...
}
```
*Note: If your feature only needs specific services, it is better to inject those services via the constructor rather than pulling them from the container inside the event handler.*

### Step 6: Remove `@` Error Suppression
If your feature uses the `@` operator to suppress errors (e.g., `@file_get_contents`), remove it. Use proper error handling (e.g., `is_readable()`) and throw descriptive exceptions instead.

### Step 7: Ensure Strict Path Validation
If your feature reads or writes files, ensure you are strictly validating paths to prevent directory traversal vulnerabilities. Do not blindly trust paths passed in event parameters.

## 3. Verification Checklist
- [ ] Does `register()` only accept `EventManager`?
- [ ] Are all dependencies injected via `__construct()`?
- [ ] Are you type-hinting against Interfaces (e.g., `LoggerInterface`) rather than concrete classes?
- [ ] Have all `new` keywords for complex services been removed from the Feature class?
- [ ] Do event handlers that need the container accept `ContainerInterface $container` as their first argument?
- [ ] Have all `@` error suppression operators been removed?
- [ ] Are all file paths strictly validated before reading/writing?
- [ ] Do all unit tests pass with the new constructor injection?