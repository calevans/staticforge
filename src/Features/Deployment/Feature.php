<?php

namespace EICC\StaticForge\Features\Deployment;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\StaticForge\Features\Deployment\Commands\UploadSiteCommand;
use Symfony\Component\Console\Application;

class Feature extends BaseFeature implements FeatureInterface, ConfigurableFeatureInterface
{
    protected string $name = 'Deployment';

    protected array $eventListeners = [
        'CONSOLE_INIT' => ['method' => 'registerCommands', 'priority' => 0]
    ];

    public function getRequiredConfig(): array
    {
        return [];
    }

    public function getRequiredEnv(): array
    {
        return ['UPLOAD_URL'];
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function registerCommands(Container $container, array $parameters): array
    {
        /** @var Application $application */
        $application = $parameters['application'];
        $application->add(new UploadSiteCommand($container));

        return $parameters;
    }
}
