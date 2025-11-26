<?php

namespace EICC\StaticForge\Features\CategoryIndex;

use EICC\Utils\Container;
use EICC\Utils\Log;

class ImageProcessor
{
    private Log $logger;

    public function __construct(Log $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Extract hero image from rendered HTML content
     */
    public function extractHeroImageFromHtml(string $html, string $sourcePath, Container $container): string
    {
        // Extract first image tag
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $imageSrc = $matches[1];

            // Check if it's an external URL
            if (preg_match('/^https?:\/\//i', $imageSrc)) {
                // Download and cache external image
                return $this->downloadAndCacheImage($imageSrc, $sourcePath, $container);
            }

            // Convert relative URL to filesystem path
            $outputDir = $container->getVariable('OUTPUT_DIR');
            if (!$outputDir) {
                throw new \RuntimeException('OUTPUT_DIR not set in container');
            }
            $imagePath = $outputDir . $imageSrc;

            if (file_exists($imagePath)) {
                // Generate thumbnail
                return $this->generateThumbnail($imagePath, $sourcePath, $container);
            }
        }

        return $this->getPlaceholderImage($container);
    }

    /**
     * Generate thumbnail from source image
     */
    private function generateThumbnail(string $sourcePath, string $contentPath, Container $container): string
    {
        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set in container');
        }
        $thumbnailDir = $outputDir . '/images';

        // Create images directory if it doesn't exist
        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }

        // Generate thumbnail filename based on content file
        $basename = pathinfo($contentPath, PATHINFO_FILENAME);
        $thumbnailPath = $thumbnailDir . '/' . $basename . '.jpg';
        $thumbnailUrl = '/images/' . $basename . '.jpg';

        // Check if thumbnail already exists and is newer than source
        if (file_exists($thumbnailPath) && filemtime($thumbnailPath) >= filemtime($sourcePath)) {
            return $thumbnailUrl;
        }

        // Use ImageMagick to resize
        try {
            $imagick = new \Imagick($sourcePath);
            $imagick->thumbnailImage(300, 200, true); // 300x200, best fit
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(85);
            $imagick->writeImage($thumbnailPath);
            $imagick->clear();

            $this->logger->log('INFO', "Generated thumbnail: {$thumbnailPath}");
            return $thumbnailUrl;
        } catch (\Exception $e) {
            $this->logger->log('ERROR', "Failed to generate thumbnail: " . $e->getMessage());
            return $this->getPlaceholderImage($container);
        }
    }

    /**
     * Get or generate placeholder image
     */
    private function getPlaceholderImage(Container $container): string
    {
        $theme = $container->getVariable('TEMPLATE') ?? 'terminal';
        $templateDir = $container->getVariable('TEMPLATE_DIR');
        if (!$templateDir) {
            throw new \RuntimeException('TEMPLATE_DIR not set in container');
        }
        $placeholderPath = $templateDir . '/' . $theme . '/placeholder.jpg';

        // Check if placeholder exists
        if (file_exists($placeholderPath)) {
            return '/templates/' . $theme . '/placeholder.jpg';
        }

        // Generate placeholder
        try {
            $imagick = new \Imagick();
            $imagick->newImage(300, 200, new \ImagickPixel('#808080')); // Gray background
            $imagick->setImageFormat('jpeg');

            // Ensure directory exists
            $dir = dirname($placeholderPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $imagick->writeImage($placeholderPath);
            $imagick->clear();

            $this->logger->log('INFO', "Generated placeholder image: {$placeholderPath}");
            return '/templates/' . $theme . '/placeholder.jpg';
        } catch (\Exception $e) {
            $this->logger->log('ERROR', "Failed to generate placeholder: " . $e->getMessage());
            return ''; // Return empty string if all fails
        }
    }

    /**
     * Download external image and cache it locally
     */
    private function downloadAndCacheImage(string $url, string $sourcePath, Container $container): string
    {
        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set in container');
        }
        $cacheDir = $outputDir . '/images/cache';

        // Create cache directory if it doesn't exist
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Generate cache filename from URL hash
        $urlHash = md5($url);
        $basename = pathinfo($sourcePath, PATHINFO_FILENAME);
        $cachedImagePath = $cacheDir . '/' . $basename . '_' . $urlHash . '.jpg';
        $cachedImageUrl = '/images/cache/' . $basename . '_' . $urlHash . '.jpg';

        // Check if cached version exists
        if (file_exists($cachedImagePath)) {
            $this->logger->log('DEBUG', "Using cached image: {$cachedImagePath}");
            return $cachedImageUrl;
        }

        // Download the image
        try {
            $this->logger->log('INFO', "Downloading external image: {$url}");

            $imageData = @file_get_contents($url);
            if ($imageData === false) {
                throw new \Exception("Failed to download image from URL");
            }

            // Save to temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'img_');
            file_put_contents($tempFile, $imageData);

            // Use ImageMagick to resize and convert to thumbnail
            $imagick = new \Imagick($tempFile);
            $imagick->thumbnailImage(300, 200, true); // 300x200, best fit
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(85);
            $imagick->writeImage($cachedImagePath);
            $imagick->clear();

            // Clean up temp file
            unlink($tempFile);

            $this->logger->log('INFO', "Cached external image: {$cachedImagePath}");
            return $cachedImageUrl;
        } catch (\Exception $e) {
            $this->logger->log('ERROR', "Failed to download/cache image from {$url}: " . $e->getMessage());
            return $this->getPlaceholderImage($container);
        }
    }
}
