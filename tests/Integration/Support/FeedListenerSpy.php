<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration\Support;

use EICC\StaticForge\Core\Events\RssBuilderInitEvent;
use EICC\StaticForge\Core\Events\RssItemBuildingEvent;

/**
 * Records event payloads and mimics the reads staticforge-podcast's PodcastFeedService performs:
 * $event->categoryMetadata['podcast'], $event->file['metadata'][audio_url|media_length|media_type|
 * podcast_show_notes_html], and writes $event->item->enclosure / ->content.
 */
final class FeedListenerSpy
{
    /** @var list<array{event: string, categoryMetadata: array<string, mixed>}> */
    public array $builderInitCalls = [];

    /** @var list<array{event: string, fileKeys: list<int|string>, title: mixed, url: mixed, date: mixed, metadata: mixed}> */
    public array $itemCalls = [];

    public function onBuilderInit(RssBuilderInitEvent $event): void
    {
        $this->builderInitCalls[] = [
            'event' => $event->name,
            'categoryMetadata' => $event->categoryMetadata,
        ];

        if (($event->categoryMetadata['podcast'] ?? null) === true) {
            $event->builder->addExtension(new FeedSpyExtension());
        }
    }

    public function onItemBuilding(RssItemBuildingEvent $event): void
    {
        $file = $event->file;
        $this->itemCalls[] = [
            'event' => $event->name,
            'fileKeys' => array_keys($file),
            'title' => $file['title'] ?? null,
            'url' => $file['url'] ?? null,
            'date' => $file['date'] ?? null,
            'metadata' => $file['metadata'] ?? null,
        ];

        $metadata = $file['metadata'] ?? null;
        if (!is_array($metadata)) {
            return;
        }
        $url = $metadata['audio_url'] ?? $metadata['video_url'] ?? null;
        if (!is_string($url) || $url === '') {
            return;
        }

        $length = is_int($metadata['media_length'] ?? null) ? $metadata['media_length'] : 0;
        $type = is_string($metadata['media_type'] ?? null) ? $metadata['media_type'] : 'application/octet-stream';
        if (preg_match('~^https?://~i', $url) !== 1) {
            $url = 'https://golden.example.com/' . ltrim($url, '/');
        }

        $event->item->enclosure = ['url' => $url, 'length' => $length, 'type' => $type];
        $showNotes = $metadata['podcast_show_notes_html'] ?? null;
        $event->item->content = (is_string($showNotes) && $showNotes !== '') ? $showNotes : null;
    }
}
