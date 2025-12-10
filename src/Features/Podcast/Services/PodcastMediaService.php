<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Podcast\Services;

use EICC\StaticForge\Features\Podcast\Services\MediaInspect\MediaInspector;
use RuntimeException;

class PodcastMediaService
{
    public function __construct(
        private MediaInspector $mediaInspector
    ) {
    }

    /**
     * Process podcast media file (copy local files, get metadata)
     *
     * @param array<string, mixed> $fileData File data including metadata
     * @param string $sourceDir Source content directory
     * @param string $outputDir Output directory
     * @return array{url: string, length: int, type: string}|null Enclosure data or null if no media
     */
    public function processMedia(array $fileData, string $sourceDir, string $outputDir): ?array
    {
        $metadata = $fileData['metadata'] ?? [];
        $audioFile = $metadata['audio_file'] ?? $metadata['video_file'] ?? null;

        if (!$audioFile) {
            return null;
        }

        // Check if remote URL
        if (preg_match('~^https?://~i', $audioFile)) {
            return [
                'url' => $audioFile,
                'length' => (int)($metadata['audio_size'] ?? 0),
                'type' => $metadata['audio_type'] ?? 'audio/mpeg',
            ];
        }

        // Handle local file
        $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . ltrim($audioFile, '/\\');

        if (!file_exists($sourcePath)) {
            return null;
        }

        // Create target directory
        $targetDir = $outputDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'media';
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $targetDir));
            }
        }

        $fileName = basename($audioFile);
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

        // Copy file if it doesn't exist or is newer
        if (!file_exists($targetPath) || filemtime($sourcePath) > filemtime($targetPath)) {
            copy($sourcePath, $targetPath);
        }

        // Use MediaInspector for metadata
        try {
            $info = $this->mediaInspector->inspect($targetPath);
            $length = $info['size'];
            $mimeType = $info['type'];
            // Duration is available in $info['duration'] if needed for itunes:duration
        } catch (\Exception $e) {
            // Fallback if inspection fails
            $length = filesize($targetPath);
            $mimeType = mime_content_type($targetPath) ?: 'audio/mpeg';
        }

        return [
            'url' => '/assets/media/' . $fileName,
            'length' => $length,
            'type' => $mimeType,
        ];
    }
}
