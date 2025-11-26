<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Sitemap;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Sitemap Feature - generates sitemap.xml
 * Listens to POST_RENDER to collect URLs, then POST_LOOP to generate the file
 */
class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'Sitemap';
    protected Log $logger;

    /**
     * Collected URLs for the sitemap
     * @var array<int, array{loc: string, lastmod: string}>
     */
    private array $urls = [];

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_RENDER' => ['method' => 'collectUrl', 'priority' => 100],
        'POST_LOOP' => ['method' => 'generateSitemap', 'priority' => 100]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);
        $this->logger = $container->get('logger');
        $this->logger->log('INFO', 'Sitemap Feature registered');
    }

    /**
     * Collect URL from processed file
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function collectUrl(Container $container, array $parameters): array
    {
        $outputPath = $parameters['output_path'] ?? null;
        $metadata = $parameters['metadata'] ?? [];

        // Skip if no output path
        if (!$outputPath) {
            return $parameters;
        }

        // Get site URL from config or default to /
        $siteUrl = rtrim($container->getVariable('SITE_URL') ?? '', '/');

        // Calculate relative URL from output path
        // output/foo/bar.html -> foo/bar.html
        $outputDir = $container->getVariable('OUTPUT_DIR') ?? 'output';
        $relativePath = str_replace($outputDir . '/', '', $outputPath);

        // Construct full URL
        $loc = $siteUrl . '/' . ltrim($relativePath, '/');

        // Get last modification date
        // Prefer 'date' from metadata, fallback to file mtime if available, else now
        $lastmod = date('Y-m-d');
        if (isset($metadata['date'])) {
            // Try to parse date from metadata
            $timestamp = strtotime($metadata['date']);
            if ($timestamp !== false) {
                $lastmod = date('Y-m-d', $timestamp);
            }
        } elseif (isset($parameters['file_path']) && file_exists($parameters['file_path'])) {
            $lastmod = date('Y-m-d', filemtime($parameters['file_path']));
        }

        $this->urls[] = [
            'loc' => $loc,
            'lastmod' => $lastmod
        ];

        return $parameters;
    }

    /**
     * Generate sitemap.xml file
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function generateSitemap(Container $container, array $parameters): array
    {
        $this->logger->log('INFO', 'Generating sitemap.xml with ' . count($this->urls) . ' URLs');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        foreach ($this->urls as $url) {
            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . htmlspecialchars($url['loc']) . '</loc>' . PHP_EOL;
            $xml .= '    <lastmod>' . $url['lastmod'] . '</lastmod>' . PHP_EOL;
            $xml .= '  </url>' . PHP_EOL;
        }

        $xml .= '</urlset>';

        $outputDir = $container->getVariable('OUTPUT_DIR') ?? 'output';
        $sitemapPath = $outputDir . '/sitemap.xml';

        if (file_put_contents($sitemapPath, $xml) === false) {
            $this->logger->log('ERROR', 'Failed to write sitemap.xml to ' . $sitemapPath);
        } else {
            $this->logger->log('INFO', 'sitemap.xml generated successfully');
        }

        return $parameters;
    }
}
