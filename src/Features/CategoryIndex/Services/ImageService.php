<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\CategoryIndex\Services;

use EICC\Utils\Container;
use EICC\Utils\Log;
use Imagick;
use RuntimeException;

class ImageService
{
    private Log $logger;

    public function __construct(Log $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function extractHeroImage(string $html, array $metadata, string $sourcePath, Container $container): ?string
    {
        // 1. Check Frontmatter for explicit image reference
        // Check 'hero' key
        if (!empty($metadata['hero']) && is_string($metadata['hero'])) {
            return $metadata['hero'];
        }

        // Check 'social.image' key (nested)
        if (isset($metadata['social']['image']) && is_string($metadata['social']['image'])) {
            return $metadata['social']['image'];
        }

        // Check generic 'image' key as fallback
        if (!empty($metadata['image']) && is_string($metadata['image'])) {
            return $metadata['image'];
        }

        // 2. Fallback: Scrape first image from HTML content
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $src = $matches[1];

            if (preg_match('/^https?:\/\//i', $src)) {
                // Return remote URLs as is
                return $src;
            }

            $outputDir = $container->getVariable('OUTPUT_DIR');
            if ($outputDir) {
                $imagePath = $outputDir . $src;
                if (file_exists($imagePath)) {
                    return $this->generateThumbnail($imagePath, $sourcePath, $container);
                }
            }
            // If local file doesn't exist or OUTPUT_DIR not set, return src anyway?
            // Or maybe fail? Original code returned it if file exists.
            // If file doesn't exist, we probably shouldn't use it as a thumbnail.
            // But if it's just a broken link, maybe we accept it?
            // Let's stick closer to previous logic: attempt thumbnail, else return src.
             return $src;
        }

        // 3. No image found
        return null;
    }

    private function generateThumbnail(string $imagePath, string $contentPath, Container $container): string
    {
        $outputDir = $container->getVariable('OUTPUT_DIR');
        $thumbDir = $outputDir . '/images';

        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        $basename = pathinfo($contentPath, PATHINFO_FILENAME);
        $thumbPath = $thumbDir . '/' . $basename . '.jpg';
        $thumbUrl = '/images/' . $basename . '.jpg';

        if (file_exists($thumbPath) && filemtime($thumbPath) >= filemtime($imagePath)) {
            return $thumbUrl;
        }

        try {
            if (class_exists('Imagick')) {
                $imagick = new Imagick($imagePath);
                $imagick->thumbnailImage(300, 200, true);
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(85);
                $imagick->writeImage($thumbPath);
                $imagick->clear();
                $this->logger->log('INFO', "Generated thumbnail: {$thumbPath}");
                return $thumbUrl;
            }
        } catch (\Exception $e) {
            $this->logger->log('ERROR', "Thumbnail generation failed: " . $e->getMessage());
        }

        // Fallback if thumbnail generation fails: return original image URL relative to site root?
        // We need to convert absolute file path back to URL.
        // Assuming $imagePath starts with $outputDir.
        if (str_starts_with($imagePath, $outputDir)) {
             return substr($imagePath, strlen($outputDir));
        }

        return ''; // Should not happen if logic is correct
    }
}
