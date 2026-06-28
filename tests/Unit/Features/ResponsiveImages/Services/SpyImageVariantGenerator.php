<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\ResponsiveImages\Services;

use EICC\StaticForge\Features\ResponsiveImages\Services\ImageVariantGenerator;

/**
 * Test double that counts calls to generateVariants() without invoking
 * real Imagick logic, so call-count assertions can be made on
 * HtmlImageRewriterService's cheap bail-out paths.
 */
final class SpyImageVariantGenerator extends ImageVariantGenerator
{
    public int $calls = 0;

    /**
     * @return list<array{width: int, path: string, url: string, format: string}>
     */
    public function generateVariants(string $sourcePath, string $outputBaseDir, string $urlBaseDir): array
    {
        $this->calls++;
        return [];
    }
}
