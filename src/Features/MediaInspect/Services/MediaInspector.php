<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\MediaInspect\Services;

use getID3;
use RuntimeException;

class MediaInspector
{
    private getID3 $getID3;

    public function __construct()
    {
        $this->getID3 = new getID3();
    }

    /**
     * Inspect a media file and return metadata
     *
     * @param string $filePath Path to the media file
     * @return array{size: int, type: string, duration: string}
     * @throws RuntimeException If file cannot be analyzed
     */
    public function inspect(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $fileInfo = $this->getID3->analyze($filePath);

        if (isset($fileInfo['error'])) {
            throw new RuntimeException("Failed to analyze file: " . implode(', ', $fileInfo['error']));
        }

        $size = $fileInfo['filesize'] ?? 0;
        $mimeType = $fileInfo['mime_type'] ?? 'application/octet-stream';
        $duration = $this->formatDuration($fileInfo['playtime_seconds'] ?? 0);

        return [
            'size' => $size,
            'type' => $mimeType,
            'duration' => $duration,
        ];
    }

    /**
     * Format duration to iTunes compatible format (HH:MM:SS or MM:SS)
     */
    private function formatDuration(float $seconds): string
    {
        $seconds = (int) round($seconds);

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }
}
