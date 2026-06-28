<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\ResponsiveImages\Services;

use EICC\StaticForge\Features\ResponsiveImages\Services\ResponsiveImageConfig;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class ResponsiveImageConfigTest extends UnitTestCase
{
    public function testDefaultsAppliedWhenKeyAbsentEntirely(): void
    {
        $config = ResponsiveImageConfig::fromSiteConfig([]);

        $this->assertFalse($config->enabled);
        $this->assertSame([400, 800, 1200], $config->widths);
        $this->assertTrue($config->webp);
        $this->assertSame(82, $config->quality);
        $this->assertSame('assets/images/responsive', $config->outputDir);
        $this->assertSame(400, $config->minSourceWidth);
    }

    public function testEnabledHonoredWhenExplicitlySetTrue(): void
    {
        $config = ResponsiveImageConfig::fromSiteConfig(['responsive_images' => ['enabled' => true]]);

        $this->assertTrue($config->enabled);
    }

    public function testEnabledHonoredWhenExplicitlySetFalse(): void
    {
        $config = ResponsiveImageConfig::fromSiteConfig(['responsive_images' => ['enabled' => false]]);

        $this->assertFalse($config->enabled);
    }

    public function testInvalidWidthsNonArrayFallsBackToDefault(): void
    {
        $config = ResponsiveImageConfig::fromSiteConfig(['responsive_images' => ['widths' => 'not-an-array']]);

        $this->assertSame([400, 800, 1200], $config->widths);
    }

    public function testInvalidWidthsNegativeNumbersFallBackToDefault(): void
    {
        $config = ResponsiveImageConfig::fromSiteConfig(['responsive_images' => ['widths' => [-1, -200]]]);

        $this->assertSame([400, 800, 1200], $config->widths);
    }

    public function testInvalidWidthsNonNumericFallBackToDefault(): void
    {
        $config = ResponsiveImageConfig::fromSiteConfig(['responsive_images' => ['widths' => ['foo', 'bar']]]);

        $this->assertSame([400, 800, 1200], $config->widths);
    }

    public function testWidthsAreDedupedAndSortedAscending(): void
    {
        $config = ResponsiveImageConfig::fromSiteConfig(['responsive_images' => ['widths' => [800, 400, 800, 1200]]]);

        $this->assertSame([400, 800, 1200], $config->widths);
    }

    public function testMixedValidAndInvalidWidthsKeepsOnlyValid(): void
    {
        $config = ResponsiveImageConfig::fromSiteConfig(['responsive_images' => ['widths' => [400, -1, 'x', 800]]]);

        $this->assertSame([400, 800], $config->widths);
    }

    public function testQualityClampedToUpperBound(): void
    {
        $config = ResponsiveImageConfig::fromSiteConfig(['responsive_images' => ['quality' => 250]]);

        $this->assertSame(100, $config->quality);
    }

    public function testQualityClampedToLowerBound(): void
    {
        $config = ResponsiveImageConfig::fromSiteConfig(['responsive_images' => ['quality' => -10]]);

        $this->assertSame(1, $config->quality);
    }

    public function testQualityNonNumericFallsBackToDefault(): void
    {
        $config = ResponsiveImageConfig::fromSiteConfig(['responsive_images' => ['quality' => 'high']]);

        $this->assertSame(82, $config->quality);
    }

    public function testWebpDisabled(): void
    {
        $config = ResponsiveImageConfig::fromSiteConfig(['responsive_images' => ['webp' => false]]);

        $this->assertFalse($config->webp);
    }

    public function testOutputDirTrimsSlashes(): void
    {
        $config = ResponsiveImageConfig::fromSiteConfig(['responsive_images' => ['output_dir' => '/custom/dir/']]);

        $this->assertSame('custom/dir', $config->outputDir);
    }

    public function testMinSourceWidthInvalidFallsBackToDefault(): void
    {
        $config = ResponsiveImageConfig::fromSiteConfig(['responsive_images' => ['min_source_width' => -50]]);

        $this->assertSame(400, $config->minSourceWidth);
    }

    public function testResponsiveImagesKeyNotArrayFallsBackToAllDefaults(): void
    {
        $config = ResponsiveImageConfig::fromSiteConfig(['responsive_images' => 'oops']);

        $this->assertFalse($config->enabled);
        $this->assertSame([400, 800, 1200], $config->widths);
    }
}
