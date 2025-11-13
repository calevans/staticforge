<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\RssFeed;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * RSS Feed Feature - generates category-based RSS feed files
 * Listens to POST_RENDER to collect category files, then POST_LOOP to generate feeds
 */
class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'RssFeed';
    protected Log $logger;

    /**
     * Files organized by category for RSS feeds
     * @var array<string, array{display_name: string, files: array<int, array<string, mixed>>}>
     */
    private array $categoryFiles = [];

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_RENDER' => ['method' => 'collectCategoryFiles', 'priority' => 110],
        'POST_LOOP' => ['method' => 'generateRssFeeds', 'priority' => 90]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);

        // Get logger from container
        $this->logger = $container->get('logger');

        $this->logger->log('INFO', 'RssFeed Feature registered');
    }

    /**
     * Collect files that have categories during POST_RENDER
     *
     * Called dynamically by EventManager when POST_RENDER event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function collectCategoryFiles(Container $container, array $parameters): array
    {
        $metadata = $parameters['metadata'] ?? [];
        $category = $metadata['category'] ?? null;

        if (!$category) {
            return $parameters;
        }

        $outputPath = $parameters['output_path'] ?? null;
        $filePath = $parameters['file_path'] ?? null;
        $renderedContent = $parameters['rendered_content'] ?? '';
        $title = $metadata['title'] ?? 'Untitled';

        if (!$outputPath || !$filePath) {
            return $parameters;
        }

        // Sanitize category name to match filesystem
        $sanitizedCategory = $this->sanitizeCategoryName($category);

        if (!isset($this->categoryFiles[$sanitizedCategory])) {
            $this->categoryFiles[$sanitizedCategory] = [
                'display_name' => $category,
                'files' => []
            ];
        }

        // Extract description from rendered content
        $description = $this->extractDescription($renderedContent, $metadata);
        $date = $this->getFileDate($metadata, $filePath);
        $url = $this->getFileUrl($outputPath, $container);

        $this->categoryFiles[$sanitizedCategory]['files'][] = [
            'title' => $title,
            'url' => $url,
            'description' => $description,
            'date' => $date,
            'metadata' => $metadata
        ];

        $this->logger->log('DEBUG', "Collected file for RSS: {$title} in category {$category}");

        return $parameters;
    }

    /**
     * Generate RSS feeds for all categories during POST_LOOP
     *
     * Called dynamically by EventManager when POST_LOOP event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function generateRssFeeds(Container $container, array $parameters): array
    {
        if (empty($this->categoryFiles)) {
            $this->logger->log('INFO', 'No categories with files - skipping RSS feed generation');
            return $parameters;
        }

        $this->logger->log('INFO', 'Generating RSS feeds for ' . count($this->categoryFiles) . ' categories');

        $publicDir = $container->getVariable('PUBLIC_DIR') ?? 'public';
        $siteBaseUrl = $container->getVariable('SITE_BASE_URL') ?? 'https://example.com/';

        $siteConfig = $container->getVariable('site_config') ?? [];
        $siteInfo = $siteConfig['site'] ?? [];
        $siteName = $siteInfo['name'] ?? $container->getVariable('SITE_NAME') ?? 'My Site';

        foreach ($this->categoryFiles as $categorySlug => $categoryData) {
            $this->generateRssFeed(
                $categorySlug,
                $categoryData,
                $publicDir,
                $siteBaseUrl,
                $siteName
            );
        }

        return $parameters;
    }

    /**
     * Generate RSS feed XML file for a single category
     *
     * @param string $categorySlug Sanitized category name
     * @param array<string, mixed> $categoryData Category data with files
     * @param string $publicDir Public output directory
     * @param string $siteBaseUrl Base URL for the site
     * @param string $siteName Site name
     */
    private function generateRssFeed(
        string $categorySlug,
        array $categoryData,
        string $publicDir,
        string $siteBaseUrl,
        string $siteName
    ): void {
        $files = $categoryData['files'] ?? [];
        $categoryName = $categoryData['display_name'] ?? ucfirst($categorySlug);

        // Sort files by date (newest first)
        usort($files, function ($a, $b) {
            return strtotime($b['date']) <=> strtotime($a['date']);
        });

        // Build RSS XML
        $xml = $this->buildRssXml($categoryName, $categorySlug, $files, $siteBaseUrl, $siteName);

        // Write RSS file
        $categoryDir = $publicDir . DIRECTORY_SEPARATOR . $categorySlug;
        if (!is_dir($categoryDir)) {
            mkdir($categoryDir, 0755, true);
        }

        $rssPath = $categoryDir . DIRECTORY_SEPARATOR . 'rss.xml';
        file_put_contents($rssPath, $xml);

        $this->logger->log('INFO', "Generated RSS feed: {$rssPath} with " . count($files) . " items");
    }

    /**
     * Build RSS XML content
     *
     * @param string $categoryName Category display name
     * @param string $categorySlug Category URL slug
     * @param array<int, array<string, mixed>> $files Files to include in feed
     * @param string $siteBaseUrl Base URL for the site
     * @param string $siteName Site name
     * @return string RSS XML content
     */
    private function buildRssXml(
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
     * @return string RSS item XML
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
     * Extract description from rendered content or metadata
     *
     * @param string $html Rendered HTML content
     * @param array<string, mixed> $metadata File metadata
     * @return string Description text
     */
    private function extractDescription(string $html, array $metadata): string
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
     * @return string ISO 8601 date string
     */
    private function getFileDate(array $metadata, string $filePath): string
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
     * @param Container $container Dependency injection container
     * @return string URL path relative to site root
     */
    private function getFileUrl(string $outputPath, Container $container): string
    {
        $publicDir = $container->getVariable('PUBLIC_DIR') ?? 'public';

        // Remove public directory from path to get relative URL
        $url = str_replace($publicDir, '', $outputPath);

        // Normalize path separators to forward slashes for URLs
        $url = str_replace(DIRECTORY_SEPARATOR, '/', $url);

        // Ensure URL starts with /
        if (!str_starts_with($url, '/')) {
            $url = '/' . $url;
        }

        return $url;
    }

    /**
     * Sanitize category name for use in filesystem paths
     *
     * @param string $category Category name
     * @return string Sanitized category name
     */
    private function sanitizeCategoryName(string $category): string
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

        return $sanitized;
    }

    /**
     * Escape XML special characters
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    private function escapeXml(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
