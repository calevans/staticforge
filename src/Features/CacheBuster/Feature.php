<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\CacheBuster;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\CacheBuster\Services\CacheBusterService;
use EICC\Utils\Container;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'CacheBuster';
    protected Log $logger;
    private CacheBusterService $service;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'CREATE' => ['method' => 'handleCreate', 'priority' => 10]
    ];

    public function __construct(Log $logger, CacheBusterService $service)
    {
        $this->logger = $logger;
        $this->service = $service;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger->log('INFO', 'CacheBuster Feature registered');
    }

    /**
     * Handle CREATE event - set build_id
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handleCreate(Container $container, array $parameters): array
    {
        // Generate build ID via service
        $buildId = $this->service->generateBuildId();

        // Set in container
        $container->setVariable('build_id', $buildId);
        $container->setVariable('cache_buster', 'sfcb=' . $buildId);

        return $parameters;
    }
}
