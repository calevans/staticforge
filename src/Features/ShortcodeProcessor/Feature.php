<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\ShortcodeProcessor;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\ShortcodeProcessor\Services\ShortcodeProcessorService;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\StaticForge\Shortcodes\ShortcodeManager;
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

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);
        $this->logger = $container->get('logger');

        // Initialize ShortcodeManager
        // We need TemplateRenderer. It might not be in the container yet?
        // TemplateRenderer is usually instantiated in features.
        // Let's instantiate a new one or check if it's in container.
        // The container usually doesn't have TemplateRenderer as a shared service.
        // MarkdownRenderer creates its own.
        // We should probably create one.

        // We need TemplateVariableBuilder too.
        $templateVariableBuilder = new \EICC\StaticForge\Services\TemplateVariableBuilder();
        $templateRenderer = new TemplateRenderer($templateVariableBuilder, $this->logger);

        $shortcodeManager = new ShortcodeManager($container, $templateRenderer);

        // Register ShortcodeManager in container for other features to use
        $container->add(ShortcodeManager::class, $shortcodeManager);

        // Initialize Service
        $this->service = new ShortcodeProcessorService($this->logger, $shortcodeManager);

        // Register reference shortcodes
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

