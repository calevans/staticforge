<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\SiteFeed\Services;

use DateTimeImmutable;
use DateTimeInterface;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Exceptions\InvalidSlugException;
use EICC\StaticForge\Features\SiteFeed\Models\FeedCandidate;
use EICC\StaticForge\Features\SiteFeed\Models\SiteFeedItem;
use EICC\StaticForge\Services\ContentMarkers;
use EICC\StaticForge\Services\MetadataFlags;
use EICC\StaticForge\Services\Slugger;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * POST_RENDER collector. Independent of RssFeedService state.
 */
class SiteFeedCollector
{
    /** @var list<FeedCandidate> */
    private array $candidates = [];

    public function __construct(
        private readonly Container $container,
        private readonly Log $logger,
        private FeedConfig $config,
        private readonly FeedClock $clock,
    ) {
    }

    /**
     * The Feature hands the collector and the writer one FeedConfig so the
     * settings are parsed, and their warnings logged, once per build.
     */
    public function useConfig(FeedConfig $config): void
    {
        $this->config = $config;
    }

    public function reset(): void
    {
        $this->candidates = [];
    }

    /**
     * @return list<FeedCandidate>
     */
    public function getCandidates(): array
    {
        return $this->candidates;
    }

    public function collect(RenderEvent $event): void
    {
        if (!$this->config->get()->enabled || $event->skipFile) {
            return;
        }

        $metadata = $event->metadata;
        $outputPath = $event->outputPath;
        $outputDir = $this->container->getVariable('OUTPUT_DIR');
        $baseUrl = $this->container->getVariable('SITE_BASE_URL');
        if (!$outputPath || !is_string($outputDir) || $outputDir === '' || !is_string($baseUrl) || $baseUrl === '') {
            return;
        }

        $normalizedDir = rtrim(str_replace('\\', '/', $outputDir), '/') . '/';
        $normalizedPath = str_replace('\\', '/', $outputPath);
        if (!str_starts_with($normalizedPath, $normalizedDir)) {
            return;
        }
        $relativePath = substr($normalizedPath, strlen($normalizedDir));

        if ($this->isHidden($metadata, $relativePath)) {
            return;
        }

        $date = $this->parseDate($metadata['date'] ?? null, $relativePath);
        if ($date === null) {
            return;
        }
        $updated = $this->parseDate($metadata['updated'] ?? null, $relativePath) ?? $date;

        $categoryName = null;
        $categorySlug = null;
        $rawCategory = $metadata['category'] ?? null;
        if (is_string($rawCategory) && trim($rawCategory) !== '') {
            $categoryName = trim($rawCategory);
            try {
                $categorySlug = Slugger::category($categoryName);
            } catch (InvalidSlugException) {
                // Would otherwise publish under a made-up /category/ directory
                $this->logger->log(
                    'WARNING',
                    "SiteFeed: category '{$categoryName}' in {$relativePath} cannot be slugged; "
                    . 'page left out of the feeds'
                );
                return;
            }
        }

        $optIn = MetadataFlags::isTrue($metadata['feed'] ?? null);
        if ($categorySlug === null && !$optIn) {
            return;
        }

        $siteBase = rtrim($baseUrl, '/');
        $url = $this->canonicalUrl($relativePath, $siteBase);

        // The article only, not the theme around it. Without markers the whole
        // themed page would be published, so such pages stay out.
        $body = ContentMarkers::extract($event->renderedContent ?? '');
        if ($body === null) {
            $this->logger->log(
                'WARNING',
                "SiteFeed: {$relativePath} has no content markers; page left out of the feeds"
            );
            return;
        }

        $author = $metadata['author'] ?? null;
        $item = new SiteFeedItem(
            $url,
            $url,
            is_scalar($metadata['title'] ?? null) ? (string) $metadata['title'] : 'Untitled',
            $this->summary($body, $metadata),
            HtmlUrlAbsolutizer::absolutize($body, $siteBase),
            $date,
            $updated,
            is_string($author) && $author !== '' ? $author : null,
            $this->tags($categoryName, $metadata['tags'] ?? null),
            $metadata,
        );

        // Undated pages never reach here: Atom requires <updated> and no date is invented.
        $this->candidates[] = new FeedCandidate(
            $item,
            $relativePath,
            $categorySlug,
            $categoryName,
            $optIn,
            MetadataFlags::isFalse($metadata['feed'] ?? null)
        );
    }

