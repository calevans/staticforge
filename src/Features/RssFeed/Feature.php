<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\RssFeed;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\RssFeed\Services\RssFeedService;
use EICC\Utils\Log;

/**
 * RSS Feed Feature - generates category-based RSS feed files
 * Listens to POST_RENDER to collect category files, then POST_LOOP to generate feeds
 */
class Feature extends BaseFeature implements FeatureInterface, ConfigurableFeatureInterface
{
    protected string $name = 'RssFeed';
    protected Log $logger;
    private RssFeedService $service;

    public function getRequiredConfig(): array
    {
        return ['site.name'];
    }

    public function getRequiredEnv(): array
    {
        return ['SITE_BASE_URL'];
    }

    public function __construct(Log $logger, RssFeedService $service)
    {
        $this->logger = $logger;
        $this->service = $service;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger->log('INFO', 'RssFeed Feature registered');
    }

    #[EventListener('POST_RENDER', priority: 110)]
    public function handlePostRender(RenderEvent $event): void
    {
        $this->service->collectCategoryFiles($event);
    }

    #[EventListener('POST_LOOP', priority: 90)]
    public function handlePostLoop(Event $event): void
    {
        $this->service->generateRssFeeds();
    }
}
