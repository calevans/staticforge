<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Tags;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\Tags\Services\TagPageService;
use EICC\StaticForge\Features\Tags\Services\TagsService;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Tags Feature - extracts and organizes tag metadata from content files
 * Listens to POST_GLOB to collect tags, PRE_RENDER to add tag data to templates,
 * and POST_LOOP to generate tag archive pages
 */
class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'Tags';

    private TagsService $service;
    private TagPageService $pageService;
    private Log $logger;
    private Container $applicationContainer;

    public function __construct(
        Log $logger,
        TagsService $service,
        TagPageService $pageService,
        Container $applicationContainer
    ) {
        $this->logger = $logger;
        $this->service = $service;
        $this->pageService = $pageService;
        $this->applicationContainer = $applicationContainer;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger->log('INFO', 'Tags Feature registered');
    }

    #[EventListener('POST_GLOB', priority: 150)]
    public function handlePostGlob(Event $event): void
    {
        $this->service->handlePostGlob($event);
    }

    #[EventListener('PRE_RENDER', priority: 100)]
    public function handlePreRender(RenderEvent $event): void
    {
        if (!empty($event->extra['bypass_tag_defer'])) {
            return;
        }

        $this->service->handlePreRender($event);
    }

    #[EventListener('POST_LOOP', priority: 110)]
    public function generateTagPages(Event $event): void
    {
        $this->pageService->generateTagPages($this->applicationContainer);
    }
}
