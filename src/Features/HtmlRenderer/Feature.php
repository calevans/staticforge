<?php

namespace EICC\StaticForge\Features\HtmlRenderer;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\ExtensionRegistry;
use EICC\StaticForge\Core\AssetManager;
use EICC\StaticForge\Features\HtmlRenderer\Services\HtmlRendererService;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\StaticForge\Services\TemplateVariableBuilder;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * HTML Renderer Feature - processes .html files during RENDER event
 * Extracts INI metadata, processes content, and writes output files
 */
class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'HtmlRenderer';
    protected Log $logger;
    private HtmlRendererService $service;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'RENDER' => ['method' => 'handleRender', 'priority' => 100]
    ];

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);

        // Get logger from container
        $this->logger = $this->container->get('logger');

        // Get AssetManager (optional)
        $assetManager = null;
        if ($this->container->has(AssetManager::class)) {
            $assetManager = $this->container->get(AssetManager::class);
        }

        // Initialize helpers
        $templateRenderer = new TemplateRenderer(
            new TemplateVariableBuilder(),
            $this->logger,
            $assetManager
        );

        $this->service = new HtmlRendererService($this->logger, $templateRenderer);

        // Register .html extension for processing
        $extensionRegistry = $this->container->get(ExtensionRegistry::class);
        $extensionRegistry->registerExtension('.html');

        $this->logger->log('INFO', 'HTML Renderer Feature registered');
    }

    /**
     * Handle RENDER event for HTML files
     *
     * Called dynamically by EventManager when RENDER event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handleRender(Container $container, array $parameters): array
    {
        return $this->service->processHtmlFile($container, $parameters);
    }
}
