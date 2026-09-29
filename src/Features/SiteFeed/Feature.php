<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\SiteFeed;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Features\SiteFeed\Services\FeedConfig;
use EICC\StaticForge\Features\SiteFeed\Services\SiteFeedCollector;
use EICC\StaticForge\Features\SiteFeed\Services\SiteFeedWriter;
use EICC\Utils\Log;

/**
 * SiteFeed - site-wide RSS 2.0, Atom 1.0 and JSON Feed 1.1 (and optional
 * category Atom/JSON) behind the `feed:` config key. Inert unless
 * `feed.enabled` is true. Category rss.xml belongs to RssFeed and is not touched.
 */
class Feature extends BaseFeature implements FeatureInterface, ConfigurableFeatureInterface
{
    protected string $name = 'SiteFeed';

    public function __construct(
        private readonly Log $logger,
        private readonly SiteFeedCollector $collector,
        private readonly SiteFeedWriter $writer,
        private readonly FeedConfig $config,
    ) {
    }

    public function getRequiredConfig(): array
    {
        return [];
    }

    public function getRequiredEnv(): array
    {
        return ['SITE_BASE_URL'];
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger->log('INFO', 'SiteFeed Feature registered');
    }

    #[EventListener('PRE_LOOP', priority: 100)]
    public function handlePreLoop(Event $event): void
    {
        // One settings instance per build, so each config warning is logged once
        $this->config->reset();
        $this->collector->useConfig($this->config);
        $this->writer->useConfig($this->config);
        $this->collector->reset();
        $this->writer->prepare();
    }

    #[EventListener('POST_RENDER', priority: 120)]
    public function handlePostRender(RenderEvent $event): void
    {
        $this->collector->collect($event);
    }

    #[EventListener('POST_LOOP', priority: 95)]
    public function handlePostLoop(Event $event): void
    {
        $this->writer->write($this->collector->getCandidates());
    }
}
