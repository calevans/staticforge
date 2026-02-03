<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Calendar;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\Calendar\Services\CalendarService;
use EICC\StaticForge\Features\Calendar\Services\CalendarAssetService;
use EICC\StaticForge\Features\Calendar\Shortcodes\CalendarShortcode;
use EICC\StaticForge\Shortcodes\ShortcodeManager;
use EICC\Utils\Container;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface, ConfigurableFeatureInterface
{
    protected string $name = 'Calendar';
    protected Log $logger;
    private CalendarService $service;
    private CalendarAssetService $assetService;

    protected array $eventListeners = [
        'CREATE' => ['method' => 'handleCreate', 'priority' => 10],
        'POST_LOOP' => ['method' => 'handlePostLoop', 'priority' => 100]
    ];

    public function getRequiredConfig(): array
    {
        // Require the calendars configuration block if this feature is enabled
        // Actually, strictly speaking, if no calendars are defined, the feature might be useless but not broken.
        // But the plan says "Requires 'calendars' key".
        return ['calendars'];
    }

    public function getRequiredEnv(): array
    {
        return [];
    }

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);
        $this->logger = $container->get('logger');
        
        $projectRoot = $container->getVariable('app_root') ?? getcwd();
        
        $this->service = new CalendarService($this->logger, $projectRoot);
        $this->assetService = new CalendarAssetService($this->logger, $projectRoot);

        $this->logger->log('INFO', 'Calendar Feature registered');
    }

    public function handleCreate(Container $container): void
    {
        // Register the shortcode
        if ($container->has(ShortcodeManager::class)) {
            $shortcodeManager = $container->get(ShortcodeManager::class);
            $shortcodeManager->register(new CalendarShortcode($this->service, $container));
            $this->logger->log('INFO', 'Calendar shortcode registered');
        } else {
            $this->logger->log('WARNING', 'ShortcodeManager not found. Calendar shortcode not registered.');
        }
    }

    /**
     * Copy assets and templates during POST_LOOP
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostLoop(Container $container, array $parameters): array
    {
        $this->assetService->copyAssets($container);
        return $parameters;
    }
}
