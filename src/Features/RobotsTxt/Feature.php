<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\RobotsTxt;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * RobotsTxt Feature - generates robots.txt file based on content metadata
 * 
 * EVENTS FIRED: None
 * 
 * EVENTS OBSERVED:
 * - POST_GLOB (priority 150): Scans discovered files for robots metadata
 * - POST_LOOP (priority 100): Generates robots.txt file at the end of processing
 * 
 * Honors the "robots" field in content file frontmatter:
 * - robots=no: Disallow the page in robots.txt
 * - robots=yes or not specified: Allow the page (default)
 * 
 * Also honors robots field in category definition files to disallow entire categories
 */
class Feature extends BaseFeature implements FeatureInterface
{
  protected string $name = 'RobotsTxt';
  protected Log $logger;

  /**
   * Paths to disallow in robots.txt
   * @var array<int, string>
   */
  private array $disallowedPaths = [];

  /**
   * @var array<string, array{method: string, priority: int}>
   */
  protected array $eventListeners = [
    'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 150],
    'POST_LOOP' => ['method' => 'handlePostLoop', 'priority' => 100]
  ];

  public function register(EventManager $eventManager, Container $container): void
  {
    parent::register($eventManager, $container);

    // Get logger from container
    $this->logger = $container->get('logger');

    $this->logger->log('INFO', 'RobotsTxt Feature registered');
  }

  /**
   * Handle POST_GLOB event - scan all discovered files for robots metadata
   *
   * Called dynamically by EventManager when POST_GLOB event fires.
   *
   * @phpstan-used Called via EventManager event dispatch
   * @param array<string, mixed> $parameters
   * @return array<string, mixed>
   */
  public function handlePostGlob(Container $container, array $parameters): array
  {
    $discoveredFiles = $container->getVariable('discovered_files') ?? [];
    $sourceDir = $container->getVariable('SOURCE_DIR') ?? 'content';

    $this->logger->log('INFO', 'RobotsTxt: Scanning files for robots metadata');

    foreach ($discoveredFiles as $filePath) {
      $this->scanFileForRobotsMetadata($filePath, $sourceDir);
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
   * Handle POST_LOOP event - generate robots.txt file
   *
   * Called dynamically by EventManager when POST_LOOP event fires.
   *
   * @phpstan-used Called via EventManager event dispatch
   * @param array<string, mixed> $parameters
   * @return array<string, mixed>
   */
  public function handlePostLoop(Container $container, array $parameters): array
  {
    $outputDir = $container->getVariable('OUTPUT_DIR') ?? 'output';
    $siteBaseUrl = $container->getVariable('SITE_BASE_URL') ?? '';

    $this->logger->log('INFO', 'RobotsTxt: Generating robots.txt file');

    $robotsTxtContent = $this->generateRobotsTxt($siteBaseUrl);

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
   */
  private function scanFileForRobotsMetadata(string $filePath, string $sourceDir): void
  {
    if (!file_exists($filePath)) {
      return;
    }

    $content = @file_get_contents($filePath);
    if ($content === false) {
      return;
    }

    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
    $metadata = [];

    if ($extension === 'md') {
      $metadata = $this->extractMetadataFromMarkdown($content);
    } elseif ($extension === 'html') {
      $metadata = $this->extractMetadataFromHtml($content);
    }

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
    $sourceDir = $container->getVariable('SOURCE_DIR') ?? 'content';

    // Category files are typically named like "category-slug.md" or "category-slug.html"
    // with type=category in frontmatter
    $discoveredFiles = $container->getVariable('discovered_files') ?? [];

    foreach ($discoveredFiles as $filePath) {
      if (!file_exists($filePath)) {
        continue;
      }

      $content = @file_get_contents($filePath);
      if ($content === false) {
        continue;
      }

      $extension = pathinfo($filePath, PATHINFO_EXTENSION);
      $metadata = [];

      if ($extension === 'md') {
        $metadata = $this->extractMetadataFromMarkdown($content);
      } elseif ($extension === 'html') {
        $metadata = $this->extractMetadataFromHtml($content);
      }

      // Check if this is a category definition file
      $type = $metadata['type'] ?? '';
      if ($type === 'category') {
        $robots = $metadata['robots'] ?? 'yes';
        $robots = strtolower(trim($robots));

        if ($robots === 'no') {
          // Get category slug/name
          $category = $metadata['category'] ?? $this->getCategoryFromFilename($filePath);

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
   * Extract metadata from Markdown file frontmatter
   * 
   * @return array<string, mixed>
   */
  private function extractMetadataFromMarkdown(string $content): array
  {
    $metadata = [];

    // Check for INI frontmatter (--- ... ---)
    if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
      $iniContent = trim($matches[1]);

      if (!empty($iniContent)) {
        $lines = explode("\n", $iniContent);
        foreach ($lines as $line) {
          $line = trim($line);
          // INI format uses = not :
          if (empty($line) || !str_contains($line, '=')) {
            continue;
          }

          [$key, $value] = array_map('trim', explode('=', $line, 2));
          // Remove quotes if present
          $value = trim($value, '"\'');

          $metadata[$key] = $value;
        }
      }
    }

    return $metadata;
  }

  /**
   * Extract metadata from HTML file frontmatter
   * 
   * @return array<string, mixed>
   */
  private function extractMetadataFromHtml(string $content): array
  {
    $metadata = [];

    // Check for HTML comment INI format <!-- INI ... -->
    if (preg_match('/<!--\s*INI\s*\n(.*?)\n-->/s', $content, $matches)) {
      $iniContent = trim($matches[1]);

      if (!empty($iniContent)) {
        $lines = explode("\n", $iniContent);
        foreach ($lines as $line) {
          $line = trim($line);
          // INI format uses = not :
          if (empty($line) || !str_contains($line, '=')) {
            continue;
          }

          [$key, $value] = array_map('trim', explode('=', $line, 2));
          // Remove quotes if present
          $value = trim($value, '"\'');

          $metadata[$key] = $value;
        }
      }
    }

    return $metadata;
  }

  /**
   * Calculate the web path for a file (relative URL)
   */
  private function calculateWebPath(string $filePath, string $sourceDir): ?string
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

  /**
   * Generate robots.txt content
   */
  private function generateRobotsTxt(string $siteBaseUrl): string
  {
    $content = "# robots.txt generated by StaticForge\n";
    $content .= "# " . date('Y-m-d H:i:s') . "\n\n";
    $content .= "User-agent: *\n";

    if (empty($this->disallowedPaths)) {
      $content .= "# No disallowed paths\n";
      $content .= "Disallow:\n";
    } else {
      // Sort paths for consistent output
      $paths = $this->disallowedPaths;
      sort($paths);
      $paths = array_unique($paths);

      foreach ($paths as $path) {
        $content .= "Disallow: {$path}\n";
      }
    }

    // Add sitemap reference if we have a base URL
    if (!empty($siteBaseUrl)) {
      $siteBaseUrl = rtrim($siteBaseUrl, '/');
      $content .= "\n# Sitemap location\n";
      $content .= "Sitemap: {$siteBaseUrl}/sitemap.xml\n";
    }

    return $content;
  }
}
