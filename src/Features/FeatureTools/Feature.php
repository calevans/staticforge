<?php

namespace EICC\StaticForge\Features\FeatureTools;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\StaticForge\Features\FeatureTools\Commands\FeatureCreateCommand;
use EICC\StaticForge\Features\FeatureTools\Commands\FeatureSetupCommand;
use EICC\StaticForge\Features\FeatureTools\Commands\ListFeaturesCommand;
use Symfony\Component\Console\Application;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'FeatureTools';

    protected array $eventListeners = [
        'CONSOLE_INIT' => ['method' => 'registerCommands', 'priority' => 0]
    ];

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function registerCommands(Container $container, array $parameters): array
    {
        /** @var Application $application */
        $application = $parameters['application'];
        $application->add(new FeatureCreateCommand());
        $application->add(new FeatureSetupCommand());
        $application->add(new ListFeaturesCommand($container->get(\EICC\StaticForge\Core\FeatureManager::class)));

        return $parameters;
    }
}
