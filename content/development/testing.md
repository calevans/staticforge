---
title: 'Testing Your Code'
description: 'How to write unit and integration tests for StaticForge features.'
template: docs
menu: '4.2'
og_image: "Scientist examining a glowing blue crystal with a magnifying glass, laboratory background, digital interface overlays, high detail, --ar 16:9"
---

# Testing Your Code

If it isn't tested, it doesn't exist.

StaticForge relies heavily on automated testing to ensure stability. When you build a new Feature, you should write tests to prove it works.

## Integration Tests

The easiest way to test a Feature is with an Integration Test. This spins up the full StaticForge container, allowing you to test your feature in a real environment.

### Basic Test Structure

Create a test file in `tests/Integration/Features/MyFeature/MyFeatureTest.php`.

```php
<?php

namespace EICC\StaticForge\Tests\Integration\Features\MyFeature;

use EICC\StaticForge\Tests\Integration\IntegrationTestCase;
use EICC\StaticForge\Core\FeatureManager;

class MyFeatureTest extends IntegrationTestCase
{
    public function testFeatureIsLoaded(): void
    {
        // 1. Boot the application
        // This loads .env.integration, siteconfig, and all features.
        $container = $this->createContainer(__DIR__ . '/../../../../.env.integration');

        // 2. Get the Feature Manager
        $featureManager = $container->get(FeatureManager::class);
        $featureManager->loadFeatures();

        // 3. Assert your feature is running
        $this->assertNotNull($featureManager->getFeature('MyFeature'));
    }

    public function testFeatureDoesThing(): void
    {
        // Setup container
        $container = $this->createContainer(__DIR__ . '/../../../../.env.integration');

        // ... Trigger your event, or call your service directly, and assert
        // the real, observable result (rendered content, a written file,
        // a container variable) ...
    }
}
```

### Running Your Test

You must use Lando to run tests.

```bash
# Run all tests (Good luck!)
lando phpunit

# Run just YOUR test (Much faster)
lando phpunit tests/Integration/Features/MyFeature/MyFeatureTest.php
```

## Unit Tests

### Pure Logic (No Container)

If you have complex logic (like a math calculation or string parser) that doesn't need the whole system, use a standard PHPUnit `TestCase` — no container, no bloat.

Place these in `tests/Unit/Features/MyFeature/Services/`.

```php
<?php

namespace EICC\StaticForge\Tests\Unit\Features\MyFeature\Services;

use PHPUnit\Framework\TestCase;
use EICC\StaticForge\Features\MyFeature\Services\Calculator;

class CalculatorTest extends TestCase
{
    public function testItAddsNumbers(): void
    {
        $start = 1;
        $end = 1;

        // No container, no bloat. Just pure logic.
        $result = $start + $end;

        $this->assertEquals(2, $result);
    }
}
```

### Testing a Feature's Event Handlers

To test `Feature.php` itself — its `#[EventListener]` handlers, its `register()` gating logic — extend `UnitTestCase` (which gives you a real, bootstrapped `Container` via `.env.testing`) and construct the Feature through `FeatureFactory`, which autowires its constructor exactly like the real `FeatureManager` does. Build the real typed event, call the handler directly, and assert on the event's mutated state.

```php
<?php

namespace EICC\StaticForge\Tests\Unit\Features\MyFeature;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureFactory;
use EICC\StaticForge\Features\MyFeature\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class FeatureTest extends UnitTestCase
{
    private Feature $feature;

    protected function setUp(): void
    {
        parent::setUp();

        $feature = (new FeatureFactory($this->container))->make(Feature::class);
        $this->assertInstanceOf(Feature::class, $feature);
        $this->feature = $feature;
        $this->feature->register(new EventManager());
    }

    public function testHandlerMutatesMetadata(): void
    {
        $event = new RenderEvent(
            name: 'PRE_RENDER',
            filePath: 'content/page.md',
            fileUrl: '',
            metadata: [],
        );

        $this->feature->handlePreRender($event);

        $this->assertArrayHasKey('my_computed_value', $event->metadata);
    }
}
```
