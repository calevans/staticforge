<?php

namespace EICC\StaticForge\Features\Categories;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
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

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 250],
        'POST_RENDER' => ['method' => 'handlePostRender', 'priority' => 100]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);

        // Get logger from container
        $this->logger = $container->get('logger');

        $this->service = new CategoriesService($this->logger);

        $this->logger->log('INFO', 'Categories Feature registered');
    }

    /**
     * Handle POST_GLOB event to scan category files and store their templates
     *
     * Called dynamically by EventManager when POST_GLOB event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostGlob(Container $container, array $parameters): array
    {
        $this->service->processCategoryTemplates($container);
        return $parameters;
    }

    /**
     * Handle POST_RENDER event to modify output path based on category
     *
     * Called dynamically by EventManager when POST_RENDER event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostRender(Container $container, array $parameters): array
    {
        // Only process if there's metadata with a category
        $metadata = $parameters['metadata'] ?? [];
        $category = $metadata['category'] ?? null;

        if (!$category) {
            return $parameters;
        }

        // Get current output path
        $outputPath = $parameters['output_path'] ?? null;

        if (!$outputPath) {
            return $parameters;
        }

        // Modify output path to include category subdirectory
        $newOutputPath = $this->service->categorizeOutputPath($outputPath, $category);

        // Update the output path in parameters
        $parameters['output_path'] = $newOutputPath;

        return $parameters;
    }
}

