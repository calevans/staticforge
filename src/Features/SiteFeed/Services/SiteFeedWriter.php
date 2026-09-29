<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\SiteFeed\Services;

use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\OutputWriter;
use EICC\StaticForge\Exceptions\InvalidSlugException;
use EICC\StaticForge\Features\SiteFeed\Events\SiteFeedInitEvent;
use EICC\StaticForge\Features\SiteFeed\Events\SiteFeedItemBuildingEvent;
use EICC\StaticForge\Features\SiteFeed\Models\FeedCandidate;
use EICC\StaticForge\Features\SiteFeed\Models\SiteFeedItem;
use EICC\StaticForge\Services\MetadataFlags;
use EICC\StaticForge\Services\Slugger;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Writes /feed.xml, /feed.atom, /feed.json and the optional per-category
 * Atom/JSON feeds at POST_LOOP. Never touches category rss.xml.
 */
class SiteFeedWriter
{
    public const EVENT_INIT = 'SITE_FEED_INIT';
    public const EVENT_ITEM_BUILDING = 'SITE_FEED_ITEM_BUILDING';

    private const LINK_TYPES = [
        'rss' => ['application/rss+xml', 'RSS'],
        'atom' => ['application/atom+xml', 'Atom'],
        'json' => ['application/feed+json', 'JSON Feed'],
    ];

    /** @var array<string, FeedBuilderInterface> */
    private array $builders;

    /** @var list<string>|null */
    private ?array $siteFormats = null;

    private bool $baseUrlWarned = false;

    public function __construct(
        private readonly Container $container,
        private readonly Log $logger,
        private readonly EventManager $eventManager,
        private readonly OutputWriter $outputWriter,
        private FeedConfig $config,
        private readonly FeedClock $clock,
        Rss2Builder $rss,
        AtomBuilder $atom,
        JsonFeedBuilder $json,
    ) {
        $this->builders = ['rss' => $rss, 'atom' => $atom, 'json' => $json];
    }

    public function useConfig(FeedConfig $config): void
    {
        $this->config = $config;
    }

    public function reset(): void
    {
        $this->siteFormats = null;
        $this->baseUrlWarned = false;
    }

    /**
     * Decides which site feeds will exist and publishes their autodiscovery
     * links before any page is rendered.
     */
    public function prepare(): void
    {
        $this->reset();
        if (!$this->isActive()) {
            return;
        }

        $baseUrl = rtrim((string) $this->container->getVariable('SITE_BASE_URL'), '/');
        $siteName = $this->siteName();
        $links = [];
        foreach ($this->siteFormats() as $format) {
            [$type, $label] = self::LINK_TYPES[$format];
            $links[] = [
                'type' => $type,
                'title' => $siteName . ' (' . $label . ')',
                'href' => $baseUrl . '/' . $this->builders[$format]->getFileName(),
            ];
        }

        if ($this->container->hasVariable('feed_links')) {
            $this->container->updateVariable('feed_links', $links);
        } else {
            $this->container->setVariable('feed_links', $links);
        }
    }

    /**
     * @param list<FeedCandidate> $allCandidates
     */
    public function write(array $allCandidates): void
    {
        if (!$this->isActive()) {
            return;
        }

        $settings = $this->config->get();
        $baseUrl = rtrim((string) $this->container->getVariable('SITE_BASE_URL'), '/');
        $siteName = $this->siteName();
        $podcastSlugs = $this->podcastCategorySlugs();

        $eligible = array_values(array_filter(
            $allCandidates,
            static fn (FeedCandidate $c): bool => $c->categorySlug === null
                || (!in_array($c->categorySlug, $settings->excludeCategories, true)
                    && !in_array($c->categorySlug, $podcastSlugs, true))
        ));

        $siteItems = array_values(array_filter(
            $eligible,
            static fn (FeedCandidate $c): bool => !$c->optOut && ($c->categorySlug !== null || $c->optIn)
        ));
        $siteItems = array_slice($this->sort($siteItems), 0, $settings->limit);

        $channel = [
            'title' => $siteName,
            'link' => $baseUrl . '/',
            'description' => $this->siteDescription($siteName),
            'author' => $siteName,
        ];
        foreach ($this->siteFormats() as $format) {
            $this->writeFeed($format, 'site', '', $channel, $siteItems, $baseUrl);
        }

        $categoryFormats = array_values(array_diff($settings->categoryFormats, ['rss']));
        if ($categoryFormats === []) {
            return;
        }

        /** @var array<string, list<FeedCandidate>> $byCategory */
        $byCategory = [];
        foreach ($eligible as $candidate) {
            if ($candidate->categorySlug !== null) {
                $byCategory[$candidate->categorySlug][] = $candidate;
            }
        }
        ksort($byCategory);

        foreach ($byCategory as $slug => $candidates) {
            $name = $candidates[0]->categoryName ?? ucfirst((string) $slug);
            $categoryChannel = [
                'title' => $siteName . ' - ' . $name,
                'link' => $baseUrl . '/' . $slug . '/',
                'description' => $name . ' articles from ' . $siteName,
                'author' => $siteName,
            ];
            $sorted = $this->sort($candidates);
            foreach ($categoryFormats as $format) {
                $this->writeFeed($format, (string) $slug, $slug . '/', $categoryChannel, $sorted, $baseUrl);
            }
        }
    }

