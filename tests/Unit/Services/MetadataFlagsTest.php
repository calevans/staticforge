<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services;

use EICC\StaticForge\Services\MetadataFlags;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MetadataFlagsTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: bool, 2: bool}>
     */
    public static function booleanProvider(): array
    {
        // value, expected isTrue, expected isFalse
        return [
            'bool true' => [true, true, false],
            'bool false' => [false, false, true],
            'string true' => ['true', true, false],
            'string false' => ['false', false, true],
            'string TRUE mixed case' => [' True ', true, false],
            'string yes' => ['yes', true, false],
            'string no' => ['no', false, true],
            'string on' => ['on', true, false],
            'string off' => ['off', false, true],
            'int 1' => [1, true, false],
            'int 0' => [0, false, true],
            'string 1' => ['1', true, false],
            'string 0' => ['0', false, true],
            'null' => [null, false, false],
            'empty string' => ['', false, true],
            'empty array' => [[], false, false],
            'non-empty array' => [['true'], false, false],
            'garbage string' => ['garbage', false, false],
            'object' => [new \stdClass(), false, false],
        ];
    }

    #[DataProvider('booleanProvider')]
    public function testIsTrueAndIsFalseParseValues(mixed $value, bool $isTrue, bool $isFalse): void
    {
        $this->assertSame($isTrue, MetadataFlags::isTrue($value));
        $this->assertSame($isFalse, MetadataFlags::isFalse($value));
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function robotsProvider(): array
    {
        return [
            'string no' => ['no', true],
            'padded upper NO' => [' NO ', true],
            'bool false (YAML bare no)' => [false, true],
            'string false is not blocked' => ['false', false],
            'string yes' => ['yes', false],
            'empty string' => ['', false],
            'null' => [null, false],
            'bool true' => [true, false],
            'int 0' => [0, false],
            'string 0' => ['0', false],
            'string off' => ['off', false],
            'array' => [['no'], false],
            'garbage' => ['garbage', false],
        ];
    }

    #[DataProvider('robotsProvider')]
    public function testRobotsBlockedPinsExactBehavior(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, MetadataFlags::robotsBlocked($value));
    }
}
