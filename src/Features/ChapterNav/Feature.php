<?php

namespace EICC\StaticForge\Features\ChapterNav;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\ChapterNav\Services\ChapterNavService;
use EICC\Utils\Container;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'ChapterNav';
    private Log $logger;
    private ChapterNavService $service;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 150]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);
        $this->logger = $container->get('logger');

        $this->service = new ChapterNavService($this->logger);
    }

    /**
     * Handle POST_GLOB event - build chapter navigation from menu configuration
     *
     * Called dynamically by EventManager when POST_GLOB event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostGlob(Container $container, array $parameters): array
    {
        if (!$this->requireFeatures(['MenuBuilder'])) {
            return $parameters;
        }

        return $this->service->processChapterNavigation($container, $parameters);
    }
}
