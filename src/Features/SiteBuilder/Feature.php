<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\SiteBuilder;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\Events\ConsoleInitEvent;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Features\SiteBuilder\Commands\RenderSiteCommand;
use EICC\Utils\Container;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'SiteBuilder';
    private Container $applicationContainer;

    public function __construct(Container $applicationContainer)
    {
        $this->applicationContainer = $applicationContainer;
    }

    #[EventListener('CONSOLE_INIT', priority: 0)]
    public function registerCommands(ConsoleInitEvent $event): void
    {
        $event->application->addCommand(new RenderSiteCommand($this->applicationContainer));
    }
}
