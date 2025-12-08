<?php

namespace EICC\StaticForge\Features\TableOfContents;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\TableOfContents\Services\TableOfContentsService;
use EICC\Utils\Container;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'TableOfContents';
    protected Log $logger;
    private TableOfContentsService $service;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'MARKDOWN_CONVERTED' => ['method' => 'handleMarkdownConverted', 'priority' => 500]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);
        $this->logger = $container->get('logger');
        $this->service = new TableOfContentsService($this->logger);
        $eventManager->registerEvent('MARKDOWN_CONVERTED');
        $this->logger->log('INFO', 'TableOfContents Feature registered');
    }

    /**
     * Handle MARKDOWN_CONVERTED event
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */

    public function handleMarkdownConverted(Container $container, array $parameters): array
    {
        return $this->service->handleMarkdownConverted($container, $parameters);
    }
}

