<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\RssFeed;

class RssFeedGenerator
{
    /**
     * Build RSS XML content
     *
     * @param string $categoryName Category display name
     * @param string $categorySlug Category URL slug
     * @param array<int, array<string, mixed>> $files Files to include in feed
     * @param string $siteBaseUrl Base URL for the site
     * @param string $siteName Site name
     */
    public function generateFeedXml(
        string $categoryName,
        string $categorySlug,
        array $files,
        string $siteBaseUrl,
        string $siteName
    ): string {
        // Ensure base URL has trailing slash
        $siteBaseUrl = rtrim($siteBaseUrl, '/') . '/';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= '  <channel>' . "\n";
        $xml .= '    <title>' . $this->escapeXml($siteName . ' - ' . $categoryName) . '</title>' . "\n";
        $xml .= '    <link>' . $this->escapeXml($siteBaseUrl . $categorySlug . '/') . '</link>' . "\n";
        $xml .= '    <description>' . $this->escapeXml($categoryName . ' articles from ' . $siteName) . '</description>' . "\n";
        $xml .= '    <language>en-us</language>' . "\n";
        $xml .= '    <lastBuildDate>' . date('r') . '</lastBuildDate>' . "\n";
        $xml .= '    <atom:link href="' . $this->escapeXml($siteBaseUrl . $categorySlug . '/rss.xml') . '" rel="self" type="application/rss+xml" />' . "\n";

        foreach ($files as $file) {
            $xml .= $this->buildRssItem($file, $siteBaseUrl);
        }

        $xml .= '  </channel>' . "\n";
        $xml .= '</rss>' . "\n";

        return $xml;
    }

    /**
     * Build a single RSS item
     *
     * @param array<string, mixed> $file File data
     * @param string $siteBaseUrl Base URL for the site
     */
    private function buildRssItem(array $file, string $siteBaseUrl): string
    {
        // Ensure base URL has trailing slash
        $siteBaseUrl = rtrim($siteBaseUrl, '/') . '/';

        // Build full URL - file URL is already relative to site root
        $fullUrl = $siteBaseUrl . ltrim($file['url'], '/');

        $xml = '    <item>' . "\n";
        $xml .= '      <title>' . $this->escapeXml($file['title']) . '</title>' . "\n";
        $xml .= '      <link>' . $this->escapeXml($fullUrl) . '</link>' . "\n";
        $xml .= '      <guid>' . $this->escapeXml($fullUrl) . '</guid>' . "\n";
        $xml .= '      <pubDate>' . date('r', strtotime($file['date'])) . '</pubDate>' . "\n";

        if (!empty($file['description'])) {
            $xml .= '      <description>' . $this->escapeXml($file['description']) . '</description>' . "\n";
        }

        // Add author if available in metadata
        if (!empty($file['metadata']['author'])) {
            $xml .= '      <author>' . $this->escapeXml($file['metadata']['author']) . '</author>' . "\n";
        }

        $xml .= '    </item>' . "\n";

        return $xml;
    }

    /**
     * Escape XML special characters
     */
    private function escapeXml(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
