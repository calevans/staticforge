<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\TemplateAssets;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Features\TemplateAssets\Services\TemplateAssetsService;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'TemplateAssets';
    protected Log $logger;
    private TemplateAssetsService $service;

    public function __construct(Log $logger, TemplateAssetsService $service)
    {
        $this->logger = $logger;
        $this->service = $service;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger->log('INFO', 'TemplateAssets Feature registered');
    }

    #[EventListener('POST_LOOP', priority: 100)]
    public function handlePostLoop(Event $event): void
    {
        $this->service->handlePostLoop();
    }
}
