<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\TableOfContents;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\TableOfContents\Services\TableOfContentsService;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'TableOfContents';
    protected Log $logger;
    private TableOfContentsService $service;

    public function __construct(Log $logger, TableOfContentsService $service)
    {
        $this->logger = $logger;
        $this->service = $service;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger->log('INFO', 'TableOfContents Feature registered');
    }

    #[EventListener('MARKDOWN_CONVERTED', priority: 500)]
    public function handleMarkdownConverted(RenderEvent $event): void
    {
        $this->service->handleMarkdownConverted($event);
    }
}
