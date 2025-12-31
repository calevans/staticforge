<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\RobotsTxt\Services;

use EICC\Utils\Container;
use EICC\Utils\Log;

class RobotsTxtService
{
    private Log $logger;
    private RobotsTxtGenerator $generator;

    /**
     * Paths to disallow in robots.txt
     * @var array<int, string>
     */
    private array $disallowedPaths = [];

    public function __construct(Log $logger, RobotsTxtGenerator $generator)
    {
        $this->logger = $logger;
        $this->generator = $generator;
    }

    /**
     * Scan all discovered files for robots metadata
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function scanForRobotsMetadata(Container $container, array $parameters): array
    {
        $discoveredFiles = $container->getVariable('discovered_files') ?? [];
        $sourceDir = $container->getVariable('SOURCE_DIR') ?? 'content';

        $this->logger->log('INFO', 'RobotsTxt: Scanning files for robots metadata');

        foreach ($discoveredFiles as $fileData) {
            $this->scanFileForRobotsMetadata($fileData, $sourceDir);
        }

        // Also scan for category definition files
        $this->scanCategoryFiles($container);

        $this->logger->log(
            'INFO',
            'RobotsTxt: Found ' . count($this->disallowedPaths) . ' paths to disallow'
        );

        return $parameters;
    }

    /**
     * Generate robots.txt file
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function generateRobotsTxt(Container $container, array $parameters): array
    {
        $discoveredFiles = $container->getVariable('discovered_files');
        if (empty($discoveredFiles)) {
            $this->logger->log('INFO', 'RobotsTxt: No files discovered, skipping robots.txt generation');
            return $parameters;
        }
        $this->logger->log('INFO', 'RobotsTxt: Files discovered: ' . count($discoveredFiles));

        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set in container');
        }
        $siteBaseUrl = $container->getVariable('SITE_BASE_URL');
        if ($siteBaseUrl === null) {
            throw new \RuntimeException('SITE_BASE_URL not set in container');
        }

        $this->logger->log('INFO', 'RobotsTxt: Generating robots.txt file');

        $robotsTxtContent = $this->generator->generate($siteBaseUrl, $this->disallowedPaths);

        // Write robots.txt to output directory
        $robotsTxtPath = $outputDir . '/robots.txt';

        // Create output directory if it doesn't exist
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $result = file_put_contents($robotsTxtPath, $robotsTxtContent);

        if ($result === false) {
            $this->logger->log('ERROR', "Failed to write robots.txt to {$robotsTxtPath}");
        } else {
            $this->logger->log('INFO', "robots.txt generated at {$robotsTxtPath}");
        }

        return $parameters;
    }

    /**
     * Scan a content file for robots metadata
     *
     * @param array{path: string, url: string, metadata: array<string, mixed>} $fileData File data from discovery
     */
    private function scanFileForRobotsMetadata(array $fileData, string $sourceDir): void
    {
        $filePath = $fileData['path'];
        $metadata = $fileData['metadata'];

        // Check robots field
        $robots = $metadata['robots'] ?? 'yes';
        $robots = strtolower(trim($robots));

        if ($robots === 'no') {
            // Calculate the web path for this file
            $webPath = $this->calculateWebPath($filePath, $sourceDir);
            if ($webPath) {
                $this->disallowedPaths[] = $webPath;
                $this->logger->log('DEBUG', "RobotsTxt: Disallowing path: {$webPath}");
            }
        }
    }

    /**
     * Scan for category definition files and check their robots metadata
     */
    private function scanCategoryFiles(Container $container): void
    {
        // Category files are typically named like "category-slug.md" or "category-slug.html"
        // with type=category in frontmatter
        $discoveredFiles = $container->getVariable('discovered_files') ?? [];

        foreach ($discoveredFiles as $fileData) {
            $metadata = $fileData['metadata'];

            // Check if this is a category definition file
            $type = $metadata['type'] ?? '';
            if ($type === 'category') {
                $robots = $metadata['robots'] ?? 'yes';
                $robots = strtolower(trim($robots));

                if ($robots === 'no') {
                    // Get category slug/name
                    $category = $metadata['category'] ?? $this->getCategoryFromFilename($fileData['path']);

                    if ($category) {
                        // Disallow entire category directory
                        $categorySlug = $this->sanitizeCategoryName($category);
                        $categoryPath = '/' . $categorySlug . '/';
                        $this->disallowedPaths[] = $categoryPath;
                        $this->logger->log('DEBUG', "RobotsTxt: Disallowing category: {$categoryPath}");
                    }
                }
            }
        }
    }

    /**
     * Calculate the web path for a file (relative URL)
     */
    private function calculateWebPath(string $filePath, string $sourceDir): string
    {
        // Normalize paths
        $normalizedSourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR);
        $normalizedFilePath = $filePath;

        // Check if file path starts with source directory
        if (!str_starts_with($normalizedFilePath, $normalizedSourceDir)) {
            // Fallback to just the filename
            $relativePath = basename($filePath);
        } else {
            // Get path relative to source directory
            $relativePath = substr($normalizedFilePath, strlen($normalizedSourceDir) + 1);
        }

        // Convert file extension to .html
        $relativePath = preg_replace('/\.(md|html)$/', '.html', $relativePath) ?? $relativePath;

        // Convert to web path with forward slashes
        $webPath = '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

        return $webPath;
    }

    /**
     * Get category name from filename
     */
    private function getCategoryFromFilename(string $filePath): string
    {
        $filename = pathinfo($filePath, PATHINFO_FILENAME);
        return $filename;
    }

    /**
     * Sanitize category name for use in filesystem paths (same as Categories feature)
     */
    private function sanitizeCategoryName(string $category): string
    {
        // Convert to lowercase
        $sanitized = strtolower($category);

        // Replace spaces and special characters with hyphens
        $sanitized = preg_replace('/[^a-z0-9]+/', '-', $sanitized) ?? $sanitized;

        // Remove leading/trailing hyphens
        $sanitized = trim($sanitized, '-');

        return $sanitized;
    }
}
