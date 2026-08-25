<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\ShortcodeProcessor;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\ShortcodeProcessor\Services\ShortcodeProcessorService;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'ShortcodeProcessor';
    protected Log $logger;
    private ShortcodeProcessorService $service;

    public function __construct(Log $logger, ShortcodeProcessorService $service)
    {
        $this->logger = $logger;
        $this->service = $service;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->service->registerReferenceShortcodes();
        $this->logger->log('INFO', 'ShortcodeProcessor Feature registered');
    }

    /**
     * Handle PRE_RENDER event
     */
    #[EventListener('PRE_RENDER', priority: 50)]
    public function handlePreRender(RenderEvent $event): void
    {
        $this->service->processShortcodes($event);
    }
}
