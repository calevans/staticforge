---
title = "Bootstrap & Initialization"
template = "docs"
menu = 1.6, 2.15
---

# Bootstrap & Initialization

StaticForge uses a procedural bootstrap file for application initialization. This document explains how the bootstrap process works and how to use it in different contexts.

---

## Overview

The bootstrap process handles:
- Composer autoloading
- Environment variable loading
- Container initialization
- Logger configuration
- Application-wide service registration

All of this is done in a single file: `src/bootstrap.php`

---

## Bootstrap File Location

```
src/bootstrap.php
```

This is a **procedural script**, not a class. It accepts parameters and returns a configured container.

---

## How Bootstrap Works

### Basic Flow

1. **Autoloading**: Requires Composer's autoloader
2. **Environment Loading**: Loads `.env` file using Dotenv
3. **Container Creation**: Creates EICC\Utils\Container instance
4. **Logger Registration**: Registers logger as singleton service
5. **Return**: Returns fully configured container

### Code Structure

```php
<?php
// Accept optional environment file path
$envPath = $envPath ?? '.env';

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createUnsafeImmutable(
    dirname($envPath),
    basename($envPath)
);
$dotenv->load();

// Create container
$container = new EICC\Utils\Container();

// Copy environment variables to container for backward compatibility
foreach ($_ENV as $key => $value) {
    $container->setVariable($key, $value);
}

// Register logger as singleton
$container->stuff('logger', function() {
    return new EICC\Utils\Log(
        'staticforge',
        $_ENV['LOG_FILE'] ?? 'logs/staticforge.log',
        $_ENV['LOG_LEVEL'] ?? 'INFO'
    );
});

// Return configured container
return $container;
```

---

## Using Bootstrap in Entry Points

### Console Entry Point

The main console script uses bootstrap:

```php
#!/usr/bin/env php
<?php
// bin/console.php

// Bootstrap the application
$container = require_once __DIR__ . '/../src/bootstrap.php';

// Create Symfony Console application
$app = new Symfony\Component\Console\Application('StaticForge', '1.0.0');

// Register commands with container
$app->add(new EICC\StaticForge\Commands\RenderSiteCommand($container));
$app->add(new EICC\StaticForge\Commands\UploadSiteCommand($container));

// Run
$app->run();
```

**Key Points:**
- Bootstrap returns container
- Container passed to all commands
- Commands receive dependencies via constructor
- Single initialization point

---

## Using Bootstrap in Tests

### Unit Tests

Unit tests extend `UnitTestCase` which handles bootstrap:

```php
<?php
namespace EICC\StaticForge\Tests\Unit;

use PHPUnit\Framework\TestCase;

abstract class UnitTestCase extends TestCase
{
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        // Bootstrap with test environment
        $envPath = __DIR__ . '/../.env.testing';
        $this->container = include __DIR__ . '/../../src/bootstrap.php';
    }
}
```

**Usage in tests:**
```php
class MyFeatureTest extends UnitTestCase
{
    public function testSomething(): void
    {
        // Container already available
        $logger = $this->container->get('logger');

        // Use helper methods
        $this->setContainerVariable('SITE_NAME', 'Test Site');
        $this->addToContainer('my_service', new MyService());
    }
}
```

### Integration Tests

Integration tests extend `IntegrationTestCase`:

```php
<?php
class IntegrationTestCase extends TestCase
{
    /**
     * Create a container using custom env file
     */
    protected function createContainer(string $envPath): Container
    {
        return require __DIR__ . '/../../src/bootstrap.php';
    }
}
```

**Usage:**
```php
class MyIntegrationTest extends IntegrationTestCase
{
    public function testFullWorkflow(): void
    {
        $envPath = $this->createTestEnv([
            'SITE_NAME' => 'Integration Test',
            'OUTPUT_DIR' => '/tmp/test-output'
        ]);

        $container = $this->createContainer($envPath);
        $app = new Application($container);
        $app->generate();
    }
}
```

---

## Custom Environment Files

**In Production:** You never need to call bootstrap directly. The console entry point (`bin/console.php`) handles it.

**In Tests:** Tests use bootstrap with custom environment files:

```php
// In test setUp() - use 'include' to allow per-test bootstrapping
$envPath = __DIR__ . '/../.env.testing';
$container = include __DIR__ . '/../../src/bootstrap.php';
```

**Why tests are different:**
- Each test needs fresh container state
- Tests use `.env.testing` instead of `.env`
- `include` (not `include_once`) allows re-bootstrapping per test

---

## Container Services

### Logger Service

The logger is registered as a singleton:

```php
// In any code with access to container
$logger = $container->get('logger');
$logger->info('Processing started');
```

**Configuration** (from `.env`):
- `LOG_FILE` - Path to log file (default: `logs/staticforge.log`)
- `LOG_LEVEL` - Log level (default: `INFO`)

