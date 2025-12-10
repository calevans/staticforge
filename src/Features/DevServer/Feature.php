<?php

namespace EICC\StaticForge\Features\DevServer;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\StaticForge\Features\DevServer\Commands\DevServerCommand;
use Symfony\Component\Console\Application;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'DevServer';

    protected array $eventListeners = [
        'CONSOLE_INIT' => ['method' => 'registerCommands', 'priority' => 0]
    ];

    public function registerCommands(Container $container, array $parameters): array
    {
        /** @var Application $application */
        $application = $parameters['application'];
        $application->add(new DevServerCommand());

        return $parameters;
    }
}
