<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\MenuBuilder;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\MenuBuilder\Services\MenuBuilderService;
use EICC\Utils\Container;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface, ConfigurableFeatureInterface
{
    protected string $name = 'MenuBuilder';
    private Log $logger;
    private MenuBuilderService $service;
    private Container $applicationContainer;

    public function getRequiredConfig(): array
    {
        return ['menu'];
    }

    public function getRequiredEnv(): array
    {
        return [];
    }

    public function __construct(Log $logger, MenuBuilderService $service, Container $applicationContainer)
    {
        $this->logger = $logger;
        $this->service = $service;
        $this->applicationContainer = $applicationContainer;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger->log('INFO', 'MenuBuilder Feature registered');
    }

    #[EventListener('POST_GLOB', priority: 100)]
    public function handlePostGlob(Event $event): void
    {
        $this->service->buildMenus($this->applicationContainer);
    }
}
