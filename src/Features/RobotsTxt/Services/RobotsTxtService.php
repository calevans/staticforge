<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\RobotsTxt\Services;

use EICC\StaticForge\Core\Events\RobotsTxtBuildingEvent;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\OutputWriter;
use EICC\StaticForge\Core\PathGuard;
use EICC\Utils\Container;
use EICC\Utils\Log;

class RobotsTxtService
{
    private Log $logger;
    private RobotsTxtGenerator $generator;
    private OutputWriter $outputWriter;
    private EventManager $eventManager;
    private Container $container;

    /**
     * Paths to disallow in robots.txt
     * @var array<int, string>
     */
    private array $disallowedPaths = [];

    public function __construct(
        Log $logger,
        RobotsTxtGenerator $generator,
        OutputWriter $outputWriter,
        EventManager $eventManager,
        Container $container
    ) {
        $this->logger = $logger;
        $this->generator = $generator;
        $this->outputWriter = $outputWriter;
        $this->eventManager = $eventManager;
        $this->container = $container;
    }

    /**
     * Scan all discovered files for robots metadata
     */
    public function scanForRobotsMetadata(): void
    {
        $discoveredFiles = $this->container->getVariable('discovered_files') ?? [];
        $sourceDir = $this->container->getVariable('SOURCE_DIR') ?? 'content';

        $this->logger->log('INFO', 'RobotsTxt: Scanning files for robots metadata');

        foreach ($discoveredFiles as $fileData) {
            $this->scanFileForRobotsMetadata($fileData, $sourceDir);
        }

        // Also scan for category definition files
        $this->scanCategoryFiles();

        $this->logger->log(
            'INFO',
            'RobotsTxt: Found ' . count($this->disallowedPaths) . ' paths to disallow'
        );
    }

    /**
     * Generate robots.txt file
     */
    public function generateRobotsTxt(): void
    {
        $discoveredFiles = $this->container->getVariable('discovered_files');
        if (empty($discoveredFiles)) {
            $this->logger->log('INFO', 'RobotsTxt: No files discovered, skipping robots.txt generation');
            return;
        }
        $this->logger->log('INFO', 'RobotsTxt: Files discovered: ' . count($discoveredFiles));

        $outputDir = $this->container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set in container');
        }
        $siteBaseUrl = $this->container->getVariable('SITE_BASE_URL');
        if ($siteBaseUrl === null) {
            throw new \RuntimeException('SITE_BASE_URL not set in container');
        }

        $this->logger->log('INFO', 'RobotsTxt: Generating robots.txt file');

        $rules = [
            '*' => [
                'Disallow' => $this->disallowedPaths,
                'Allow' => []
            ],
            'Bingbot' => [
                'Allow' => ['/']
            ]
        ];

        $buildingEvent = new RobotsTxtBuildingEvent('ROBOTS_TXT_BUILDING', $rules);
        $this->eventManager->fire('ROBOTS_TXT_BUILDING', $buildingEvent);
        $finalRules = $buildingEvent->rules;

        $robotsTxtContent = $this->generator->generate($siteBaseUrl, $finalRules);

        // Write robots.txt to output directory
        $robotsTxtPath = $outputDir . '/robots.txt';

        try {
            $this->outputWriter->write($robotsTxtPath, $robotsTxtContent);
            $this->logger->log('INFO', "robots.txt generated at {$robotsTxtPath}");
        } catch (\Throwable $e) {
            $this->logger->log('ERROR', "Failed to write robots.txt to {$robotsTxtPath}: " . $e->getMessage());
        }
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
    private function scanCategoryFiles(): void
    {
        // Category files are typically named like "category-slug.md" or "category-slug.html"
        // with type=category in frontmatter
        $discoveredFiles = $this->container->getVariable('discovered_files') ?? [];

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
        $relativePath = PathGuard::relativeTo($filePath, $sourceDir) ?? basename($filePath);

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
