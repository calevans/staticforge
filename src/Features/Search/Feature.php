<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Search;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\Search\Services\SearchIndexService;
use EICC\StaticForge\Features\Search\Services\SearchAssetService;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Search Feature - generates search.json and assets
 * Listens to POST_RENDER to collect page data, then POST_LOOP to generate the index
 */
class Feature extends BaseFeature implements FeatureInterface, ConfigurableFeatureInterface
{
    protected string $name = 'Search';
    protected Log $logger;
    private SearchIndexService $service;
    private SearchAssetService $assetService;
    private Container $applicationContainer;

    public function getRequiredConfig(): array
    {
        return ['search'];
    }

    public function getRequiredEnv(): array
    {
        return [];
    }

    public function getConfigHelp(string $key): ?string
    {
        if ($key === 'search') {
            return <<<YAML
search:
  # 'minisearch' (default) or 'fuse'
  engine: minisearch
YAML;
        }
        return null;
    }

    public function __construct(
        Log $logger,
        SearchIndexService $service,
        SearchAssetService $assetService,
        Container $applicationContainer
    ) {
        $this->logger = $logger;
        $this->service = $service;
        $this->assetService = $assetService;
        $this->applicationContainer = $applicationContainer;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger->log('INFO', 'Search Feature registered');
    }

    #[EventListener('POST_RENDER', priority: 100)]
    public function handlePostRender(RenderEvent $event): void
    {
        $this->service->collectPage($event);
    }

    #[EventListener('POST_LOOP', priority: 100)]
    public function handlePostLoop(Event $event): void
    {
        $this->assetService->copyAssets($this->applicationContainer);
        $this->service->buildIndex();
    }
}
