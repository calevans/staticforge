<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\MarkdownRenderer;

use EICC\StaticForge\Core\BaseRendererFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\ExtensionRegistry;
use EICC\StaticForge\Features\MarkdownRenderer\Services\MarkdownRendererService;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Markdown Renderer Feature - processes .md files during RENDER event
 * Extracts YAML frontmatter, converts Markdown to HTML, and applies templates
 */
class Feature extends BaseRendererFeature implements FeatureInterface
{
    protected string $name = 'MarkdownRenderer';
    protected Log $logger;
    private MarkdownRendererService $service;
    private ExtensionRegistry $extensionRegistry;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'RENDER' => ['method' => 'handleRender', 'priority' => 100]
    ];

    public function __construct(Log $logger, MarkdownRendererService $service, ExtensionRegistry $extensionRegistry)
    {
        $this->logger = $logger;
        $this->service = $service;
        $this->extensionRegistry = $extensionRegistry;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);

        // Register .md extension for processing
        $this->extensionRegistry->registerExtension('.md');

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
