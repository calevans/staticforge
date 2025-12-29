<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\RssFeed;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\RssFeed\Services\RssFeedService;
use EICC\Utils\Container;
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

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_RENDER' => ['method' => 'handlePostRender', 'priority' => 110],
        'POST_LOOP' => ['method' => 'handlePostLoop', 'priority' => 90]
    ];

    public function getRequiredConfig(): array
    {
        return ['site.name'];
    }

    public function getRequiredEnv(): array
    {
        return ['SITE_BASE_URL'];
    }

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);

        // Get logger from container
        $this->logger = $container->get('logger');

        // Initialize services
        $this->service = new RssFeedService($this->logger, $eventManager);

        $this->logger->log('INFO', 'RssFeed Feature registered');
    }

    /**
     * Collect files that have categories during POST_RENDER
     *
     * Called dynamically by EventManager when POST_RENDER event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostRender(Container $container, array $parameters): array
    {
        return $this->service->collectCategoryFiles($container, $parameters);
    }

    /**
     * Generate RSS feeds for all categories during POST_LOOP
     *
     * Called dynamically by EventManager when POST_LOOP event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostLoop(Container $container, array $parameters): array
    {
        return $this->service->generateRssFeeds($container, $parameters);
    }
}
