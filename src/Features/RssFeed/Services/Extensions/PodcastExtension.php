<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\RssFeed\Services\Extensions;

use DOMDocument;
use DOMElement;
use EICC\StaticForge\Features\RssFeed\Models\FeedChannel;
use EICC\StaticForge\Features\RssFeed\Models\FeedItem;

class PodcastExtension implements FeedExtensionInterface
{
    public function getNamespaces(): array
    {
        return [
            'itunes' => 'http://www.itunes.com/dtds/podcast-1.0.dtd'
        ];
    }

    public function applyToChannel(DOMElement $channel, FeedChannel $data, DOMDocument $dom): void
    {
        $metadata = $data->metadata;

        // Copyright fallback
        if (empty($data->copyright) && !empty($metadata['itunes_owner_name'])) {
            $copyright = $dom->createElement('copyright', '© ' . date('Y') . ' ' . $metadata['itunes_owner_name']);
            $channel->appendChild($copyright);
        }

        // iTunes Type
        $type = $metadata['itunes_type'] ?? 'episodic';
        $channel->appendChild($dom->createElement('itunes:type', $type));

        if (!empty($metadata['itunes_author'])) {
            $channel->appendChild($dom->createElement('itunes:author', $metadata['itunes_author']));
        }

        $summary = $metadata['itunes_summary'] ?? $metadata['description'] ?? null;
        if ($summary) {
            $channel->appendChild($dom->createElement('itunes:summary', $summary));
        }

        if (!empty($metadata['itunes_owner_name']) || !empty($metadata['itunes_owner_email'])) {
            $owner = $dom->createElement('itunes:owner');
            if (!empty($metadata['itunes_owner_name'])) {
                $owner->appendChild($dom->createElement('itunes:name', $metadata['itunes_owner_name']));
            }
            if (!empty($metadata['itunes_owner_email'])) {
                $owner->appendChild($dom->createElement('itunes:email', $metadata['itunes_owner_email']));
            }
            $channel->appendChild($owner);
        }

        if (!empty($metadata['itunes_image'])) {
            $image = $dom->createElement('itunes:image');
            $image->setAttribute('href', $this->resolveUrl($metadata['itunes_image'], $data->link));
            $channel->appendChild($image);
        }

        if (!empty($metadata['itunes_category'])) {
            $categories = $metadata['itunes_category'];
            if (!is_array($categories)) {
                $categories = [$categories];
            }

            foreach ($categories as $catString) {
                if (str_contains($catString, '>')) {
                    $parts = array_map('trim', explode('>', $catString, 2));
                    $catNode = $dom->createElement('itunes:category');
                    $catNode->setAttribute('text', $parts[0]);

                    $subCatNode = $dom->createElement('itunes:category');
                    $subCatNode->setAttribute('text', $parts[1]);
                    $catNode->appendChild($subCatNode);

                    $channel->appendChild($catNode);
                } else {
                    $catNode = $dom->createElement('itunes:category');
                    $catNode->setAttribute('text', $catString);
                    $channel->appendChild($catNode);
                }
            }
        }

        if (isset($metadata['itunes_explicit'])) {
            $explicit = $this->normalizeExplicit($metadata['itunes_explicit']);
            $channel->appendChild($dom->createElement('itunes:explicit', $explicit));
        }
    }

    public function applyToItem(DOMElement $item, FeedItem $data, DOMDocument $dom): void
    {
        $metadata = $data->metadata;

        // Enclosure is handled by standard RSS, but iTunes uses it too.
        // We assume the standard enclosure is already added by the builder if present.

        if (!empty($metadata['itunes_title'])) {
            $item->appendChild($dom->createElement('itunes:title', $metadata['itunes_title']));
        }

        if (!empty($metadata['itunes_episode_type'])) {
            $item->appendChild($dom->createElement('itunes:episodeType', $metadata['itunes_episode_type']));
        }

        $author = $metadata['itunes_author'] ?? $data->author ?? null;
        if ($author) {
            $item->appendChild($dom->createElement('itunes:author', $author));
        }

        if (!empty($metadata['itunes_subtitle'])) {
            $item->appendChild($dom->createElement('itunes:subtitle', $metadata['itunes_subtitle']));
        }

        $summary = $metadata['itunes_summary'] ?? $data->description ?? null;
        if ($summary) {
            $item->appendChild($dom->createElement('itunes:summary', $summary));
        }

        if (!empty($metadata['itunes_duration'])) {
            $item->appendChild($dom->createElement('itunes:duration', (string)$metadata['itunes_duration']));
        }

        if (isset($metadata['itunes_explicit'])) {
            $explicit = $this->normalizeExplicit($metadata['itunes_explicit']);
            $item->appendChild($dom->createElement('itunes:explicit', $explicit));
        }

        if (!empty($metadata['itunes_episode'])) {
            $item->appendChild($dom->createElement('itunes:episode', (string)(int)$metadata['itunes_episode']));
        }

        if (!empty($metadata['itunes_season'])) {
            $item->appendChild($dom->createElement('itunes:season', (string)(int)$metadata['itunes_season']));
        }

        if (!empty($metadata['itunes_image'])) {
            $image = $dom->createElement('itunes:image');
            $image->setAttribute('href', $metadata['itunes_image']);
            $item->appendChild($image);
        }
    }

    private function normalizeExplicit(mixed $value): string
    {
        return ($value === 'true' || $value === true) ? 'true' : 'false';
    }

    private function resolveUrl(string $url, string $baseUrl): string
    {
        if (preg_match('~^https?://~i', $url)) {
            return $url;
        }
        return $url;
    }
}
