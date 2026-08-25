<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\ShortcodeProcessor\Services;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\PathGuard;
use EICC\StaticForge\Shortcodes\ShortcodeManager;
use EICC\Utils\Container;
use EICC\Utils\Log;

class ShortcodeProcessorService
{
    private Log $logger;
    private ShortcodeManager $shortcodeManager;
    private Container $container;

    public function __construct(Log $logger, ShortcodeManager $shortcodeManager, Container $container)
    {
        $this->logger = $logger;
        $this->shortcodeManager = $shortcodeManager;
        $this->container = $container;
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
     * Process shortcodes in content during PRE_RENDER, mutating $event in place.
     */
    public function processShortcodes(RenderEvent $event): void
    {
        $filePath = $event->filePath;

        // Only process .md files (or others if needed)
        // Shortcodes are primarily for Markdown content.
        if ($filePath === '' || pathinfo($filePath, PATHINFO_EXTENSION) !== 'md') {
            return;
        }

        $this->logger->log('DEBUG', "Processing shortcodes for: {$filePath}");

        // Get content
        // If file_content is already set (by another feature), use it.
        // Otherwise read from file.
        if (isset($event->extra['file_content'])) {
            $content = $event->extra['file_content'];
        } else {
            // Security: Validate that the file path is within the source directory
            $sourceDir = $this->container->getVariable('SOURCE_DIR');
            if (!$sourceDir) {
                throw new \RuntimeException('SOURCE_DIR not set in container');
            }

            try {
                $realFilePath = PathGuard::resolveInside($filePath, $sourceDir);
            } catch (\RuntimeException $e) {
                throw new \RuntimeException(
                    "Security Error: File path is outside the allowed source directory: {$filePath}"
                );
            }

            if (!is_readable($realFilePath)) {
                $this->logger->log('WARNING', "Failed to read file (unreadable): {$filePath}");
                return;
            }

            $content = file_get_contents($realFilePath);
            if ($content === false) {
                $this->logger->log('WARNING', "Failed to read file: {$filePath}");
                return;
            }
        }

        // Split frontmatter and body to avoid processing shortcodes in frontmatter
        $parts = $this->splitFrontmatter($content);
        $frontmatter = $parts['frontmatter'];
        $body = $parts['body'];

        // Process shortcodes in body
        $processedBody = $this->shortcodeManager->process($body);

        // Reconstruct content
        $newContent = $frontmatter . $processedBody;

        // Update event
        $event->extra['file_content'] = $newContent;
    }

    /**
     * Split content into frontmatter and body
     *
     * @param string $content
     * @return array{frontmatter: string, body: string}
     */
    private function splitFrontmatter(string $content): array
    {
        // Check for YAML frontmatter (--- ... ---)
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
