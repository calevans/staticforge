<?php

namespace EICC\StaticForge\Tests\Unit\Features;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Features\MenuBuilder\Feature;
use EICC\Utils\Container;

class MenuBuilderFeatureTest extends UnitTestCase
{
  private Feature $feature;


  protected function setUp(): void
  {
    parent::setUp();
    // Use bootstrapped container from parent::setUp()
    $this->feature = new Feature($this->container);
  }

  public function testParseMenuValueSinglePosition(): void
  {
    $reflection = new \ReflectionClass($this->feature);
    $method = $reflection->getMethod('parseMenuValue');
    $method->setAccessible(true);

    $result = $method->invoke($this->feature, '1.2');
    $this->assertEquals(['1.2'], $result);
  }

  public function testParseMenuValueMultiplePositionsNoQuotes(): void
  {
    $reflection = new \ReflectionClass($this->feature);
    $method = $reflection->getMethod('parseMenuValue');
    $method->setAccessible(true);

    $result = $method->invoke($this->feature, '1.2, 2.3, 3.4');
    $this->assertEquals(['1.2', '2.3', '3.4'], $result);
  }

  public function testParseMenuValueMultiplePositionsWithBrackets(): void
  {
    $reflection = new \ReflectionClass($this->feature);
    $method = $reflection->getMethod('parseMenuValue');
    $method->setAccessible(true);

    $result = $method->invoke($this->feature, '[1.2, 2.3]');
    $this->assertEquals(['1.2', '2.3'], $result);
  }

  public function testParseMenuValueMultiplePositionsWithQuotes(): void
  {
    $reflection = new \ReflectionClass($this->feature);
    $method = $reflection->getMethod('parseMenuValue');
    $method->setAccessible(true);

    $result = $method->invoke($this->feature, '["1.2", "2.3"]');
    $this->assertEquals(['1.2', '2.3'], $result);
  }

  public function testParseMenuValueSingleBracketed(): void
  {
    $reflection = new \ReflectionClass($this->feature);
    $method = $reflection->getMethod('parseMenuValue');
    $method->setAccessible(true);

    $result = $method->invoke($this->feature, '[1.2]');
    $this->assertEquals(['1.2'], $result);
  }

  public function testParseMenuValueWithExtraWhitespace(): void
  {
    $reflection = new \ReflectionClass($this->feature);
    $method = $reflection->getMethod('parseMenuValue');
    $method->setAccessible(true);

    $result = $method->invoke($this->feature, '  1.2  ,  2.3  ,  3.4  ');
    $this->assertEquals(['1.2', '2.3', '3.4'], $result);
  }

  public function testParseMenuValueEmptyString(): void
  {
    $reflection = new \ReflectionClass($this->feature);
    $method = $reflection->getMethod('parseMenuValue');
    $method->setAccessible(true);

    $result = $method->invoke($this->feature, '');
    $this->assertEquals([], $result);
  }

  public function testParseMenuValueWithEmptyItems(): void
  {
    $reflection = new \ReflectionClass($this->feature);
    $method = $reflection->getMethod('parseMenuValue');
    $method->setAccessible(true);

    $result = $method->invoke($this->feature, '1.2, , 3.4');
    $this->assertEquals(['1.2', '3.4'], $result);
  }

  public function testParseMenuValueMixedQuoteStyles(): void
  {
    $reflection = new \ReflectionClass($this->feature);
    $method = $reflection->getMethod('parseMenuValue');
    $method->setAccessible(true);

    $result = $method->invoke($this->feature, '"1.2", \'2.3\', 3.4');
    $this->assertEquals(['1.2', '2.3', '3.4'], $result);
  }

  public function testParseMenuValueComplexPositions(): void
  {
    $reflection = new \ReflectionClass($this->feature);
    $method = $reflection->getMethod('parseMenuValue');
    $method->setAccessible(true);

    $result = $method->invoke($this->feature, '1.2.3, 2.1, 3.5.0');
    $this->assertEquals(['1.2.3', '2.1', '3.5.0'], $result);
  }

  public function testSortMenuDataRecursive(): void
  {
    $reflection = new \ReflectionClass($this->feature);
    $method = $reflection->getMethod('sortMenuData');
    $method->setAccessible(true);

    // Create unsorted menu data with nested structure
    $menuData = [
        1 => [
            3 => [
                'title' => 'Section 3',
                2 => ['title' => 'Item 3.2'],
                1 => ['title' => 'Item 3.1'],
                10 => ['title' => 'Item 3.10']
            ],
            1 => ['title' => 'Item 1'],
            2 => ['title' => 'Item 2']
        ]
    ];

    $result = $method->invoke($this->feature, $menuData);

    // Verify top level sorting
    $this->assertEquals(['Item 1', 'Item 2', 'Section 3'], [
        $result[1][1]['title'],
        $result[1][2]['title'],
        $result[1][3]['title']
    ]);

    // Verify nested sorting
    $section3 = $result[1][3];
    // Keys should be sorted: 1, 2, 10
    // Note: array_keys returns keys in order
    $numericKeys = [];
    foreach ($section3 as $key => $value) {
        if (is_int($key)) {
            $numericKeys[] = $key;
        }
    }
    $this->assertEquals([1, 2, 10], $numericKeys);

    // Verify values match sorted keys
    $this->assertEquals('Item 3.1', $section3[1]['title']);
    $this->assertEquals('Item 3.2', $section3[2]['title']);
    $this->assertEquals('Item 3.10', $section3[10]['title']);
  }
}
