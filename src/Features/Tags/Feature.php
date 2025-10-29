<?php

namespace EICC\StaticForge\Features\Tags;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;

/**
 * Tags Feature - extracts and organizes tag metadata from content files
 * Listens to POST_GLOB to collect tags, PRE_RENDER to add tag data to templates
 */
class Feature extends BaseFeature implements FeatureInterface
{
  protected string $name = 'Tags';

  protected array $eventListeners = [
    'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 150],
    'PRE_RENDER' => ['method' => 'handlePreRender', 'priority' => 100]
  ];

  private array $allTags = [];
  private array $tagIndex = []; // tag => [file paths]
  private $logger;

  public function register(EventManager $eventManager, Container $container): void
  {
    parent::register($eventManager, $container);

    // Get logger from container
    $this->logger = $container->getVariable('logger');

    $this->logger->log('INFO', 'Tags Feature registered');
  }

  /**
   * Handle POST_GLOB event - scan all discovered files for tags
   */
  public function handlePostGlob(Container $container, array $parameters): array
  {
    $discoveredFiles = $container->getVariable('discovered_files') ?? [];

    foreach ($discoveredFiles as $filePath) {
      $this->extractTagsFromFile($filePath);
    }

    // Store tag data in features array
    if (!isset($parameters['features'])) {
      $parameters['features'] = [];
    }

    $parameters['features'][$this->getName()] = [
      'all_tags' => $this->getAllTagsSorted(),
      'tag_index' => $this->tagIndex,
      'tag_counts' => $this->getTagCounts()
    ];

    $this->logger->log('INFO', 'Collected ' . count($this->allTags) . ' unique tags from ' . count($discoveredFiles) . ' files');

    return $parameters;
  }

  /**
   * Handle PRE_RENDER event - add tag data to template parameters
   */
  public function handlePreRender(Container $container, array $parameters): array
  {
    $filePath = $parameters['file_path'] ?? '';
    $metadata = $parameters['metadata'] ?? [];

    // Add tag-specific data to parameters for template rendering
    $fileTags = $metadata['tags'] ?? [];

    // Normalize tags to array if it's a single string
    if (is_string($fileTags)) {
      $fileTags = [$fileTags];
    }

    // Get related files by tags (files that share tags)
    $relatedFiles = $this->getRelatedFilesByTags($filePath, $fileTags);

    $parameters['tag_data'] = [
      'tags' => $fileTags,
      'related_files' => $relatedFiles,
      'all_tags' => $this->getAllTagsSorted(),
      'tag_counts' => $this->getTagCounts()
    ];

    return $parameters;
  }

  /**
   * Extract tags from a content file
   */
  private function extractTagsFromFile(string $filePath): void
  {
    if (!file_exists($filePath)) {
      return;
    }

    $content = file_get_contents($filePath);
    $extension = pathinfo($filePath, PATHINFO_EXTENSION);

    $tags = [];

    if ($extension === 'md') {
      $tags = $this->extractTagsFromMarkdown($content);
    } elseif ($extension === 'html') {
      $tags = $this->extractTagsFromHtml($content);
    }

    // Normalize tags (trim, lowercase)
    $tags = array_map(function($tag) {
      return strtolower(trim($tag));
    }, $tags);

    // Filter empty tags
    $tags = array_filter($tags);

    // Add to all tags collection
    foreach ($tags as $tag) {
      if (!in_array($tag, $this->allTags)) {
        $this->allTags[] = $tag;
      }

      // Add file to tag index
      if (!isset($this->tagIndex[$tag])) {
        $this->tagIndex[$tag] = [];
      }

      if (!in_array($filePath, $this->tagIndex[$tag])) {
        $this->tagIndex[$tag][] = $filePath;
      }
    }
  }

  /**
   * Extract tags from Markdown YAML frontmatter
   */
  private function extractTagsFromMarkdown(string $content): array
  {
    // Check for YAML frontmatter (--- ... ---)
    if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
      $yamlContent = trim($matches[1]);

      // Look for tags line in YAML
      if (preg_match('/^tags:\s*\[([^\]]+)\]/m', $yamlContent, $tagMatches)) {
        // Array format: tags: [tag1, tag2, tag3]
        $tagString = $tagMatches[1];
        $tags = array_map('trim', explode(',', $tagString));
        return $tags;
      } elseif (preg_match('/^tags:\s*$/m', $yamlContent)) {
        // List format: tags:\n  - tag1\n  - tag2
        $pattern = '/^tags:\s*\n((?:\s*-\s*.+\n?)+)/m';
        if (preg_match($pattern, $yamlContent, $listMatches)) {
          preg_match_all('/^\s*-\s*(.+)$/m', $listMatches[1], $itemMatches);
          return $itemMatches[1];
        }
      }
    }

    return [];
  }

  /**
   * Extract tags from HTML meta or frontmatter
   */
  private function extractTagsFromHtml(string $content): array
  {
    $tags = [];

    // Check for INI frontmatter block
    if (preg_match('/<!--\s*INI\s*\n(.*?)\n-->/s', $content, $matches)) {
      $iniContent = $matches[1];

      // Look for tags line
      if (preg_match('/^tags:\s*(.+)$/m', $iniContent, $tagMatches)) {
        $tagString = trim($tagMatches[1]);

        // Remove brackets if present
        $tagString = trim($tagString, '[]');

        $tags = array_map('trim', explode(',', $tagString));
      }
    }

    // Also check for meta tags
    if (preg_match_all('/<meta\s+name=["\']keywords["\']\s+content=["\']([^"\']+)["\']/i', $content, $matches)) {
      foreach ($matches[1] as $keywords) {
        $metaTags = array_map('trim', explode(',', $keywords));
        $tags = array_merge($tags, $metaTags);
      }
    }

    return $tags;
  }

  /**
   * Get all tags sorted alphabetically
   */
  private function getAllTagsSorted(): array
  {
    $sorted = $this->allTags;
    sort($sorted);
    return $sorted;
  }

  /**
   * Get count of files for each tag
   */
  private function getTagCounts(): array
  {
    $counts = [];
    foreach ($this->tagIndex as $tag => $files) {
      $counts[$tag] = count($files);
    }
    arsort($counts); // Sort by count descending
    return $counts;
  }

  /**
   * Get files related to the current file by shared tags
   */
  private function getRelatedFilesByTags(string $currentFile, array $tags): array
  {
    if (empty($tags)) {
      return [];
    }

    $relatedFiles = [];
    $scoredFiles = [];

    foreach ($tags as $tag) {
      $normalizedTag = strtolower(trim($tag));

      if (isset($this->tagIndex[$normalizedTag])) {
        foreach ($this->tagIndex[$normalizedTag] as $file) {
          if ($file === $currentFile) {
            continue; // Skip current file
          }

          // Score files by number of shared tags
          if (!isset($scoredFiles[$file])) {
            $scoredFiles[$file] = 0;
          }
          $scoredFiles[$file]++;
        }
      }
    }

    // Sort by score (most shared tags first)
    arsort($scoredFiles);

    // Return top related files (limit to 10)
    $relatedFiles = array_slice(array_keys($scoredFiles), 0, 10);

    return $relatedFiles;
  }
}
