<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services\Upload;

use EICC\StaticForge\Services\Upload\UploadSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UploadSettingsTest extends TestCase
{
    /**
     * @return array<string, array{mixed, ?int}>
     */
    public static function validBlocks(): array
    {
        return [
            'block absent' => [null, null],
            'empty mapping' => [[], null],
            'max_delete unset' => [['strategy' => 'in_place'], null],
            'max_delete null' => [['max_delete' => null], null],
            'max_delete zero' => [['max_delete' => 0], 0],
            'max_delete five' => [['max_delete' => 5], 5],
            'strategy in_place' => [['max_delete' => 3, 'strategy' => 'in_place'], 3],
            'strategy null' => [['strategy' => null], null],
            'unknown keys ignored' => [['keep_releases' => 3, 'anything' => 'x'], null],
        ];
    }

    #[DataProvider('validBlocks')]
    public function testValidBlockHasNoErrorsAndExposesMaxDelete(mixed $raw, ?int $expected): void
    {
        $settings = UploadSettings::fromConfig($raw);

        $this->assertSame([], $settings->errors);
        $this->assertSame($expected, $settings->maxDelete);
        $this->assertSame(UploadSettings::STRATEGY_IN_PLACE, $settings->strategy);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidMaxDelete(): array
    {
        return [
            'non numeric string' => ['abc'],
            'quoted integer' => ['5'],
            'negative' => [-1],
            'float' => [2.5],
            'bool' => [true],
            'empty list' => [[]],
        ];
    }

    #[DataProvider('invalidMaxDelete')]
    public function testInvalidMaxDeleteIsReportedAndLeftUnset(mixed $value): void
    {
        $settings = UploadSettings::fromConfig(['max_delete' => $value]);

        $this->assertSame(['upload.max_delete must be null or a non-negative integer.'], $settings->errors);
        $this->assertNull($settings->maxDelete);
    }

    public function testAtomicStrategyIsReportedAsUnavailable(): void
    {
        $settings = UploadSettings::fromConfig(['strategy' => 'atomic']);

        $this->assertCount(1, $settings->errors);
        $this->assertStringContainsString('not available in this version', $settings->errors[0]);
    }

    public function testUnknownStrategyIsReported(): void
    {
        $settings = UploadSettings::fromConfig(['strategy' => 'x']);

        $this->assertCount(1, $settings->errors);
        $this->assertStringContainsString('must be in_place', $settings->errors[0]);
        $this->assertStringNotContainsString('not available', $settings->errors[0]);
    }

    public function testEveryProblemInABlockIsReported(): void
    {
        $settings = UploadSettings::fromConfig(['max_delete' => -1, 'strategy' => 'atomic']);

        $this->assertCount(2, $settings->errors);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nonMappings(): array
    {
        return [
            'string' => ['on'],
            'bool' => [true],
            'int' => [3],
        ];
    }

    #[DataProvider('nonMappings')]
    public function testUploadThatIsNotAMappingIsReported(mixed $raw): void
    {
        $settings = UploadSettings::fromConfig($raw);

        $this->assertCount(1, $settings->errors);
        $this->assertStringContainsString("'upload' in siteconfig.yaml must be a mapping", $settings->errors[0]);
        $this->assertNull($settings->maxDelete);
    }
}
