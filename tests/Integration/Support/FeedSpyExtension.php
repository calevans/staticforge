<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration\Support;

use DOMDocument;
use DOMElement;
use EICC\StaticForge\Features\RssFeed\Models\FeedChannel;
use EICC\StaticForge\Features\RssFeed\Models\FeedItem;
use EICC\StaticForge\Features\RssFeed\Services\Extensions\FeedExtensionInterface;

final class FeedSpyExtension implements FeedExtensionInterface
{
    public function getNamespaces(): array
    {
        return ['itunes' => 'http://www.itunes.com/dtds/podcast-1.0.dtd'];
    }

   
    public function applyToChannel(DOMElement $channel, FeedChannel $data, DOMDocument $dom): void
    {
        $channel->appendChild($dom->createElement('itunes:explicit', 'false'));
    }

   
    public function applyToItem(DOMElement $item, FeedItem $data, DOMDocument $dom): void
    {
        if ($data->enclosure !== null) {
            $item->appendChild($dom->createElement('itunes:duration', '00:01:00'));
        }
    }
}
