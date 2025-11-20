<?php

namespace EICC\StaticForge\Core;

use EICC\Utils\Container;
use EICC\Utils\Log;
use Symfony\Component\Yaml\Yaml;

/**
 * Discovers content files in configured directories
 * Filters by registered extensions and stores file paths in container
 */
class FileDiscovery
{
    private Container $container;
    private Log $logger;
    private ExtensionRegistry $extensionRegistry;

    public function __construct(Container $container, ExtensionRegistry $extensionRegistry)
    {
        $this->container = $container;
        $this->logger = $container->get('logger');
        $this->extensionRegistry = $extensionRegistry;
    }

    /**
     * Discover all processable files and store in container
     * Each file includes path, URL, and parsed frontmatter metadata
     */
    public function discoverFiles(): void
    {
        $directories = $this->getDirectoriesToScan();
        $discoveredFiles = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                $this->logger->log('WARNING', "Directory not found: {$directory}");
                continue;
            }

            $this->scanDirectory($directory, $discoveredFiles);
        }

        // Store discovered files in container
        $this->container->setVariable('discovered_files', $discoveredFiles);

        $this->logger->log('INFO', "Discovered " . count($discoveredFiles) . " processable files");
    }

    /**
     * Get list of directories to scan from container
     *
     * @return array<string>
     */
    protected function getDirectoriesToScan(): array
    {
        $directories = $this->container->getVariable('SCAN_DIRECTORIES');

        if ($directories === null) {
            // Default to SOURCE_DIR if SCAN_DIRECTORIES not configured
            $sourceDir = $this->container->getVariable('SOURCE_DIR') ?? 'content';
            return [$sourceDir];
        }

        return is_array($directories) ? $directories : [$directories];
    }

    /**
     * Recursively scan directory for processable files
     *
     * @param string $directory Directory to scan
     * @param array<array{path: string, url: string, metadata: array<string, mixed>}> &$files Files array passed by reference
     */
    protected function scanDirectory(string $directory, array &$files): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $this->extensionRegistry->canProcess($file->getPathname())) {
                $filePath = $file->getPathname();

                // Parse frontmatter and generate URL
                $metadata = $this->parseFrontmatter($filePath);
                $url = $this->generateUrl($filePath, $metadata);

                $files[] = [
                    'path' => $filePath,
                    'url' => $url,
                    'metadata' => $metadata,
                ];
            }
        }
    }

    /**
     * Parse frontmatter from a file
     *
     * @param string $filePath Path to file
     * @return array<string, mixed> Parsed metadata
     */
    protected function parseFrontmatter(string $filePath): array
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            $this->logger->log('WARNING', "Failed to read file: {$filePath}");
            return [];
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        if ($extension === 'md') {
            return $this->parseMarkdownFrontmatter($content);
        } elseif ($extension === 'html') {
            return $this->parseHtmlFrontmatter($content);
        }

        return [];
    }

    /**
     * Parse YAML frontmatter from Markdown file (--- ... ---)
     *
     * @param string $content File content
     * @return array<string, mixed> Parsed metadata
     */
    protected function parseMarkdownFrontmatter(string $content): array
    {
        $metadata = [];

        // Check for YAML frontmatter (--- ... ---)
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
            $yamlContent = trim($matches[1]);
            $metadata = $this->parseYamlContent($yamlContent);
        }

        return $metadata;
    }

    /**
     * Parse YAML frontmatter from HTML file (<!-- --- ... --- -->)
     *
     * @param string $content File content
     * @return array<string, mixed> Parsed metadata
     */
    protected function parseHtmlFrontmatter(string $content): array
    {
        $metadata = [];

        // Check for YAML frontmatter within HTML comment (<!-- --- ... --- -->)
        if (preg_match('/^<!--\s*\n---\s*\n(.*?)\n---\s*\n-->\s*\n/s', $content, $matches)) {
            $yamlContent = trim($matches[1]);
            $metadata = $this->parseYamlContent($yamlContent);
        }

        return $metadata;
    }

    /**
     * Parse YAML-format content into metadata array
     *
     * @param string $yamlContent YAML format content
     * @return array<string, mixed> Parsed metadata
     */
    protected function parseYamlContent(string $yamlContent): array
    {
        if (empty($yamlContent)) {
            return [];
        }

        try {
            $metadata = Yaml::parse($yamlContent);

            // Ensure we return an array (YAML can parse to null or other types)
            if (!is_array($metadata)) {
                $this->logger->log('WARNING', 'YAML frontmatter did not parse to array');
                return [];
            }

            return $metadata;
        } catch (\Exception $e) {
            $this->logger->log('ERROR', 'Failed to parse YAML frontmatter: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate URL from file path and metadata
     *
     * @param string $filePath Path to source file
     * @param array<string, mixed> $metadata File metadata
     * @return string Generated URL path
     */
    protected function generateUrl(string $filePath, array $metadata): string
    {
        $sourceDir = $this->container->getVariable('SOURCE_DIR') ?? 'content';

        // Remove source directory prefix
        $relativePath = str_replace($sourceDir . '/', '', $filePath);

        // Change extension to .html
        $relativePath = preg_replace('/\.(md|html)$/', '.html', $relativePath);

        // If file has a category, add category subdirectory
        if (isset($metadata['category'])) {
            $category = $metadata['category'];
            $categorySlug = $this->slugify($category);

            // Get filename from path
            $filename = basename($relativePath);

            // If file is already in a directory, keep that structure
            // Otherwise, add category as subdirectory
            if (strpos($relativePath, '/') === false) {
                $relativePath = $categorySlug . '/' . $filename;
            }
        }

        // Return relative path (no leading slash) for compatibility with <base> tag
        return ltrim($relativePath, '/');
    }

    /**
     * Convert string to URL-safe slug
     *
     * @param string $text Text to slugify
     * @return string URL-safe slug
     */
    protected function slugify(string $text): string
    {
        // Convert to lowercase
        $slug = strtolower($text);

        // Replace spaces and underscores with hyphens
        $slug = str_replace([' ', '_'], '-', $slug);

        // Remove special characters
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);

        // Remove consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);

        // Trim hyphens from ends
        $slug = trim($slug, '-');

        return $slug;
    }
}
