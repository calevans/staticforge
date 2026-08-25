<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Deployment;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\Events\ConsoleInitEvent;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Features\Deployment\Commands\UploadSiteCommand;
use EICC\Utils\Container;

class Feature extends BaseFeature implements FeatureInterface, ConfigurableFeatureInterface
{
    protected string $name = 'Deployment';
    private Container $applicationContainer;

    public function __construct(Container $applicationContainer)
    {
        $this->applicationContainer = $applicationContainer;
    }

    public function getRequiredConfig(): array
    {
        return [];
    }

    public function getRequiredEnv(): array
    {
        return ['UPLOAD_URL'];
    }

    #[EventListener('CONSOLE_INIT', priority: 0)]
    public function registerCommands(ConsoleInitEvent $event): void
    {
        $event->application->addCommand(new UploadSiteCommand($this->applicationContainer));
    }
}
