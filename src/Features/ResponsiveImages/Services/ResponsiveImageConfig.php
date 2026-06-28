<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\ResponsiveImages\Services;

/**
 * Value object holding parsed/validated responsive_images configuration.
 * Parsed once at Feature registration time to avoid re-parsing on every page.
 */
final class ResponsiveImageConfig
{
    /**
     * @var int[]
     */
    public readonly array $widths;

    public readonly int $quality;

    /**
     * @param int[] $widths
     */
    public function __construct(
        public readonly bool $enabled,
        array $widths,
        public readonly bool $webp,
        int $quality,
        public readonly string $outputDir,
        public readonly int $minSourceWidth,
    ) {
        $this->widths = $widths;
        $this->quality = max(1, min(100, $quality));
    }

    /**
     * @param array<string, mixed> $siteConfig
     */
    public static function fromSiteConfig(array $siteConfig): self
    {
        $cfg = $siteConfig['responsive_images'] ?? [];
        if (!is_array($cfg)) {
            $cfg = [];
        }

        $enabled = $cfg['enabled'] ?? false;
        $enabled = is_bool($enabled) ? $enabled : (bool) $enabled;

        $widths = self::parseWidths($cfg['widths'] ?? null);

        $webp = $cfg['webp'] ?? true;
        $webp = is_bool($webp) ? $webp : (bool) $webp;

        $quality = $cfg['quality'] ?? 82;
        $quality = is_numeric($quality) ? (int) $quality : 82;

        $outputDir = $cfg['output_dir'] ?? 'assets/images/responsive';
        $outputDir = is_string($outputDir) && $outputDir !== '' ? trim($outputDir, '/') : 'assets/images/responsive';

        $minSourceWidth = $cfg['min_source_width'] ?? 400;
        $minSourceWidth = is_numeric($minSourceWidth) && (int) $minSourceWidth > 0 ? (int) $minSourceWidth : 400;

        return new self(
            enabled: $enabled,
            widths: $widths,
            webp: $webp,
            quality: $quality,
            outputDir: $outputDir,
            minSourceWidth: $minSourceWidth,
        );
    }

    /**
     * @return int[]
     */
    private static function parseWidths(mixed $widths): array
    {
        $default = [400, 800, 1200];

        if (!is_array($widths) || empty($widths)) {
            return $default;
        }

        $valid = [];
        foreach ($widths as $width) {
            if (is_numeric($width) && (int) $width > 0) {
                $valid[] = (int) $width;
            }
        }

        if (empty($valid)) {
            return $default;
        }

        $valid = array_values(array_unique($valid));
        sort($valid);

        return $valid;
    }
}
