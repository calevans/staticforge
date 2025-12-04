<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\RssFeed;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\RssFeed\Models\FeedChannel;
use EICC\StaticForge\Features\RssFeed\Models\FeedItem;
use EICC\StaticForge\Features\RssFeed\Services\Extensions\PodcastExtension;
use EICC\StaticForge\Features\RssFeed\Services\PodcastMediaService;
use EICC\StaticForge\Features\RssFeed\Services\RssBuilder;
use EICC\StaticForge\Services\MediaInspector;
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
    private PodcastMediaService $mediaService;

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

    public function __construct()
    {
        // We instantiate services here. MediaInspector is a core service but not in container by default yet,
        // so we instantiate it.
        $this->mediaService = new PodcastMediaService(new MediaInspector());
    }

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

        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
             // Should not happen if output_path is set, but safe fallback
             return $parameters;
        }
        $url = $this->getFileUrl($outputPath, $outputDir);

        $this->categoryFiles[$sanitizedCategory]['files'][] = [
            'title' => $title,
            'url' => $url,
            'description' => $description,
            'date' => $date,
            'metadata' => $metadata,
            'content' => $renderedContent
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

        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set in container');
        }
        $sourceDir = $container->getVariable('SOURCE_DIR') ?? 'content';
        $siteBaseUrl = $container->getVariable('SITE_BASE_URL') ?? 'https://example.com/';

        $siteConfig = $container->getVariable('site_config') ?? [];
        $siteInfo = $siteConfig['site'] ?? [];
        $siteName = $siteInfo['name'] ?? $container->getVariable('SITE_NAME') ?? 'My Site';

        // Get category definitions to find podcast settings
        $discoveredFiles = $container->getVariable('discovered_files') ?? [];
        $categoryDefinitions = [];

        foreach ($discoveredFiles as $file) {
            $metadata = $file['metadata'] ?? [];
            if (($metadata['type'] ?? '') === 'category') {
                $slug = pathinfo($file['path'], PATHINFO_FILENAME);
                $categoryDefinitions[$slug] = $metadata;
            }
        }

        foreach ($this->categoryFiles as $categorySlug => $categoryData) {
            $categoryMetadata = $categoryDefinitions[$categorySlug] ?? [];

            $this->generateRssFeed(
                $categorySlug,
                $categoryData,
                $outputDir,
                $sourceDir,
                $siteBaseUrl,
                $siteName,
                $categoryMetadata
            );
        }

        return $parameters;
    }

    /**
     * Generate RSS feed XML file for a single category
     *
     * @param string $categorySlug Sanitized category name
     * @param array<string, mixed> $categoryData Category data with files
     * @param string $outputDir Output directory
     * @param string $sourceDir Source directory
     * @param string $siteBaseUrl Base URL for the site
     * @param string $siteName Site name
     * @param array<string, mixed> $categoryMetadata Category definition metadata
     */
    private function generateRssFeed(
        string $categorySlug,
        array $categoryData,
        string $outputDir,
        string $sourceDir,
        string $siteBaseUrl,
        string $siteName,
        array $categoryMetadata = []
    ): void {
        $files = $categoryData['files'] ?? [];
        $categoryName = $categoryData['display_name'] ?? ucfirst($categorySlug);

        // Sort files by date (newest first)
        usort($files, function ($a, $b) {
            return strtotime($b['date']) <=> strtotime($a['date']);
        });

        // Ensure base URL has trailing slash
        $siteBaseUrl = rtrim($siteBaseUrl, '/') . '/';
        $isPodcast = ($categoryMetadata['rss_type'] ?? '') === 'podcast';

        // Prepare Builder
        $builder = new RssBuilder();
        if ($isPodcast) {
            $builder->addExtension(new PodcastExtension());
        }

        // Create Channel Model
        $channel = new FeedChannel(
            $siteName . ' - ' . $categoryName,
            $siteBaseUrl . $categorySlug . '/',
            $categoryName . ' articles from ' . $siteName,
            $siteBaseUrl . $categorySlug . '/rss.xml',
            $categoryMetadata
        );

        // Create Item Models
        $feedItems = [];
        foreach ($files as $file) {
            $fullUrl = $siteBaseUrl . ltrim($file['url'], '/');

            $item = new FeedItem(
                $file['title'],
                $fullUrl,
                $fullUrl,
                date('r', strtotime($file['date'])),
                $file['metadata']
            );

            $item->description = $file['description'];
            $item->content = $file['content'];
            $item->author = $file['metadata']['author'] ?? null;

            // Process Media if Podcast
            if ($isPodcast) {
                $mediaData = $this->mediaService->processMedia($file, $sourceDir, $outputDir);
                if ($mediaData) {
                    // Ensure URL is absolute if needed, or relative.
                    // Podcast clients usually need absolute URLs.
                    if (!preg_match('~^https?://~i', $mediaData['url'])) {
                        $mediaData['url'] = $siteBaseUrl . ltrim($mediaData['url'], '/');
                    }
                    $item->enclosure = $mediaData;
                }
            }

            $feedItems[] = $item;
        }

        // Build XML
        $xml = $builder->build($channel, $feedItems);

        // Write RSS file
        $categoryDir = $outputDir . DIRECTORY_SEPARATOR . $categorySlug;
        if (!is_dir($categoryDir)) {
            mkdir($categoryDir, 0755, true);
        }

        $rssPath = $categoryDir . DIRECTORY_SEPARATOR . 'rss.xml';
        file_put_contents($rssPath, $xml);

        $this->logger->log('INFO', "Generated RSS feed: {$rssPath} with " . count($files) . " items");
    }

    // --- Helper Methods ---

    private function sanitizeCategoryName(string $category): string
    {
        $sanitized = strtolower($category);
        $sanitized = preg_replace('/[^a-z0-9]+/', '-', $sanitized);
        if ($sanitized === null) {
            $sanitized = 'category';
        }
        $sanitized = trim($sanitized, '-');
        return $sanitized === '' ? 'category' : $sanitized;
    }

    private function extractDescription(string $html, array $metadata): string
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

    private function getFileDate(array $metadata, string $filePath): string
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

    private function getFileUrl(string $outputPath, string $outputDir): string
    {
        $url = str_replace($outputDir, '', $outputPath);
        $url = str_replace(DIRECTORY_SEPARATOR, '/', $url);
        if (!str_starts_with($url, '/')) {
            $url = '/' . $url;
        }
        return $url;
    }
}
