---
title: 'Testing Your Code'
description: 'How to write unit and integration tests for StaticForge features.'
template: docs
menu: '4.2'
url: "https://calevans.com/staticforge/development/testing.html"
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
        // This loads .env, siteconfig, and all features.
        $container = $this->createContainer(__DIR__ . '/../../../../.env');

        // 2. Get the Feature Manager
        $featureManager = $container->get(FeatureManager::class);

        // 3. Assert your feature is running
        $this->assertTrue($featureManager->hasFeature('MyFeature'));
    }

    public function testFeatureDoesThing(): void
    {
        // Setup container
        $container = $this->createContainer(__DIR__ . '/../../../../.env');

        // Define some mock data
        $data = ['content' => 'Hello World'];

        // ... Trigger your event or call your service directly ...

        // Assert the result
        $this->assertArrayHasKey('modified_content', $data);
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

If you have complex logic (like a math calculation or string parser) that doesn't need the whole system, use a standard Unit Test.

Place these in `tests/Unit/Features/MyFeature/`.

```php
<?php

namespace EICC\StaticForge\Tests\Unit\Features\MyFeature;

use PHPUnit\Framework\TestCase;
use App\Features\MyFeature\Services\Calculator;

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
