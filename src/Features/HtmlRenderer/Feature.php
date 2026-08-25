<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\HtmlRenderer;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\ExtensionRegistry;
use EICC\StaticForge\Features\HtmlRenderer\Services\HtmlRendererService;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * HTML Renderer Feature - processes .html files during RENDER event
 * Extracts frontmatter metadata (YAML, or INI-tagged HTML comment), processes content, and writes output files
 */
class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'HtmlRenderer';
    protected Log $logger;
    private HtmlRendererService $service;
    private ExtensionRegistry $extensionRegistry;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'RENDER' => ['method' => 'handleRender', 'priority' => 100]
    ];

    public function __construct(Log $logger, HtmlRendererService $service, ExtensionRegistry $extensionRegistry)
    {
        $this->logger = $logger;
        $this->service = $service;
        $this->extensionRegistry = $extensionRegistry;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);

        // Register .html extension for processing
        $this->extensionRegistry->registerExtension('.html');

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
