<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\MarkdownRenderer;

use EICC\StaticForge\Core\BaseRendererFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\ExtensionRegistry;
use EICC\StaticForge\Features\MarkdownRenderer\Services\MarkdownRendererService;
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

    #[EventListener('RENDER', priority: 100)]
    public function handleRender(RenderEvent $event): void
    {
        $this->service->processMarkdownFile($event);
    }
}
