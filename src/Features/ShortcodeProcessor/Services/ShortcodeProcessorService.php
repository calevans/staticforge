<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\ShortcodeProcessor\Services;

use EICC\StaticForge\Shortcodes\ShortcodeManager;
use EICC\Utils\Container;
use EICC\Utils\Log;

class ShortcodeProcessorService
{
    private Log $logger;
    private ShortcodeManager $shortcodeManager;

    public function __construct(Log $logger, ShortcodeManager $shortcodeManager)
    {
        $this->logger = $logger;
        $this->shortcodeManager = $shortcodeManager;
    }

    /**
     * Register default reference shortcodes
     */
    public function registerReferenceShortcodes(): void
    {
        $this->shortcodeManager->register(new \EICC\StaticForge\Shortcodes\YoutubeShortcode());
        $this->shortcodeManager->register(new \EICC\StaticForge\Shortcodes\AlertShortcode());
        $this->shortcodeManager->register(new \EICC\StaticForge\Shortcodes\WeatherShortcode());
    }

    /**
     * Process shortcodes in content during PRE_RENDER
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function processShortcodes(Container $container, array $parameters): array
    {
        $filePath = $parameters['file_path'] ?? null;

        // Only process .md files (or others if needed)
        // Shortcodes are primarily for Markdown content.
        if (!$filePath || pathinfo($filePath, PATHINFO_EXTENSION) !== 'md') {
            return $parameters;
        }

        $this->logger->log('DEBUG', "Processing shortcodes for: {$filePath}");

        // Get content
        // If file_content is already set (by another feature), use it.
        // Otherwise read from file.
        $content = $parameters['file_content'] ?? @file_get_contents($filePath);

        if ($content === false) {
            return $parameters;
        }

        // Split frontmatter and body to avoid processing shortcodes in frontmatter
        $parts = $this->splitFrontmatter($content);
        $frontmatter = $parts['frontmatter'];
        $body = $parts['body'];

        // Process shortcodes in body
        $processedBody = $this->shortcodeManager->process($body);

        // Reconstruct content
        $newContent = $frontmatter . $processedBody;

        // Update parameters
        $parameters['file_content'] = $newContent;

        return $parameters;
    }

    /**
     * Split content into frontmatter and body
     *
     * @param string $content
     * @return array{frontmatter: string, body: string}
     */
    private function splitFrontmatter(string $content): array
    {
        // Check for INI frontmatter (--- ... ---)
        if (preg_match('/^(---\s*\n.*?\n---\s*\n)(.*)$/s', $content, $matches)) {
            return [
                'frontmatter' => $matches[1],
                'body' => $matches[2]
            ];
        }

        return [
            'frontmatter' => '',
            'body' => $content
        ];
    }
}
