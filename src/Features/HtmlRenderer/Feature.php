<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\HtmlRenderer;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\ExtensionRegistry;
use EICC\StaticForge\Features\HtmlRenderer\Services\HtmlRendererService;
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
     */
    #[EventListener('RENDER', priority: 100)]
    public function handleRender(RenderEvent $event): void
    {
        $this->service->processHtmlFile($event);
    }
}
