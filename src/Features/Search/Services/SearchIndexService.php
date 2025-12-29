<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Search\Services;

use EICC\Utils\Container;
use EICC\Utils\Log;

class SearchIndexService
{
    private Log $logger;
    private array $documents = [];
    private int $idCounter = 1;

    public function __construct(Log $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Collect page data for the search index
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function collectPage(Container $container, array $parameters): array
    {
        $metadata = $parameters['metadata'] ?? [];
        $outputPath = $parameters['output_path'] ?? null;
        $content = $parameters['rendered_content'] ?? '';

        // 1. Check if page should be indexed
        if (!$this->shouldIndex($container, $parameters)) {
            return $parameters;
        }

        // 2. Calculate URL
        $url = $this->calculateUrl($container, $outputPath);

        // 3. Extract text content
        // Strip HTML tags to get raw text
        // We decode entities to make "StaticForge &amp; Friends" searchable as "StaticForge & Friends"
        $textContent = html_entity_decode(strip_tags($content));

        // Normalize whitespace
        $textContent = preg_replace('/\s+/', ' ', $textContent);
        $textContent = trim($textContent);

        // 4. Create document
        $doc = [
            'id' => $this->idCounter++,
            'title' => $metadata['title'] ?? 'Untitled',
            'text' => mb_substr($textContent, 0, 5000), // Limit text length to keep index size sane
            'url' => $url,
            'tags' => implode(' ', $metadata['tags'] ?? []),
            'category' => $metadata['category'] ?? '',
        ];

        $this->documents[] = $doc;

        return $parameters;
    }

    /**
     * Build the search index and write it to disk
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function buildIndex(Container $container, array $parameters): array
    {
        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            $this->logger->log('ERROR', 'OUTPUT_DIR not set, cannot write search index');
            return $parameters;
        }

        $this->logger->log('INFO', 'Building search index with ' . count($this->documents) . ' documents');

        // Write search.json
        $json = json_encode($this->documents, JSON_PRETTY_PRINT);
        if ($json === false) {
            $this->logger->log('ERROR', 'Failed to encode search index to JSON');
            return $parameters;
        }

        $indexPath = $outputDir . '/search.json';
        if (file_put_contents($indexPath, $json) === false) {
            $this->logger->log('ERROR', "Failed to write search index to {$indexPath}");
        }

        return $parameters;
    }

    private function shouldIndex(Container $container, array $parameters): bool
    {
        $metadata = $parameters['metadata'] ?? [];
        $outputPath = $parameters['output_path'] ?? '';

        // Check frontmatter exclusion
        if (isset($metadata['search_index']) && $metadata['search_index'] === false) {
            return false;
        }

        // Check config exclusions
        $config = $container->getVariable('site_config')['search'] ?? [];
        $excludePaths = $config['exclude_paths'] ?? [];
        $excludeContentIn = $config['exclude_content_in'] ?? [];

        // Get relative path for checking
        $outputDir = $container->getVariable('OUTPUT_DIR');
        $relativePath = str_replace($outputDir, '', $outputPath);

        // Check exclude_paths (exact matches or starts with)
        foreach ($excludePaths as $exclude) {
            if (str_starts_with($relativePath, $exclude)) {
                return false;
            }
        }

        // Check exclude_content_in
        foreach ($excludeContentIn as $exclude) {
            if (str_starts_with($relativePath, $exclude)) {
                return false;
            }
        }

        return true;
    }

    private function calculateUrl(Container $container, string $outputPath): string
    {
        $siteUrl = rtrim($container->getVariable('SITE_BASE_URL') ?? '', '/');
        $outputDir = $container->getVariable('OUTPUT_DIR');
        $relativePath = str_replace($outputDir, '', $outputPath);

        return $siteUrl . $relativePath;
    }
}
