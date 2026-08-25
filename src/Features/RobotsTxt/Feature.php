<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\RobotsTxt;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Features\RobotsTxt\Services\RobotsTxtService;
use EICC\Utils\Log;

/**
 * RobotsTxt Feature - generates robots.txt file based on content metadata
 *
 * EVENTS FIRED:
 * - ROBOTS_TXT_BUILDING: Allows external features to modify the robots rules payload.
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

    public function __construct(Log $logger, RobotsTxtService $service)
    {
        $this->logger = $logger;
        $this->service = $service;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger->log('INFO', 'RobotsTxt Feature registered');
    }

    #[EventListener('POST_GLOB', priority: 150)]
    public function handlePostGlob(Event $event): void
    {
        $this->service->scanForRobotsMetadata();
    }

    #[EventListener('POST_LOOP', priority: 100)]
    public function handlePostLoop(Event $event): void
    {
        $this->service->generateRobotsTxt();
    }
}
