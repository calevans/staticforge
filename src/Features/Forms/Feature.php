<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Forms;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\Forms\Services\FormsService;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface, ConfigurableFeatureInterface
{
    protected string $name = 'Forms';
    protected Log $logger;
    private FormsService $service;

    public function getRequiredConfig(): array
    {
        return ['forms'];
    }

    public function getRequiredEnv(): array
    {
        return [];
    }

    public function __construct(Log $logger, FormsService $service)
    {
        $this->logger = $logger;
        $this->service = $service;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger->log('INFO', 'Forms Feature registered');
    }

    /**
     * Handle RENDER event
     * Replaces form shortcodes with HTML forms
     */
    #[EventListener('RENDER', priority: 50)]
    public function handleRender(RenderEvent $event): void
    {
        $this->service->processForms($event);
    }
}
