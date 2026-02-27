<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\RobotsTxt;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\RobotsTxt\Services\RobotsTxtGenerator;
use EICC\StaticForge\Features\RobotsTxt\Services\RobotsTxtService;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * RobotsTxt Feature - generates robots.txt file based on content metadata
 *
 * EVENTS FIRED: None
 *
 * EVENTS OBSERVED:
 * - POST_GLOB (priority 150): Scans discovered files for robots metadata
 * - POST_LOOP (priority 100): Generates robots.txt file at the end of processing
 *
 * Honors the "robots" field in content file frontmatter:
 * - robots=no: Disallow the page in robots.txt
 * - robots=yes or not specified: Allow the page (default)
 *
 * Also honors robots field in category definition files to disallow entire categories
 */
class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'RobotsTxt';
    protected Log $logger;
    private RobotsTxtService $service;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 150],
        'POST_LOOP' => ['method' => 'handlePostLoop', 'priority' => 100]
    ];

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);

        // Get logger from container
        $this->logger = $this->container->get('logger');

        // Initialize services
        $generator = new RobotsTxtGenerator();
        $this->service = new RobotsTxtService($this->logger, $generator);

        $this->logger->log('INFO', 'RobotsTxt Feature registered');
    }

    /**
     * Handle POST_GLOB event - scan all discovered files for robots metadata
     *
     * Called dynamically by EventManager when POST_GLOB event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostGlob(Container $container, array $parameters): array
    {
        return $this->service->scanForRobotsMetadata($container, $parameters);
    }

    /**
     * Handle POST_LOOP event - generate robots.txt file
     *
     * Called dynamically by EventManager when POST_LOOP event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostLoop(Container $container, array $parameters): array
    {
        return $this->service->generateRobotsTxt($container, $parameters);
    }
}
