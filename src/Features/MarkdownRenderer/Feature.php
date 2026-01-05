<?php

namespace EICC\StaticForge\Features\MarkdownRenderer;

use EICC\StaticForge\Core\BaseRendererFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\ExtensionRegistry;
use EICC\StaticForge\Core\AssetManager;
use EICC\StaticForge\Features\MarkdownRenderer\Services\MarkdownRendererService;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\StaticForge\Services\TemplateVariableBuilder;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Markdown Renderer Feature - processes .md files during RENDER event
 * Extracts INI frontmatter, converts Markdown to HTML, and applies templates
 */
class Feature extends BaseRendererFeature implements FeatureInterface
{
    protected string $name = 'MarkdownRenderer';
    protected Log $logger;
    private MarkdownRendererService $service;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'RENDER' => ['method' => 'handleRender', 'priority' => 100]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);

        // Get logger from container
        $this->logger = $container->get('logger');

        // Initialize dependencies
        $markdownProcessor = new MarkdownProcessor();
        $contentExtractor = new ContentExtractor();

        // Get AssetManager (optional)
        $assetManager = null;
        if ($container->has(AssetManager::class)) {
            $assetManager = $container->get(AssetManager::class);
        }

        $templateRenderer = new TemplateRenderer(
            new TemplateVariableBuilder(),
            $this->logger,
            $assetManager
        );

        // Initialize service
        $this->service = new MarkdownRendererService(
            $this->logger,
            $markdownProcessor,
            $contentExtractor,
            $templateRenderer
        );

        // Register .md extension for processing
        $extensionRegistry = $container->get(ExtensionRegistry::class);
        $extensionRegistry->registerExtension('.md');

        $this->logger->log('INFO', 'Markdown Renderer Feature registered');
    }

    /**
     * Handle RENDER event for Markdown files
     *
     * Called dynamically by EventManager when RENDER event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handleRender(Container $container, array $parameters): array
    {
        return $this->service->processMarkdownFile($container, $parameters);
    }
}
