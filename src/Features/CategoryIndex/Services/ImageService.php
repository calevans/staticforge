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

    public function extractHeroImage(string $html, string $sourcePath, Container $container): string
    {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $src = $matches[1];

            if (preg_match('/^https?:\/\//i', $src)) {
                // TODO: Implement download logic if needed, for now return as is or placeholder
                // The original code had download logic, let's keep it simple for now or implement if critical
                return $src; 
            }

            $outputDir = $container->getVariable('OUTPUT_DIR');
            if (!$outputDir) {
                throw new RuntimeException('OUTPUT_DIR not set');
            }

            $imagePath = $outputDir . $src;
            if (file_exists($imagePath)) {
                return $this->generateThumbnail($imagePath, $sourcePath, $container);
            }
        }

        return $this->getPlaceholder($container);
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

        return $this->getPlaceholder($container);
    }

    private function getPlaceholder(Container $container): string
    {
        // Simplified placeholder logic
        return '/assets/images/placeholder.jpg';
    }
}
