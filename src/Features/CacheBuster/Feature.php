<?php

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

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);
        $this->logger = $container->get('logger');

        $this->service = new CacheBusterService($this->logger);

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

        return $parameters;
    }
}