    /**
     * Pages the author hid from indexes, plus pages the generators emit.
     *
     * Marker for generated pages: CategoryIndex renders its listings with
     * `category_files` in metadata (and strips `type`), Tags renders with
     * `tag_files`/`tag_slug`. Category definition files carry `type: category`.
     *
     * @param array<string, mixed> $metadata
     */
    private function isHidden(array $metadata, string $relativePath): bool
    {
        // Fail closed: a value the flag parser cannot classify hides the page
        return basename($relativePath) === '404.html'
            || ($metadata['template'] ?? null) === '404'
            || !self::clearlyFalse($metadata['draft'] ?? null)
            || !self::clearlyFalse($metadata['noindex'] ?? null)
            || (($metadata['sitemap'] ?? null) !== null && !MetadataFlags::isTrue($metadata['sitemap']))
            || self::robotsHides($metadata['robots'] ?? null)
            || ($metadata['type'] ?? null) === 'category'
            || array_key_exists('category_files', $metadata)
            || array_key_exists('tag_files', $metadata)
            || array_key_exists('tag_slug', $metadata);
    }

    private static function clearlyFalse(mixed $value): bool
    {
        return $value === null || MetadataFlags::isFalse($value);
    }

    private static function robotsHides(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (!is_scalar($value)) {
            return true;
        }

        return MetadataFlags::robotsBlocked($value)
            || (is_string($value) && stripos($value, 'noindex') !== false);
    }

    private function parseDate(mixed $value, string $path): ?DateTimeImmutable
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        $tz = $this->clock->timezone();
        try {
            if ($value instanceof DateTimeInterface) {
                return DateTimeImmutable::createFromInterface($value)->setTimezone($tz);
            }
            if (is_int($value)) {
                return (new DateTimeImmutable('@' . $value))->setTimezone($tz);
            }
            if (is_string($value)) {
                return new DateTimeImmutable($value, $tz);
            }
        } catch (\Exception) {
            // fall through to the warning
        }

        $this->logger->log('WARNING', "SiteFeed: unparseable date in {$path}; page left out of the feeds");
        return null;
    }

    private function canonicalUrl(string $relativePath, string $siteBase): string
    {
        if ($relativePath === 'index.html') {
            return $siteBase . '/';
        }
        $encode = static fn (string $path): string => implode('/', array_map(rawurlencode(...), explode('/', $path)));
        if (basename($relativePath) === 'index.html') {
            return $siteBase . '/' . $encode(rtrim(dirname($relativePath), '/')) . '/';
        }
        return $siteBase . '/' . $encode(ltrim($relativePath, '/'));
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function summary(string $html, array $metadata): string
    {
        if (!empty($metadata['description']) && is_string($metadata['description'])) {
            return $metadata['description'];
        }

        // Builders escape once; entities from the HTML must be decoded first
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if (mb_strlen($text) > 200) {
            $text = mb_substr($text, 0, 200);
            $lastSpace = mb_strrpos($text, ' ');
            if ($lastSpace !== false) {
                $text = mb_substr($text, 0, $lastSpace);
            }
            $text .= '...';
        }

        return $text;
    }

    /**
     * @return list<string>
     */
    private function tags(?string $categoryName, mixed $tags): array
    {
        $result = $categoryName !== null ? [$categoryName] : [];
        if (is_array($tags)) {
            foreach ($tags as $tag) {
                if (is_string($tag) && trim($tag) !== '') {
                    $result[] = trim($tag);
                }
            }
        }

        return array_values(array_unique($result));
    }
}
