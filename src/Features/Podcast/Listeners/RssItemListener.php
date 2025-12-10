<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Podcast\Listeners;

use EICC\StaticForge\Features\Podcast\Services\PodcastMediaService;
use EICC\StaticForge\Features\RssFeed\Models\FeedItem;
use EICC\Utils\Log;

class RssItemListener
{
    public function __construct(
        private PodcastMediaService $mediaService,
        private Log $logger,
        private string $outputDir,
        private string $sourceDir,
        private string $siteBaseUrl
    ) {
    }

    public function handle(array $eventData): void
    {
        /** @var FeedItem $item */
        $item = $eventData['item'];
        /** @var array $file */
        $file = $eventData['file'];

        $metadata = $file['metadata'] ?? [];

        // Check if this is a podcast item
        if (empty($metadata['audio_file']) && empty($metadata['video_file'])) {
            return;
        }

        try {
            $mediaData = $this->mediaService->processMedia(
                $file,
                $this->sourceDir,
                $this->outputDir
            );

            if ($mediaData) {
                // Ensure URL is absolute if needed
                if (!preg_match('~^https?://~i', $mediaData['url'])) {
                    $mediaData['url'] = rtrim($this->siteBaseUrl, '/') . '/' . ltrim($mediaData['url'], '/');
                }

                $item->enclosure = $mediaData;
                $this->logger->log('INFO', "Added podcast enclosure for: {$item->title}");
            }
        } catch (\Exception $e) {
            $this->logger->log('ERROR', "Failed to process podcast media for {$item->title}: " . $e->getMessage());
        }
    }
}
