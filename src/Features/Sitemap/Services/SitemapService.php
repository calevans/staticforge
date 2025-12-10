<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Sitemap\Services;

use EICC\Utils\Container;
use EICC\Utils\Log;

class SitemapService
{
    private Log $logger;

    /**
     * Collected URLs for the sitemap
     * @var array<int, array{loc: string, lastmod: string}>
     */
    private array $urls = [];

    public function __construct(Log $logger)
    {
        $this->logger = $logger;
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
        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set in container');
        }
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
            $mtime = filemtime($parameters['file_path']);
            if ($mtime !== false) {
                $lastmod = date('Y-m-d', $mtime);
            }
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
        if (empty($this->urls)) {
            $this->logger->log('INFO', 'No URLs collected, skipping sitemap.xml generation');
            return $parameters;
        }

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

        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set in container');
        }
        $sitemapPath = $outputDir . '/sitemap.xml';

        if (file_put_contents($sitemapPath, $xml) === false) {
            $this->logger->log('ERROR', 'Failed to write sitemap.xml to ' . $sitemapPath);
        } else {
            $this->logger->log('INFO', 'sitemap.xml generated successfully');
        }

        return $parameters;
    }
}
