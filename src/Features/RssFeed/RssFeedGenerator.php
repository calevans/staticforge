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
     * @param array<string, mixed> $categoryMetadata Category definition metadata
     */
    public function generateFeedXml(
        string $categoryName,
        string $categorySlug,
        array $files,
        string $siteBaseUrl,
        string $siteName,
        array $categoryMetadata = []
    ): string {
        // Ensure base URL has trailing slash
        $siteBaseUrl = rtrim($siteBaseUrl, '/') . '/';
        $isPodcast = ($categoryMetadata['rss_type'] ?? '') === 'podcast';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $namespaces = 'xmlns:atom="http://www.w3.org/2005/Atom"';
        $namespaces .= ' xmlns:content="http://purl.org/rss/1.0/modules/content/"';
        if ($isPodcast) {
            $namespaces .= ' xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"';
        }

        $xml .= '<rss version="2.0" ' . $namespaces . '>' . "\n";
        $xml .= '  <channel>' . "\n";
        $xml .= '    <title>' . $this->escapeXml($siteName . ' - ' . $categoryName) . '</title>' . "\n";
        $xml .= '    <link>' . $this->escapeXml($siteBaseUrl . $categorySlug . '/') . '</link>' . "\n";
        $xml .= '    <description>' . $this->escapeXml($categoryName . ' articles from ' . $siteName) .
                '</description>' . "\n";
        $xml .= '    <language>en-us</language>' . "\n";
        $xml .= '    <lastBuildDate>' . date('r') . '</lastBuildDate>' . "\n";
        $xml .= '    <atom:link href="' . $this->escapeXml($siteBaseUrl . $categorySlug . '/rss.xml') .
                '" rel="self" type="application/rss+xml" />' . "\n";

        if ($isPodcast) {
            $this->addPodcastChannelTags($xml, $categoryMetadata, $siteBaseUrl);
        }

        foreach ($files as $file) {
            $xml .= $this->buildRssItem($file, $siteBaseUrl, $isPodcast, $categoryMetadata);
        }

        $xml .= '  </channel>' . "\n";
        $xml .= '</rss>' . "\n";

        return $xml;
    }

    /**
     * Add iTunes podcast channel tags
     *
     * @param string $xml Reference to XML string
     * @param array<string, mixed> $metadata Category metadata
     * @param string $siteBaseUrl Base URL
     */
    private function addPodcastChannelTags(string &$xml, array $metadata, string $siteBaseUrl): void
    {
        // Copyright
        $copyright = $metadata['copyright'] ?? null;
        if (!$copyright && !empty($metadata['itunes_owner_name'])) {
            $copyright = '© ' . date('Y') . ' ' . $metadata['itunes_owner_name'];
        }
        if ($copyright) {
            $xml .= '    <copyright>' . $this->escapeXml($copyright) . '</copyright>' . "\n";
        }

        // iTunes Type (episodic/serial)
        $type = $metadata['itunes_type'] ?? 'episodic';
        $xml .= '    <itunes:type>' . $this->escapeXml($type) . '</itunes:type>' . "\n";

        if (!empty($metadata['itunes_author'])) {
            $xml .= '    <itunes:author>' . $this->escapeXml($metadata['itunes_author']) . '</itunes:author>' . "\n";
        }

        if (!empty($metadata['itunes_summary'])) {
            $xml .= '    <itunes:summary>' . $this->escapeXml($metadata['itunes_summary']) . '</itunes:summary>' . "\n";
        } elseif (!empty($metadata['description'])) {
            $xml .= '    <itunes:summary>' . $this->escapeXml($metadata['description']) . '</itunes:summary>' . "\n";
        }

        if (!empty($metadata['itunes_owner_name']) || !empty($metadata['itunes_owner_email'])) {
            $xml .= '    <itunes:owner>' . "\n";
            if (!empty($metadata['itunes_owner_name'])) {
                $xml .= '      <itunes:name>' . $this->escapeXml($metadata['itunes_owner_name']) . '</itunes:name>' . "\n";
            }
            if (!empty($metadata['itunes_owner_email'])) {
                $xml .= '      <itunes:email>' . $this->escapeXml($metadata['itunes_owner_email']) . '</itunes:email>' . "\n";
            }
            $xml .= '    </itunes:owner>' . "\n";
        }

        if (!empty($metadata['itunes_image'])) {
            $imageUrl = $metadata['itunes_image'];
            if (!preg_match('~^https?://~i', $imageUrl)) {
                $imageUrl = $siteBaseUrl . ltrim($imageUrl, '/');
            }
            $xml .= '    <itunes:image href="' . $this->escapeXml($imageUrl) . '" />' . "\n";
        }

        if (!empty($metadata['itunes_category'])) {
            $categories = $metadata['itunes_category'];
            if (!is_array($categories)) {
                $categories = [$categories];
            }

            foreach ($categories as $category) {
                // Check for subcategory (Format: "Category > Subcategory")
                if (str_contains($category, '>')) {
                    $parts = array_map('trim', explode('>', $category, 2));
                    $xml .= '    <itunes:category text="' . $this->escapeXml($parts[0]) . '">' . "\n";
                    $xml .= '      <itunes:category text="' . $this->escapeXml($parts[1]) . '" />' . "\n";
                    $xml .= '    </itunes:category>' . "\n";
                } else {
                    $xml .= '    <itunes:category text="' . $this->escapeXml($category) . '" />' . "\n";
                }
            }
        }

        if (isset($metadata['itunes_explicit'])) {
            $explicit = $metadata['itunes_explicit'] === 'true' || $metadata['itunes_explicit'] === true ? 'true' : 'false';
            $xml .= '    <itunes:explicit>' . $explicit . '</itunes:explicit>' . "\n";
        }
    }

    /**
     * Build a single RSS item
     *
     * @param array<string, mixed> $file File data
     * @param string $siteBaseUrl Base URL for the site
     * @param bool $isPodcast Whether this is a podcast feed
     * @param array<string, mixed> $categoryMetadata Category metadata
     */
    private function buildRssItem(array $file, string $siteBaseUrl, bool $isPodcast = false, array $categoryMetadata = []): string
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

        if (!empty($file['content'])) {
            $xml .= '      <content:encoded><![CDATA[' . $file['content'] . ']]></content:encoded>' . "\n";
        }

        // Add author if available in metadata
        if (!empty($file['metadata']['author'])) {
            $xml .= '      <author>' . $this->escapeXml($file['metadata']['author']) . '</author>' . "\n";
        }

        if ($isPodcast) {
            $this->addPodcastItemTags($xml, $file, $siteBaseUrl, $categoryMetadata);
        }

        $xml .= '    </item>' . "\n";

        return $xml;
    }

    /**
     * Add iTunes podcast item tags
     *
     * @param string $xml Reference to XML string
     * @param array<string, mixed> $file File data
     * @param string $siteBaseUrl Base URL
     * @param array<string, mixed> $categoryMetadata Category metadata
     */
    private function addPodcastItemTags(string &$xml, array $file, string $siteBaseUrl, array $categoryMetadata = []): void
    {
        $metadata = $file['metadata'] ?? [];

        if (!empty($file['enclosure'])) {
            $enc = $file['enclosure'];
            $url = $enc['url'];
            if (!preg_match('~^https?://~i', $url)) {
                $url = $siteBaseUrl . ltrim($url, '/');
            }

            $xml .= sprintf(
                '      <enclosure url="%s" length="%s" type="%s" />' . "\n",
                $this->escapeXml($url),
                $enc['length'],
                $enc['type']
            );
        }

        // iTunes Title
        if (!empty($metadata['itunes_title'])) {
            $xml .= '      <itunes:title>' . $this->escapeXml($metadata['itunes_title']) . '</itunes:title>' . "\n";
        }

        // iTunes Episode Type
        if (!empty($metadata['itunes_episode_type'])) {
            $xml .= '      <itunes:episodeType>' . $this->escapeXml($metadata['itunes_episode_type']) . '</itunes:episodeType>' . "\n";
        }

        // iTunes Author
        $author = $metadata['itunes_author'] ?? $metadata['author'] ?? $categoryMetadata['itunes_author'] ?? null;
        if ($author) {
            $xml .= '      <itunes:author>' . $this->escapeXml($author) . '</itunes:author>' . "\n";
        }

        // iTunes Subtitle
        if (!empty($metadata['itunes_subtitle'])) {
            $xml .= '      <itunes:subtitle>' . $this->escapeXml($metadata['itunes_subtitle']) . '</itunes:subtitle>' . "\n";
        }

        // iTunes Summary
        $summary = $metadata['itunes_summary'] ?? $file['description'] ?? null;
        if ($summary) {
            $xml .= '      <itunes:summary>' . $this->escapeXml($summary) . '</itunes:summary>' . "\n";
        }

        if (!empty($metadata['itunes_duration'])) {
            $xml .= '      <itunes:duration>' . $this->escapeXml((string)$metadata['itunes_duration']) . '</itunes:duration>' . "\n";
        }

        if (isset($metadata['itunes_explicit'])) {
            $explicit = $metadata['itunes_explicit'] === 'true' || $metadata['itunes_explicit'] === true ? 'true' : 'false';
            $xml .= '      <itunes:explicit>' . $explicit . '</itunes:explicit>' . "\n";
        }

        if (!empty($metadata['itunes_episode'])) {
            $xml .= '      <itunes:episode>' . (int)$metadata['itunes_episode'] . '</itunes:episode>' . "\n";
        }

        if (!empty($metadata['itunes_season'])) {
            $xml .= '      <itunes:season>' . (int)$metadata['itunes_season'] . '</itunes:season>' . "\n";
        }

        if (!empty($metadata['itunes_image'])) {
            $imageUrl = $metadata['itunes_image'];
            if (!preg_match('~^https?://~i', $imageUrl)) {
                $imageUrl = $siteBaseUrl . ltrim($imageUrl, '/');
            }
            $xml .= '      <itunes:image href="' . $this->escapeXml($imageUrl) . '" />' . "\n";
        }
    }

    /**
     * Escape XML special characters
     */
    private function escapeXml(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
