<?php

namespace EICC\StaticForge\Features\SiteBuilder;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\StaticForge\Features\SiteBuilder\Commands\RenderSiteCommand;
use Symfony\Component\Console\Application;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'SiteBuilder';

    protected array $eventListeners = [
        'CONSOLE_INIT' => ['method' => 'registerCommands', 'priority' => 0]
    ];

    public function registerCommands(Container $container, array $parameters): array
    {
        /** @var Application $application */
        $application = $parameters['application'];
        $application->add(new RenderSiteCommand($container));

        return $parameters;
    }
}
