<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Sitemap;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\Sitemap\Services\SitemapService;
use EICC\Utils\Log;

/**
 * Sitemap Feature - generates sitemap.xml
 * Listens to POST_RENDER to collect URLs, then POST_LOOP to generate the file
 */
class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'Sitemap';
    protected Log $logger;
    private SitemapService $service;

    public function __construct(Log $logger, SitemapService $service)
    {
        $this->logger = $logger;
        $this->service = $service;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger->log('INFO', 'Sitemap Feature registered');
    }

    #[EventListener('POST_RENDER', priority: 100)]
    public function handlePostRender(RenderEvent $event): void
    {
        $this->service->collectUrl($event);
    }

    #[EventListener('POST_LOOP', priority: 100)]
    public function handlePostLoop(Event $event): void
    {
        $this->service->generateSitemap();
    }
}
