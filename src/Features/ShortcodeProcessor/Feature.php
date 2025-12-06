<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\ShortcodeProcessor;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\StaticForge\Shortcodes\ShortcodeManager;
use EICC\Utils\Container;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'ShortcodeProcessor';
    protected Log $logger;
    private ShortcodeManager $shortcodeManager;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'PRE_RENDER' => ['method' => 'handlePreRender', 'priority' => 50]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);
        $this->logger = $container->get('logger');

        // Initialize ShortcodeManager
        // We need TemplateRenderer. It might not be in the container yet?
        // TemplateRenderer is usually instantiated in features.
        // Let's instantiate a new one or check if it's in container.
        // The container usually doesn't have TemplateRenderer as a shared service.
        // MarkdownRenderer creates its own.
        // We should probably create one.

        // We need TemplateVariableBuilder too.
        $templateVariableBuilder = new \EICC\StaticForge\Services\TemplateVariableBuilder();
        $templateRenderer = new TemplateRenderer($templateVariableBuilder, $this->logger);

        $this->shortcodeManager = new ShortcodeManager($container, $templateRenderer);

        // Register ShortcodeManager in container for other features to use
        $container->add(ShortcodeManager::class, $this->shortcodeManager);

        // Register reference shortcodes
        // We will do this in a separate method or here.
        // For now, we haven't created them yet.
        // I'll add a TODO or call a method that I'll implement later.
        $this->registerReferenceShortcodes();

        $this->logger->log('INFO', 'ShortcodeProcessor Feature registered');
    }

    private function registerReferenceShortcodes(): void
    {
        $this->shortcodeManager->register(new \EICC\StaticForge\Shortcodes\YoutubeShortcode());
        $this->shortcodeManager->register(new \EICC\StaticForge\Shortcodes\AlertShortcode());
        $this->shortcodeManager->register(new \EICC\StaticForge\Shortcodes\WeatherShortcode());
    }

    /**
     * Handle PRE_RENDER event
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePreRender(Container $container, array $parameters): array
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
