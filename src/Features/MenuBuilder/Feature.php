<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\MenuBuilder;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\MenuBuilder\Services\MenuBuilderService;
use EICC\Utils\Container;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface, ConfigurableFeatureInterface
{
    protected string $name = 'MenuBuilder';
    private Log $logger;
    private MenuBuilderService $service;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 100]
    ];

    public function getRequiredConfig(): array
    {
        return ['menu'];
    }

    public function getRequiredEnv(): array
    {
        return [];
    }

    public function __construct(Log $logger, MenuBuilderService $service)
    {
        $this->logger = $logger;
        $this->service = $service;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);

        // Register new event for other features to inject menu items
        $eventManager->registerEvent('COLLECT_MENU_ITEMS');

        $this->logger->log('INFO', 'MenuBuilder Feature registered');
    }

    /**
     * Handle POST_GLOB event - build menu structure from discovered files
     *
     * Called dynamically by EventManager when POST_GLOB event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostGlob(Container $container, array $parameters): array
    {
        return $this->service->buildMenus($container, $parameters);
    }
}
