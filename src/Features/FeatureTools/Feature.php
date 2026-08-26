<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\FeatureTools;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\Events\ConsoleInitEvent;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\FeatureManager;
use EICC\StaticForge\Features\FeatureTools\Commands\FeatureCreateCommand;
use EICC\StaticForge\Features\FeatureTools\Commands\FeatureMigrateCommand;
use EICC\StaticForge\Features\FeatureTools\Commands\FeatureSetupCommand;
use EICC\StaticForge\Features\FeatureTools\Commands\ListFeaturesCommand;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'FeatureTools';
    private FeatureManager $featureManager;

    public function __construct(FeatureManager $featureManager)
    {
        $this->featureManager = $featureManager;
    }

    #[EventListener('CONSOLE_INIT', priority: 0)]
    public function registerCommands(ConsoleInitEvent $event): void
    {
        $event->application->addCommand(new FeatureCreateCommand());
        $event->application->addCommand(new FeatureSetupCommand());
        $event->application->addCommand(new FeatureMigrateCommand());
        $event->application->addCommand(new ListFeaturesCommand($this->featureManager));
    }
}
