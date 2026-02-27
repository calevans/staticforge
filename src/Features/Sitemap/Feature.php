<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Sitemap;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\Sitemap\Services\SitemapService;
use EICC\Utils\Container;
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

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_RENDER' => ['method' => 'handlePostRender', 'priority' => 100],
        'POST_LOOP' => ['method' => 'handlePostLoop', 'priority' => 100]
    ];

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger = $this->container->get('logger');
        $this->service = new SitemapService($this->logger);
        $this->logger->log('INFO', 'Sitemap Feature registered');
    }

    /**
     * Collect URL from processed file
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostRender(Container $container, array $parameters): array
    {
        return $this->service->collectUrl($container, $parameters);
    }

    /**
     * Generate sitemap.xml file
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostLoop(Container $container, array $parameters): array
    {
        return $this->service->generateSitemap($container, $parameters);
    }
}

