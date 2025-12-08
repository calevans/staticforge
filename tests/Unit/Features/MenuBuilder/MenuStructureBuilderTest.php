<?php

namespace EICC\StaticForge\Tests\Unit\Features\MenuBuilder;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Features\MenuBuilder\Services\MenuStructureBuilder;

class MenuStructureBuilderTest extends UnitTestCase
{
    private MenuStructureBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new MenuStructureBuilder();
    }

    public function testParseMenuValueSinglePosition(): void
    {
        $result = $this->builder->parseMenuValue('1.2');
        $this->assertEquals(['1.2'], $result);
    }

    public function testParseMenuValueMultiplePositionsNoQuotes(): void
    {
        $result = $this->builder->parseMenuValue('1.2, 2.3, 3.4');
        $this->assertEquals(['1.2', '2.3', '3.4'], $result);
    }

    public function testParseMenuValueMultiplePositionsWithBrackets(): void
    {
        $result = $this->builder->parseMenuValue('[1.2, 2.3]');
        $this->assertEquals(['1.2', '2.3'], $result);
    }

    public function testParseMenuValueMultiplePositionsWithQuotes(): void
    {
        $result = $this->builder->parseMenuValue('["1.2", "2.3"]');
        $this->assertEquals(['1.2', '2.3'], $result);
    }

    public function testParseMenuValueSingleBracketed(): void
    {
        $result = $this->builder->parseMenuValue('[1.2]');
        $this->assertEquals(['1.2'], $result);
    }

    public function testParseMenuValueWithExtraWhitespace(): void
    {
        $result = $this->builder->parseMenuValue('  1.2  ,  2.3  ,  3.4  ');
        $this->assertEquals(['1.2', '2.3', '3.4'], $result);
    }

    public function testParseMenuValueEmptyString(): void
    {
        $result = $this->builder->parseMenuValue('');
        $this->assertEquals([], $result);
    }

    public function testParseMenuValueWithEmptyItems(): void
    {
        $result = $this->builder->parseMenuValue('1.2, , 3.4');
        $this->assertEquals(['1.2', '3.4'], $result);
    }

    public function testParseMenuValueMixedQuoteStyles(): void
    {
        $result = $this->builder->parseMenuValue('"1.2", \'2.3\', 3.4');
        $this->assertEquals(['1.2', '2.3', '3.4'], $result);
    }

    public function testParseMenuValueComplexPositions(): void
    {
        $result = $this->builder->parseMenuValue('1.2.3, 2.1, 3.5.0');
        $this->assertEquals(['1.2.3', '2.1', '3.5.0'], $result);
    }

    public function testSortMenuDataRecursive(): void
    {
        // Create unsorted menu data with nested structure
        $menuData = [
            1 => [
                3 => ['title' => 'Item 3'],
                1 => ['title' => 'Item 1'],
                2 => [
                    'title' => 'Item 2',
                    5 => ['title' => 'Sub 5'],
                    1 => ['title' => 'Sub 1']
                ]
            ]
        ];

        $result = $this->builder->sortMenuData($menuData);

        // Check top level order
        $keys = array_keys($result[1]);
        // Filter out non-numeric keys if any (though in this test case we only have numeric keys for items)
        $numericKeys = array_filter($keys, 'is_int');
        $this->assertEquals([1, 2, 3], array_values($numericKeys));

        // Check nested order
        $subKeys = array_keys($result[1][2]);
        $numericSubKeys = array_filter($subKeys, 'is_int');
        $this->assertEquals([1, 5], array_values($numericSubKeys));
    }
}
