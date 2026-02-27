<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\EstimatedReadingTime;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\Utils\Container;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'EstimatedReadingTime';
    private EstimatedReadingTimeService $service;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'PRE_RENDER' => ['method' => 'handlePreRender', 'priority' => 50]
    ];

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->service = new EstimatedReadingTimeService();
    }

    /**
     * Calculate reading time and inject into metadata
     *
     * @param Container $container
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function handlePreRender(Container $container, array $context): array
    {
        $filePath = $context['file_path'] ?? null;
        if (!$filePath || !file_exists($filePath)) {
            return $context;
        }

        // Get configuration
        $siteConfig = $this->container->getVariable('site_config') ?? [];
        $config = $siteConfig['reading_time'] ?? [];

        // check excludes
        $excludes = $config['exclude'] ?? [];
        foreach ($excludes as $exclude) {
            if (str_contains($filePath, $exclude)) {
                return $context;
            }
        }

        $wpm = (int) ($config['wpm'] ?? 200);
        $singular = $config['label_singular'] ?? 'min read';
        $plural = $config['label_plural'] ?? 'min read';

        $rawContent = file_get_contents($filePath);
        if ($rawContent === false) {
            return $context;
        }

        // Strip YAML frontmatter
        // Matches --- at start, content, then ---
        $content = preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $rawContent);

        // Strip Title block if it's there? No, title counts as reading time.

        $result = $this->service->calculate($content ?? '', $wpm, $singular, $plural);

        // Inject into metadata
        if (!isset($context['file_metadata'])) {
            $context['file_metadata'] = [];
        }

        $context['file_metadata']['reading_time_minutes'] = $result['minutes'];
        $context['file_metadata']['reading_time_label'] = $result['label'];

        // Also update legacy metadata key if present
        if (isset($context['metadata'])) {
            $context['metadata']['reading_time_minutes'] = $result['minutes'];
            $context['metadata']['reading_time_label'] = $result['label'];
        }

        return $context;
    }
}
