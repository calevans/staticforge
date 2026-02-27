<?php

namespace EICC\StaticForge\Features\MenuBuilder;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\MenuBuilder\Services\MenuBuilderService;
use EICC\StaticForge\Features\MenuBuilder\Services\MenuHtmlGenerator;
use EICC\StaticForge\Features\MenuBuilder\Services\MenuScanner;
use EICC\StaticForge\Features\MenuBuilder\Services\MenuStructureBuilder;
use EICC\StaticForge\Features\MenuBuilder\Services\StaticMenuProcessor;
use EICC\Utils\Container;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface, ConfigurableFeatureInterface
{
    protected string $name = 'MenuBuilder';
    private Log $logger;
    private MenuBuilderService $service;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 100]
    ];

    public function getRequiredConfig(): array
    {
        return ['menu'];
    }

    public function getRequiredEnv(): array
    {
        return [];
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);

        // Get logger from container
        $this->logger = $this->container->get('logger');

        // Initialize services
        $structureBuilder = new MenuStructureBuilder();
        $htmlGenerator = new MenuHtmlGenerator();
        $menuScanner = new MenuScanner($structureBuilder);
        $staticMenuProcessor = new StaticMenuProcessor($htmlGenerator, $this->logger);

        // Register services in container for potential external use/testing
        // Note: Keeping these for backward compatibility/testing, but they are now in Services namespace
        $this->container->add(MenuStructureBuilder::class, $structureBuilder);
        $this->container->add(MenuHtmlGenerator::class, $htmlGenerator);
        $this->container->add(MenuScanner::class, $menuScanner);
        $this->container->add(StaticMenuProcessor::class, $staticMenuProcessor);

        // Initialize main service
        $this->service = new MenuBuilderService(
            $menuScanner,
            $htmlGenerator,
            $staticMenuProcessor,
            $structureBuilder,
            $eventManager,
            $this->logger
        );

        // Register new event for other features to inject menu items
        $eventManager->registerEvent('COLLECT_MENU_ITEMS');

        $this->logger->log('INFO', 'MenuBuilder Feature registered');
    }

    /**
     * Handle POST_GLOB event - build menu structure from discovered files
     *
     * Called dynamically by EventManager when POST_GLOB event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostGlob(Container $container, array $parameters): array
    {
        return $this->service->buildMenus($container, $parameters);
    }
}
