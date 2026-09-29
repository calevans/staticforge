<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\SiteFeed\Services;

use EICC\StaticForge\Features\SiteFeed\Models\FeedSettings;
use EICC\Utils\Container;
use EICC\Utils\Log;

class FeedConfig
{
    private ?FeedSettings $settings = null;

    public function __construct(private readonly Container $container, private readonly Log $logger)
    {
    }

    public function get(): FeedSettings
    {
        if ($this->settings === null) {
            $siteConfig = $this->container->getVariable('site_config');
            $this->settings = FeedSettings::fromConfig(is_array($siteConfig) ? ($siteConfig['feed'] ?? null) : null);
            foreach ($this->settings->warnings as $warning) {
                $this->logger->log('WARNING', 'SiteFeed: ' . $warning);
            }
        }

        return $this->settings;
    }

    public function reset(): void
    {
        $this->settings = null;
    }
}
