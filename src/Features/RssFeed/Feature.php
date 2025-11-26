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
    private RssDataProcessor $dataProcessor;
    private RssFeedGenerator $feedGenerator;

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
        $this->dataProcessor = new RssDataProcessor();
        $this->feedGenerator = new RssFeedGenerator();
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
        $sanitizedCategory = $this->dataProcessor->sanitizeCategoryName($category);

        if (!isset($this->categoryFiles[$sanitizedCategory])) {
            $this->categoryFiles[$sanitizedCategory] = [
                'display_name' => $category,
                'files' => []
            ];
        }

        // Extract description from rendered content
        $description = $this->dataProcessor->extractDescription($renderedContent, $metadata);
        $date = $this->dataProcessor->getFileDate($metadata, $filePath);

        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
             // Should not happen if output_path is set, but safe fallback
             return $parameters;
        }
        $url = $this->dataProcessor->getFileUrl($outputPath, $outputDir);

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

        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set in container');
        }
        $siteBaseUrl = $container->getVariable('SITE_BASE_URL') ?? 'https://example.com/';

        $siteConfig = $container->getVariable('site_config') ?? [];
        $siteInfo = $siteConfig['site'] ?? [];
        $siteName = $siteInfo['name'] ?? $container->getVariable('SITE_NAME') ?? 'My Site';

        foreach ($this->categoryFiles as $categorySlug => $categoryData) {
            $this->generateRssFeed(
                $categorySlug,
                $categoryData,
                $outputDir,
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
     * @param string $outputDir Output directory
     * @param string $siteBaseUrl Base URL for the site
     * @param string $siteName Site name
     */
    private function generateRssFeed(
        string $categorySlug,
        array $categoryData,
        string $outputDir,
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
        $xml = $this->feedGenerator->generateFeedXml($categoryName, $categorySlug, $files, $siteBaseUrl, $siteName);

        // Write RSS file
        $categoryDir = $outputDir . DIRECTORY_SEPARATOR . $categorySlug;
        if (!is_dir($categoryDir)) {
            mkdir($categoryDir, 0755, true);
        }

        $rssPath = $categoryDir . DIRECTORY_SEPARATOR . 'rss.xml';
        file_put_contents($rssPath, $xml);

        $this->logger->log('INFO', "Generated RSS feed: {$rssPath} with " . count($files) . " items");
    }
}
