<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\CacheBuster;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Features\CacheBuster\Services\CacheBusterService;
use EICC\Utils\Container;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'CacheBuster';
    protected Log $logger;
    private CacheBusterService $service;
    private Container $applicationContainer;

    public function __construct(Log $logger, CacheBusterService $service, Container $applicationContainer)
    {
        $this->logger = $logger;
        $this->service = $service;
        $this->applicationContainer = $applicationContainer;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger->log('INFO', 'CacheBuster Feature registered');
    }

    /**
     * Handle CREATE event - set build_id
     */
    #[EventListener('CREATE', priority: 10)]
    public function handleCreate(Event $event): void
    {
        $buildId = $this->service->generateBuildId();

        $this->applicationContainer->setVariable('build_id', $buildId);
        $this->applicationContainer->setVariable('cache_buster', 'sfcb=' . $buildId);
    }
}