    private function isActive(): bool
    {
        if (!$this->config->get()->enabled) {
            return false;
        }

        $baseUrl = $this->container->getVariable('SITE_BASE_URL');
        if (!is_string($baseUrl) || $baseUrl === '') {
            if (!$this->baseUrlWarned) {
                $this->baseUrlWarned = true;
                $this->logger->log('WARNING', 'SiteFeed: SITE_BASE_URL is not set, so no feeds are written');
            }
            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function siteFormats(): array
    {
        if ($this->siteFormats === null) {
            $this->siteFormats = [];
            foreach ($this->config->get()->formats as $format) {
                $fileName = $this->builders[$format]->getFileName();
                if ($this->sourceFileExists($fileName)) {
                    $this->logger->log(
                        'WARNING',
                        "SiteFeed: content/{$fileName} exists; kept, no {$format} site feed written"
                    );
                    continue;
                }
                $this->siteFormats[] = $format;
            }
        }

        return $this->siteFormats;
    }

    private function sourceFileExists(string $relativePath): bool
    {
        $sourceDir = $this->container->getVariable('SOURCE_DIR');

        if (!is_string($sourceDir) || $sourceDir === '') {
            return false;
        }

        return file_exists(rtrim($sourceDir, '/\\') . '/' . $relativePath);
    }

    /**
     * @param array<string, mixed> $channel
     * @param list<FeedCandidate> $candidates
     */
    private function writeFeed(
        string $format,
        string $scope,
        string $dirPrefix,
        array $channel,
        array $candidates,
        string $baseUrl
    ): void {
        $builder = $this->builders[$format];
        $relative = $dirPrefix . $builder->getFileName();

        if ($scope !== 'site' && $this->sourceFileExists($relative)) {
            $this->logger->log('WARNING', "SiteFeed: content/{$relative} exists; kept, nothing written");
            return;
        }

        $channel['feed_url'] = $baseUrl . '/' . $relative;
        $init = new SiteFeedInitEvent(self::EVENT_INIT, $format, $scope, $channel);
        $this->eventManager->fire(self::EVENT_INIT, $init);

        $items = [];
        foreach ($candidates as $candidate) {
            $copy = clone $candidate->item;
            $this->eventManager->fire(
                self::EVENT_ITEM_BUILDING,
                new SiteFeedItemBuildingEvent(self::EVENT_ITEM_BUILDING, $format, $scope, $copy)
            );
            $items[] = $copy;
        }

        $outputDir = rtrim((string) $this->container->getVariable('OUTPUT_DIR'), '/\\');
        $path = $outputDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        try {
            $this->outputWriter->write($path, $builder->build($init->channel, $items, $this->clock->now()));
            $this->logger->log('INFO', "SiteFeed: wrote {$relative} with " . count($items) . ' items');
        } catch (\Throwable $e) {
            $this->logger->log('ERROR', "SiteFeed: failed to write {$relative}: " . $e->getMessage());
        }
    }

    /**
     * Newest first; equal dates fall back to output path so builds are deterministic.
     *
     * @param list<FeedCandidate> $candidates
     * @return list<FeedCandidate>
     */
    private function sort(array $candidates): array
    {
        usort($candidates, static function (FeedCandidate $a, FeedCandidate $b): int {
            $byDate = $b->item->published->getTimestamp() <=> $a->item->published->getTimestamp();

            return $byDate !== 0 ? $byDate : strcmp($a->path, $b->path);
        });

        return $candidates;
    }

    /**
     * Category definitions (type: category) marked podcast: true belong to the
     * podcast package, whose enclosures these feeds cannot carry.
     *
     * @return list<string>
     */
    private function podcastCategorySlugs(): array
    {
        $slugs = [];
        $files = $this->container->getVariable('discovered_files');
        foreach (is_array($files) ? $files : [] as $file) {
            $metadata = $file['metadata'] ?? [];
            if (!is_array($metadata) || ($metadata['type'] ?? '') !== 'category') {
                continue;
            }
            if (!MetadataFlags::isTrue($metadata['podcast'] ?? null)) {
                continue;
            }
            try {
                $slugs[] = Slugger::category(pathinfo((string) ($file['path'] ?? ''), PATHINFO_FILENAME));
            } catch (InvalidSlugException) {
                $this->logger->log(
                    'WARNING',
                    'SiteFeed: podcast category definition has an unsluggable filename; ignored'
                );
            }
        }

        return $slugs;
    }

    private function siteName(): string
    {
        $siteConfig = $this->container->getVariable('site_config');
        $name = is_array($siteConfig) ? ($siteConfig['site']['name'] ?? null) : null;
        if (!is_string($name) || $name === '') {
            $name = $this->container->getVariable('SITE_NAME');
        }

        return is_string($name) && $name !== '' ? $name : 'My Site';
    }

    private function siteDescription(string $siteName): string
    {
        $siteConfig = $this->container->getVariable('site_config');
        $tagline = is_array($siteConfig) ? ($siteConfig['site']['tagline'] ?? null) : null;

        return is_string($tagline) && $tagline !== '' ? $tagline : 'Latest posts from ' . $siteName;
    }
}
