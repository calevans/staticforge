<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\RssFeed;

class RssDataProcessor
{
    /**
     * Sanitize category name for use in filesystem paths
     */
    public function sanitizeCategoryName(string $category): string
    {
        // Convert to lowercase
        $sanitized = strtolower($category);

        // Replace spaces and special characters with hyphens
        $sanitized = preg_replace('/[^a-z0-9]+/', '-', $sanitized);

        // Handle null return from preg_replace (regex failure)
        if ($sanitized === null) {
            $sanitized = 'category';
        }

        // Remove leading/trailing hyphens
        $sanitized = trim($sanitized, '-');

        if ($sanitized === '') {
            return 'category';
        }

        return $sanitized;
    }

    /**
     * Extract description from rendered content or metadata
     *
     * @param string $html Rendered HTML content
     * @param array<string, mixed> $metadata File metadata
     */
    public function extractDescription(string $html, array $metadata): string
    {
        // Check for description in metadata first
        if (!empty($metadata['description'])) {
            return $metadata['description'];
        }

        // Strip HTML tags and get first 200 characters
        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text); // Normalize whitespace

        // Handle null return from preg_replace
        if ($text === null) {
            $text = '';
        }

        $text = trim($text);

        if (strlen($text) > 200) {
            $text = substr($text, 0, 200);
            $lastSpace = strrpos($text, ' ');

            // Only truncate at space if one was found
            if ($lastSpace !== false) {
                $text = substr($text, 0, $lastSpace);
            }

            $text .= '...';
        }

        return $text;
    }

    /**
     * Get file date from metadata or filesystem
     *
     * @param array<string, mixed> $metadata File metadata
     * @param string $filePath Path to the file
     */
    public function getFileDate(array $metadata, string $filePath): string
    {
        // Check for published_date in metadata
        if (!empty($metadata['published_date'])) {
            return $metadata['published_date'];
        }

        // Check for date in metadata
        if (!empty($metadata['date'])) {
            return $metadata['date'];
        }

        // Fall back to source file modification time
        if (file_exists($filePath)) {
            $mtime = filemtime($filePath);
            if ($mtime !== false) {
                return date('Y-m-d', $mtime);
            }
        }

        return date('Y-m-d'); // Current date as last resort
    }

    /**
     * Get file URL relative to site root
     *
     * @param string $outputPath Full filesystem output path
     * @param string $outputDir Root output directory
     */
    public function getFileUrl(string $outputPath, string $outputDir): string
    {
        // Remove output directory from path to get relative URL
        $url = str_replace($outputDir, '', $outputPath);

        // Normalize path separators to forward slashes for URLs
        $url = str_replace(DIRECTORY_SEPARATOR, '/', $url);

        // Ensure URL starts with /
        if (!str_starts_with($url, '/')) {
            $url = '/' . $url;
        }

        return $url;
    }
}
