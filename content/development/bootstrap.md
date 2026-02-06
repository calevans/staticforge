---
title: 'The Ignition Sequence: Bootstrapping'
description: 'Understanding the StaticForge bootstrap process, dependency injection container, and application initialization.'
template: docs
menu: '4.1.2'
url: "https://calevans.com/staticforge/development/bootstrap.html"
og_image: "Rocket engine ignition sequence close up, digital sparks, code compiling in background, startup sequence, matrix style green binary rain, --ar 16:9"
---

# The Ignition Sequence: Bootstrapping

Before StaticForge can build a single page, it has to wake up, stretch, and get its tools ready. We call this the **Bootstrap Process**.

It's not magic; it's just a single file (`src/bootstrap.php`) that sets the stage for everything else.

---

## What Happens When You Hit Enter?

When you run a command like `site:render`, the system doesn't just start processing files immediately. First, it has to "pack its bags."

Here is the checklist it runs through:

1.  **Autoloading**: "Where are all my classes?" (Thanks, Composer!)
2.  **Environment Loading**: "What are the secrets?" (Reads `.env`)
3.  **Container Creation**: "I need a bag to hold my tools." (Creates the Dependency Injection Container)
4.  **Logger Setup**: "I need a notebook to write down what happens." (Sets up Logging)

Once this checklist is complete, the system hands you a fully loaded **Container** and says, "I'm ready."

---

## The Bootstrap File (`src/bootstrap.php`)

This file is unique. It's not a class; it's a procedural script. You give it an environment file, and it gives you back a Container.

### The Code Explained (Simplified)

```php
<?php
// src/bootstrap.php

// 1. Find and Load the Autoloader
// We check common paths (vendor/autoload.php) to find where Composer put the classes.

// 2. Load Environment Variables (.env)
// We look for .env in your current folder.
$dotenv = Dotenv\Dotenv::createUnsafeMutable(dirname($path), basename($path));
$dotenv->load();

// 3. Normalize Paths
// We turn relative paths like 'content/' into absolute paths like '/var/www/site/content/'
// so the system never gets lost.
$_ENV['SOURCE_DIR'] = $normalizePath($_ENV['SOURCE_DIR'] ?? 'content');

// 4. Create the Container
$container = new EICC\Utils\Container();

// 5. Load siteconfig.yaml
// This is your main configuration file (menus, site title).
// We load it and store it in the container for features to use.
$siteConfig = Yaml::parseFile('siteconfig.yaml');
$container->setVariable('site_config', $siteConfig);

// 6. Register Core Services
// We fire up the big engines:
$container->add(EventManager::class, new EventManager($container));
$container->add(AssetManager::class, new AssetManager());
// ... and others (FeatureManager, fileDiscovery, etc.)

// 7. Return the ready-to-use Container
return $container;
```

---

## Starting the Engine (Console Usage)

The most common way to start StaticForge is via the command line. The `bin/staticforge.php` script is the key.

It simply requires the bootstrap file and then hands the container to the application.

```php
#!/usr/bin/env php
<?php
// bin/staticforge.php

// 1. Run the Ignition Sequence
$container = require_once __DIR__ . '/../src/bootstrap.php';

// 2. Create the Console Application
$app = new Symfony\Component\Console\Application('StaticForge', '1.0.0');

// 3. Register Commands
// We register the core commands. Features will register their own commands later.
$app->add(new EICC\StaticForge\Commands\InitCommand());
// ...

// 4. Fire Console Init Event
$container->get(EICC\StaticForge\Core\EventManager::class)->fire('CONSOLE_INIT', ['application' => $app]);

// 5. Run the App
$app->run();
```

**Key Points:**
- Bootstrap returns container
- Features register their own commands via `CONSOLE_INIT` event
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
        $this->setContainerVariable('SITE_BASE_URL', 'http://test.com');
        $this->addToContainer('my_service', new MyService());
    }
}
```

---

## The Toolbox (Container Services)

Once the bootstrap is done, the **Container** is your toolbox. It holds everything you need.

### The Logger

We use a singleton logger so we don't have 50 different log files open.

```php
// Get the logger from the toolbox
$logger = $container->get('logger');

// Write to it
$logger->info('Engine started successfully.');
```

### Environment Variables

Need to know the site URL? It's in the container too.

```php
$siteUrl = $container->getVariable('SITE_BASE_URL');
```

---

## The Golden Rules of Bootstrapping

Follow these rules to keep your code clean and your sanity intact.

### Rule #1: Only Bootstrap Once
In production code, `src/bootstrap.php` should be called exactly **one time**: inside `bin/staticforge.php`.

*   **NEVER** call it inside a Command.
*   **NEVER** call it inside a Feature.
*   **NEVER** call it inside a Helper class.

### Rule #2: Pass the Container
If your class needs the container, ask for it in the constructor. Don't try to build a new one.

**✅ Do This:**
```php
class MyCommand {
    public function __construct(Container $container) {
        $this->container = $container;
    }
}
```

**❌ NOT This:**
```php
class MyCommand {
    public function __construct() {
        // BAD! This creates a whole new universe!
        $this->container = require 'src/bootstrap.php';
    }
}
```

### Rule #3: Don't Create Loggers
The bootstrap file already made a logger for you. Use it.

**✅ Do This:**
```php
$logger = $container->get('logger');
```

**❌ NOT This:**
```php
// BAD! Now you have two loggers fighting over the file.
$logger = new Log(...);
```

---

## Troubleshooting

### "Service 'logger' already exists"
**Cause:** You (or some code) called bootstrap twice.
**Fix:** Find the extra `require 'src/bootstrap.php'` and delete it.

### "vendor/autoload.php not found"
**Cause:** You haven't installed dependencies.
**Fix:** Run `lando composer install`.

---

[← Back to Documentation](index.html)
