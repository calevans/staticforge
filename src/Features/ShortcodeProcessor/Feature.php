<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\ShortcodeProcessor;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\ShortcodeProcessor\Services\ShortcodeProcessorService;
use EICC\Utils\Container;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'ShortcodeProcessor';
    protected Log $logger;
    private ShortcodeProcessorService $service;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'PRE_RENDER' => ['method' => 'handlePreRender', 'priority' => 50]
    ];

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
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePreRender(Container $container, array $parameters): array
    {
        return $this->service->processShortcodes($container, $parameters);
    }
}
