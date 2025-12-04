<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\RssFeed\Services;

use EICC\StaticForge\Features\RssFeed\Models\FeedChannel;
use EICC\StaticForge\Features\RssFeed\Models\FeedItem;

class FeedDataFactory
{
    /**
     * Sanitize category name for use in filesystem paths
     */
    public function sanitizeCategoryName(string $category): string
    {
        $sanitized = strtolower($category);
        $sanitized = preg_replace('/[^a-z0-9]+/', '-', $sanitized);

        if ($sanitized === null) {
            $sanitized = 'category';
        }

        $sanitized = trim($sanitized, '-');

        if ($sanitized === '') {
            return 'category';
        }

        return $sanitized;
    }

    /**
     * Extract description from rendered content or metadata
     */
    public function extractDescription(string $html, array $metadata): string
    {
        if (!empty($metadata['description'])) {
            return $metadata['description'];
        }

        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);

        if ($text === null) {
            $text = '';
        }

        $text = trim($text);

        if (strlen($text) > 200) {
            $text = substr($text, 0, 200);
            $lastSpace = strrpos($text, ' ');

            if ($lastSpace !== false) {
                $text = substr($text, 0, $lastSpace);
            }

            $text .= '...';
        }

        return $text;
    }

    public function getFileDate(array $metadata, string $filePath): string
    {
        if (!empty($metadata['published_date'])) {
            return $metadata['published_date'];
        }

        if (!empty($metadata['date'])) {
            return $metadata['date'];
        }

        if (file_exists($filePath)) {
            $mtime = filemtime($filePath);
            if ($mtime !== false) {
                return date('Y-m-d', $mtime);
            }
        }

        return date('Y-m-d');
    }

    public function getFileUrl(string $outputPath, string $outputDir): string
    {
        $url = str_replace($outputDir, '', $outputPath);
        $url = str_replace(DIRECTORY_SEPARATOR, '/', $url);

        if (!str_starts_with($url, '/')) {
            $url = '/' . $url;
        }

        return $url;
    }

    public function createChannel(
        string $categoryName,
        string $categorySlug,
        string $siteBaseUrl,
        string $siteName,
        array $categoryMetadata
    ): FeedChannel {
        $siteBaseUrl = rtrim($siteBaseUrl, '/') . '/';

        $copyright = $categoryMetadata['copyright'] ?? null;
        if (!$copyright && !empty($categoryMetadata['itunes_owner_name'])) {
            $copyright = '© ' . date('Y') . ' ' . $categoryMetadata['itunes_owner_name'];
        }

        return new FeedChannel(
            title: $siteName . ' - ' . $categoryName,
            link: $siteBaseUrl . $categorySlug . '/',
            description: $categoryName . ' articles from ' . $siteName,
            copyright: $copyright,
            metadata: $categoryMetadata
        );
    }

    public function createItem(
        array $file,
        string $siteBaseUrl,
        ?array $enclosure = null
    ): FeedItem {
        $siteBaseUrl = rtrim($siteBaseUrl, '/') . '/';
        $fullUrl = $siteBaseUrl . ltrim($file['url'], '/');

        // Resolve enclosure URL if present
        if ($enclosure) {
            $encUrl = $enclosure['url'];
            if (!preg_match('~^https?://~i', $encUrl)) {
                $enclosure['url'] = $siteBaseUrl . ltrim($encUrl, '/');
            }
        }

        // Resolve image URL in metadata if present
        $metadata = $file['metadata'] ?? [];
        if (!empty($metadata['itunes_image'])) {
             $imgUrl = $metadata['itunes_image'];
            if (!preg_match('~^https?://~i', $imgUrl)) {
                $metadata['itunes_image'] = $siteBaseUrl . ltrim($imgUrl, '/');
            }
        }

        return new FeedItem(
            title: $file['title'],
            link: $fullUrl,
            guid: $fullUrl,
            pubDate: date('r', strtotime($file['date'])),
            description: $file['description'],
            content: $file['content'],
            author: $file['metadata']['author'] ?? null,
            enclosure: $enclosure,
            metadata: $metadata
        );
    }
}
