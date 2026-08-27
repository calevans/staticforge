<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Sitemap\Services;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\OutputWriter;
use EICC\Utils\Container;
use EICC\Utils\Log;

class SitemapService
{
    private Log $logger;
    private OutputWriter $outputWriter;
    private Container $container;

    /**
     * Collected URLs for the sitemap
     * @var array<int, array{loc: string, lastmod: string}>
     */
    private array $urls = [];

    public function __construct(Log $logger, OutputWriter $outputWriter, Container $container)
    {
        $this->logger = $logger;
        $this->outputWriter = $outputWriter;
        $this->container = $container;
    }

    /**
     * Collect URL from processed file
     */
    public function collectUrl(RenderEvent $event): void
    {
        $outputPath = $event->outputPath;
        $metadata = $event->metadata;

        // Skip if no output path
        if (!$outputPath) {
            return;
        }

        // Get site URL from config or default to /
        $siteUrl = rtrim($this->container->getVariable('SITE_BASE_URL') ?? '', '/');

        // Calculate relative URL from output path
        // output/foo/bar.html -> foo/bar.html
        $outputDir = $this->container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set in container');
        }
        $relativePath = ltrim(substr($outputPath, strlen($outputDir)), '/');

        // Construct canonical URL, rewriting index.html paths to directory URLs
        $loc = $this->normalizeUrl($relativePath, $siteUrl);

        // Get last modification date
        // Prefer 'date' from metadata, fallback to file mtime if available, else now
        $lastmod = date('Y-m-d');
        if (isset($metadata['date'])) {
            // Try to parse date from metadata
            $timestamp = strtotime((string)$metadata['date']);
            if ($timestamp !== false) {
                $lastmod = date('Y-m-d', $timestamp);
            }
        } elseif ($event->filePath !== '' && file_exists($event->filePath)) {
            $mtime = filemtime($event->filePath);
            if ($mtime !== false) {
                $lastmod = date('Y-m-d', $mtime);
            }
        }

        $this->urls[] = [
            'loc' => $loc,
            'lastmod' => $lastmod
        ];
    }

    private function normalizeUrl(string $relativePath, string $siteUrl): string
    {
        // Root case must be first: dirname('index.html') returns '.' and would produce a bad URL.
        if ($relativePath === 'index.html') {
            return $siteUrl . '/';
        }

        if (basename($relativePath) === 'index.html') {
            return $siteUrl . '/' . rtrim(dirname($relativePath), '/') . '/';
        }

        return $siteUrl . '/' . ltrim($relativePath, '/');
    }

    /**
     * Generate sitemap.xml file
     */
    public function generateSitemap(): void
    {
        if (empty($this->urls)) {
            $this->logger->log('INFO', 'No URLs collected, skipping sitemap.xml generation');
            return;
        }

        $this->logger->log('INFO', 'Generating sitemap.xml with ' . count($this->urls) . ' URLs');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        foreach ($this->urls as $url) {
            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . htmlspecialchars($url['loc']) . '</loc>' . PHP_EOL;
            $xml .= '    <lastmod>' . htmlspecialchars($url['lastmod']) . '</lastmod>' . PHP_EOL;
            $xml .= '  </url>' . PHP_EOL;
        }

        $xml .= '</urlset>';

        $outputDir = $this->container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set in container');
        }
        $sitemapPath = $outputDir . '/sitemap.xml';

        try {
            $this->outputWriter->write($sitemapPath, $xml);
            $this->logger->log('INFO', 'sitemap.xml generated successfully');
        } catch (\Throwable $e) {
            $this->logger->log('ERROR', 'Failed to write sitemap.xml to ' . $sitemapPath . ': ' . $e->getMessage());
        }
    }
}
