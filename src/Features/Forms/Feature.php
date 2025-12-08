<?php

namespace EICC\StaticForge\Features\Forms;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\Forms\Services\FormsService;
use EICC\Utils\Container;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'Forms';
    protected Log $logger;
    private FormsService $service;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'RENDER' => ['method' => 'handleRender', 'priority' => 50]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);
        $this->logger = $container->get('logger');
        $twig = $container->get('twig');

        $this->service = new FormsService($this->logger, $twig);

        $this->logger->log('INFO', 'Forms Feature registered');
    }

    /**
     * Handle RENDER event
     * Replaces form shortcodes with HTML forms
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handleRender(Container $container, array $parameters): array
    {
        return $this->service->processForms($container, $parameters);
    }
}
