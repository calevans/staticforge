<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\EstimatedReadingTime;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\Utils\Container;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'EstimatedReadingTime';
    private EstimatedReadingTimeService $service;
    private Container $applicationContainer;

    public function __construct(EstimatedReadingTimeService $service, Container $applicationContainer)
    {
        $this->service = $service;
        $this->applicationContainer = $applicationContainer;
    }

    /**
     * Calculate reading time and inject into metadata
     */
    #[EventListener('PRE_RENDER', priority: 50)]
    public function handlePreRender(RenderEvent $event): void
    {
        $filePath = $event->filePath;
        if ($filePath === '' || !file_exists($filePath)) {
            return;
        }

        // Get configuration
        $siteConfig = $this->applicationContainer->getVariable('site_config') ?? [];
        $config = $siteConfig['reading_time'] ?? [];

        // check excludes
        $excludes = $config['exclude'] ?? [];
        foreach ($excludes as $exclude) {
            if (str_contains($filePath, $exclude)) {
                return;
            }
        }

        $wpm = (int) ($config['wpm'] ?? 200);
        $singular = $config['label_singular'] ?? 'min read';
        $plural = $config['label_plural'] ?? 'min read';

        $rawContent = file_get_contents($filePath);
        if ($rawContent === false) {
            return;
        }

        // Strip YAML frontmatter
        // Matches --- at start, content, then ---
        $content = preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $rawContent);

        $result = $this->service->calculate($content ?? '', $wpm, $singular, $plural);

        $event->metadata['reading_time_minutes'] = $result['minutes'];
        $event->metadata['reading_time_label'] = $result['label'];
    }
}
