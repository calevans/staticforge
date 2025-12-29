<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Search;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\Search\Services\SearchIndexService;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Search Feature - generates search.json and assets
 * Listens to POST_RENDER to collect page data, then POST_LOOP to generate the index
 */
class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'Search';
    protected Log $logger;
    private SearchIndexService $service;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_RENDER' => ['method' => 'handlePostRender', 'priority' => 100],
        'POST_LOOP' => ['method' => 'handlePostLoop', 'priority' => 100]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);
        $this->logger = $container->get('logger');
        $this->service = new SearchIndexService($this->logger);
        $this->logger->log('INFO', 'Search Feature registered');
    }

    /**
     * Collect page data for search index
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostRender(Container $container, array $parameters): array
    {
        return $this->service->collectPage($container, $parameters);
    }

    /**
     * Generate search.json and copy assets
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostLoop(Container $container, array $parameters): array
    {
        return $this->service->buildIndex($container, $parameters);
    }
}
