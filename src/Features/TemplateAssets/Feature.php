<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\TemplateAssets;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\TemplateAssets\Services\TemplateAssetsService;
use EICC\Utils\Container;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'TemplateAssets';
    protected Log $logger;
    private TemplateAssetsService $service;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_LOOP' => ['method' => 'handlePostLoop', 'priority' => 100]
    ];

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger = $this->container->get('logger');
        $this->service = new TemplateAssetsService($this->logger);
        $this->logger->log('INFO', 'TemplateAssets Feature registered');
    }

    /**
     * Handle POST_LOOP event
     * Copies assets from template directory to output directory
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostLoop(Container $container, array $parameters): array
    {
        return $this->service->handlePostLoop($container, $parameters);
    }
}
