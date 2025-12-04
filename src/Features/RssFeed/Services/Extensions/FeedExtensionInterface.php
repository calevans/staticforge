<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\RssFeed\Services\Extensions;

use DOMDocument;
use DOMElement;
use EICC\StaticForge\Features\RssFeed\Models\FeedChannel;
use EICC\StaticForge\Features\RssFeed\Models\FeedItem;

interface FeedExtensionInterface
{
    /**
     * Get XML namespaces required by this extension
     * @return array<string, string> prefix => uri
     */
    public function getNamespaces(): array;

    /**
     * Apply extension data to the channel element
     */
    public function applyToChannel(DOMElement $channel, FeedChannel $data, DOMDocument $dom): void;

    /**
     * Apply extension data to an item element
     */
    public function applyToItem(DOMElement $item, FeedItem $data, DOMDocument $dom): void;
}
