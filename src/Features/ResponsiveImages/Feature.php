<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\ResponsiveImages;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Features\ResponsiveImages\Services\HtmlImageRewriterService;
use EICC\StaticForge\Features\ResponsiveImages\Services\ResponsiveImageConfig;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Post-processes rendered HTML on POST_RENDER to find local <img> tags,
 * generate resized Imagick variants (with optional WebP), and rewrite
 * them into <picture> elements with srcset. Disabled by default via
 * responsive_images.enabled: false config.
 */
class Feature extends BaseFeature implements FeatureInterface, ConfigurableFeatureInterface
{
    protected string $name = 'ResponsiveImages';
    protected Log $logger;
    private HtmlImageRewriterService $service;
    private ResponsiveImageConfig $config;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_RENDER' => ['method' => 'handlePostRender', 'priority' => 150],
    ];

    public function getRequiredConfig(): array
    {
        return [];
    }

    public function getRequiredEnv(): array
    {
        return [];
    }

    public function __construct(Log $logger, HtmlImageRewriterService $service, ResponsiveImageConfig $config)
    {
        $this->logger = $logger;
        $this->service = $service;
        $this->config = $config;
    }

    public function register(EventManager $eventManager): void
    {
        $this->eventManager = $eventManager;

        if (!$this->config->enabled) {
            $this->logger->log('INFO', 'ResponsiveImages Feature disabled via config');
            return;
        }

        $this->registerEventListeners();
        $this->logger->log('INFO', 'ResponsiveImages Feature registered');
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostRender(Container $container, array $parameters): array
    {
        if (!$this->config->enabled) {
            return $parameters;
        }

        return $this->service->handlePostRender($container, $parameters);
    }
}
