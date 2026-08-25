<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Categories;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\Categories\Services\CategoriesService;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Categories Feature - organizes content into category subdirectories
 * Listens to POST_RENDER to modify output paths based on category metadata
 */
class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'Categories';
    protected Log $logger;
    private CategoriesService $service;
    private Container $applicationContainer;

    public function __construct(Log $logger, CategoriesService $service, Container $applicationContainer)
    {
        $this->logger = $logger;
        $this->service = $service;
        $this->applicationContainer = $applicationContainer;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger->log('INFO', 'Categories Feature registered');
    }

    /**
     * Handle POST_GLOB event to scan category files and store their templates
     */
    #[EventListener('POST_GLOB', priority: 250)]
    public function handlePostGlob(Event $event): void
    {
        $this->service->processCategoryTemplates($this->applicationContainer);
    }

    /**
     * Handle PRE_RENDER event to pre-compute the categorized output path before
     * RENDER runs, so FileProcessor's incremental-build cache check can compare
     * against the actual file that will be written, not the un-categorized path.
     */
    #[EventListener('PRE_RENDER', priority: 200)]
    public function handlePreRender(RenderEvent $event): void
    {
        $category = $event->metadata['category'] ?? null;
        $filePath = $event->filePath;

        if (!$category || $filePath === '') {
            return;
        }

        $uncategorizedOutputPath = $this->service->calculateUncategorizedOutputPath(
            $filePath,
            $this->applicationContainer
        );
        $event->extra['expected_output_path'] = $this->service->categorizeOutputPath(
            $uncategorizedOutputPath,
            $category
        );
    }

    /**
     * Handle POST_RENDER event to modify output path based on category
     */
    #[EventListener('POST_RENDER', priority: 100)]
    public function handlePostRender(RenderEvent $event): void
    {
        $category = $event->metadata['category'] ?? null;

        if (!$category || $event->outputPath === null) {
            return;
        }

        $event->outputPath = $this->service->categorizeOutputPath($event->outputPath, $category);
    }
}
