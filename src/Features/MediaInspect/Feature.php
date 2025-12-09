<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\MediaInspect;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\MediaInspect\Commands\InspectMediaCommand;
use EICC\StaticForge\Features\MediaInspect\Services\MediaInspector;
use EICC\Utils\Container;
use Symfony\Component\Console\Application;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'MediaInspect';

    protected array $eventListeners = [
        'CONSOLE_INIT' => ['method' => 'registerCommands', 'priority' => 100],
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);

        // Register the service
        $container->set(MediaInspector::class, new MediaInspector());
    }

    public function registerCommands(Container $container, array $parameters): array
    {
        /** @var Application $app */
        $app = $parameters['application'];

        // Get service from container
        $mediaInspector = $container->get(MediaInspector::class);

        $app->add(new InspectMediaCommand($mediaInspector));

        return $parameters;
    }
}
