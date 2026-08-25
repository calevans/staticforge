<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Tags;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\Tags\Services\TagPageService;
use EICC\StaticForge\Features\Tags\Services\TagsService;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Tags Feature - extracts and organizes tag metadata from content files
 * Listens to POST_GLOB to collect tags, PRE_RENDER to add tag data to templates,
 * and POST_LOOP to generate tag archive pages
 */
class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'Tags';

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 150],
        'PRE_RENDER' => ['method' => 'handlePreRender', 'priority' => 100],
        'POST_LOOP' => ['method' => 'generateTagPages', 'priority' => 110]
    ];

    private TagsService $service;
    private TagPageService $pageService;
    private Log $logger;

    public function __construct(Log $logger, TagsService $service, TagPageService $pageService)
    {
        $this->logger = $logger;
        $this->service = $service;
        $this->pageService = $pageService;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger->log('INFO', 'Tags Feature registered');
    }

    /**
     * Handle POST_GLOB event - scan all discovered files for tags
     *
     * Called dynamically by EventManager when POST_GLOB event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostGlob(Container $container, array $parameters): array
    {
        return $this->service->handlePostGlob($container, $parameters);
    }

    /**
     * Handle PRE_RENDER event - add tag data to template parameters
     *
     * Called dynamically by EventManager when PRE_RENDER event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePreRender(Container $container, array $parameters): array
    {
        if (!empty($parameters['bypass_tag_defer'])) {
            return $parameters;
        }

        return $this->service->handlePreRender($container, $parameters);
    }

    /**
     * Handle POST_LOOP event - generate tag archive pages
     *
     * Called dynamically by EventManager when POST_LOOP event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function generateTagPages(Container $container, array $parameters): array
    {
        $this->pageService->generateTagPages($container);
        return $parameters;
    }
}