**Log Levels:**
- `DEBUG` - Detailed debugging information
- `INFO` - Informational messages
- `WARNING` - Warning messages
- `ERROR` - Error messages
- `CRITICAL` - Critical errors

### Environment Variables

All environment variables are accessible:

```php
// Direct access via $_ENV
$siteName = $_ENV['SITE_NAME'];

// Or via container (for backward compatibility)
$siteName = $container->getVariable('SITE_NAME');
```

---

## Best Practices

### DO: Use Bootstrap Once Per Entry Point

✅ **Good:**
```php
// bin/console.php - ONLY place that bootstraps
$container = require_once __DIR__ . '/../src/bootstrap.php';
$app->add(new SomeCommand($container));
```

❌ **Bad:**
```php
// NEVER bootstrap in commands, features, or other code
class SomeCommand {
    public function __construct() {
        $container = require_once 'src/bootstrap.php'; // NO! NEVER DO THIS!
    }
}
```

**Rule:** If you're not writing a test or the console entry point, you should NEVER call bootstrap.

### DO: Pass Container via Dependency Injection

✅ **Good:**
```php
class SomeCommand {
    public function __construct(Container $container) {
        $this->container = $container;
    }
}
```

❌ **Bad:**
```php
class SomeCommand {
    public function __construct() {
        $this->container = new Container(); // NO!
    }
}
```

### DO: Use require_once for Console Entry Point

✅ **Good:**
```php
// bin/console.php - The ONLY file that should do this
$container = require_once 'src/bootstrap.php';
```

**Why `require_once`:** Ensures bootstrap only executes once, preventing duplicate service registration.

**Who calls this:** ONLY `bin/console.php`. Commands, features, and application code receive the container via dependency injection.

### DO: Create Logger Only in Bootstrap

✅ **Good:**
```php
// In bootstrap.php
$container->stuff('logger', function() {
    return new Log(...);
});

// Everywhere else
$logger = $container->get('logger');
```

❌ **Bad:**
```php
// Don't create loggers elsewhere
$logger = new Log(...); // NO!
```

---

## Troubleshooting

### Container Already Has Logger

**Problem:** "Service 'logger' already exists in container"

**Cause:** Someone called bootstrap more than once (or tried to register logger manually)

**Solution:**

**Rule #1:** NEVER call bootstrap outside of `bin/console.php` or test files.

**Rule #2:** NEVER create a logger with `new Log()` - always get it from container:

```php
// Good - get logger from container
$logger = $container->get('logger');

// Bad - never create your own
$logger = new Log(...); // NO!
```

If you see this error in production code, someone violated Rule #1.

### Environment Variables Not Loading

**Problem:** `$_ENV['SITE_NAME']` is null

**Cause:**
- `.env` file doesn't exist
- Wrong path to `.env` file
- Syntax error in `.env` file

**Solution:**
```php
// Check environment file exists
if (!file_exists('.env')) {
    throw new Exception('.env file not found');
}
```

**Note:** You should never need to manually set `$envPath` or call bootstrap in production code. The console does this automatically.

### Autoloader Not Found

**Problem:** "vendor/autoload.php not found"

**Cause:** Composer dependencies not installed

**Solution:**
```bash
composer install
```

---

## Migration from Old Bootstrap Class

If you're upgrading from an older version that used `Core\Bootstrap` class:

**Old way:**
```php
use EICC\StaticForge\Core\Bootstrap;

$bootstrap = new Bootstrap();
$container = $bootstrap->initialize();
```

**New way:**
```php
// In bin/console.php ONLY
$container = require_once 'src/bootstrap.php';
```

**In your code:**
```php
// Receive container via dependency injection
class MyCommand {
    public function __construct(Container $container) {
        $this->container = $container;
        $this->logger = $container->get('logger');
    }
}
```

**Benefits:**
- Simpler procedural approach
- No class overhead
- Clearer dependency injection
- Easier testing with custom env files
- Single point of logger creation
- **One place to bootstrap:** Only `bin/console.php` in production

---

## Summary

- **One file:** `src/bootstrap.php` handles all initialization
- **One caller in production:** ONLY `bin/console.php` calls bootstrap
- **Tests are different:** Tests call bootstrap in setUp() with custom env files
- **Returns container:** Pass to commands and services via dependency injection
- **Singleton logger:** Only created in bootstrap, accessed via `$container->get('logger')`
- **Never bootstrap in:**
  - Commands
  - Features
  - Application code
  - Anywhere except `bin/console.php` and test setUp() methods

For implementation examples, see:
- `bin/console.php` - Console entry point
- `tests/Unit/UnitTestCase.php` - Unit test bootstrap
- `tests/Integration/IntegrationTestCase.php` - Integration test bootstrap
