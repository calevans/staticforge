<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\ResponsiveImages\Services;

use EICC\Utils\Log;
use Imagick;

/**
 * Owns all Imagick interaction for the ResponsiveImages feature.
 * Mirrors CategoryIndex\Services\ImageService patterns but is self-contained
 * (no shared class) so this feature can be extracted standalone.
 *
 * @phpstan-type ImageVariant array{width: int, path: string, url: string, format: string}
 */
class ImageVariantGenerator
{
    public function __construct(
        private readonly Log $logger,
        private readonly ResponsiveImageConfig $config,
    ) {
    }

    /**
     * Generate (or reuse cached) variants for one source image.
     * Returns [] if the source is missing/corrupt/unsupported — caller treats
     * empty array as "leave the <img> tag alone".
     *
     * @return list<array{width: int, path: string, url: string, format: string}>
     */
    public function generateVariants(string $sourcePath, string $outputBaseDir, string $urlBaseDir): array
    {
        if (!class_exists('Imagick') || !is_readable($sourcePath)) {
            $this->logger->log('WARNING', "ResponsiveImages: source unreadable, skipping: {$sourcePath}");
            return [];
        }

        try {
            $probe = new Imagick($sourcePath);
            $sourceWidth = $probe->getImageWidth();
            $probe->clear();
        } catch (\Exception $e) {
            $this->logger->log(
                'WARNING',
                "ResponsiveImages: failed to read image dimensions for {$sourcePath}: " . $e->getMessage()
            );
            return [];
        }

        if ($sourceWidth < $this->config->minSourceWidth) {
            return [];
        }

        $variants = [];
        foreach ($this->config->widths as $width) {
            if ($width >= $sourceWidth) {
                continue;
            }

            $original = $this->renderVariant($sourcePath, $width, 'original', $outputBaseDir, $urlBaseDir);
            if ($original !== null) {
                $variants[] = $original;
            }

            if ($this->config->webp) {
                $webp = $this->renderVariant($sourcePath, $width, 'webp', $outputBaseDir, $urlBaseDir);
                if ($webp !== null) {
                    $variants[] = $webp;
                }
            }
        }

        return $variants;
    }

    /**
     * @return array{width: int, path: string, url: string, format: string}|null
     */
    private function renderVariant(
        string $sourcePath,
        int $width,
        string $mode,
        string $outputBaseDir,
        string $urlBaseDir
    ): ?array {
        $hash = substr(md5($sourcePath), 0, 8);
        $basename = pathinfo($sourcePath, PATHINFO_FILENAME) . "-{$hash}-{$width}w";
        $ext = $mode === 'webp' ? 'webp' : pathinfo($sourcePath, PATHINFO_EXTENSION);
        $outPath = $outputBaseDir . '/' . $basename . '.' . $ext;
        $outUrl = $urlBaseDir . '/' . $basename . '.' . $ext;

        if (file_exists($outPath) && filemtime($outPath) >= filemtime($sourcePath)) {
            return ['width' => $width, 'path' => $outPath, 'url' => $outUrl, 'format' => $mode];
        }

        if (!is_dir($outputBaseDir)) {
            mkdir($outputBaseDir, 0755, true);
        }

        try {
            $imagick = new Imagick($sourcePath);
            $imagick->thumbnailImage($width, 0);
            if ($mode === 'webp') {
                $imagick->setImageFormat('webp');
            }
            $imagick->setImageCompressionQuality($this->config->quality);
            $imagick->writeImage($outPath);
            $imagick->clear();
            return ['width' => $width, 'path' => $outPath, 'url' => $outUrl, 'format' => $mode];
        } catch (\Exception $e) {
            $this->logger->log(
                'WARNING',
                "ResponsiveImages: variant generation failed for {$sourcePath} @ {$width}w ({$mode}): "
                    . $e->getMessage()
            );
            return null;
        }
    }
}
