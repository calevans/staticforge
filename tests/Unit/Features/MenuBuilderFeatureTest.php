<?php

namespace EICC\StaticForge\Tests\Unit\Features;

use PHPUnit\Framework\TestCase;
use EICC\StaticForge\Features\MenuBuilder\Feature;
use EICC\Utils\Container;

class MenuBuilderFeatureTest extends TestCase
{
  private Feature $feature;
  private Container $container;

  protected function setUp(): void
  {
    $this->container = new Container();
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
}
