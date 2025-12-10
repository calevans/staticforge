<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\RssFeed\Services;

use DOMDocument;
use DOMElement;
use EICC\StaticForge\Features\RssFeed\Models\FeedChannel;
use EICC\StaticForge\Features\RssFeed\Models\FeedItem;
use EICC\StaticForge\Features\RssFeed\Services\Extensions\FeedExtensionInterface;

class RssBuilder
{
    /** @var FeedExtensionInterface[] */
    private array $extensions = [];

    public function addExtension(FeedExtensionInterface $extension): void
    {
        $this->extensions[] = $extension;
    }

    /**
     * @param FeedChannel $channelData
     * @param FeedItem[] $items
     * @return string XML content
     */
    public function build(FeedChannel $channelData, array $items): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $rss = $dom->createElement('rss');
        $rss->setAttribute('version', '2.0');

        // Add standard namespaces
        $rss->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $rss->setAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');

        // Add extension namespaces
        foreach ($this->extensions as $extension) {
            foreach ($extension->getNamespaces() as $prefix => $uri) {
                $rss->setAttribute('xmlns:' . $prefix, $uri);
            }
        }

        $dom->appendChild($rss);

        $channel = $dom->createElement('channel');
        $rss->appendChild($channel);

        // Standard Channel Elements
        $this->addTextElement($dom, $channel, 'title', $channelData->title);
        $this->addTextElement($dom, $channel, 'link', $channelData->link);
        $this->addTextElement($dom, $channel, 'description', $channelData->description);
        $this->addTextElement($dom, $channel, 'language', $channelData->language);
        $this->addTextElement($dom, $channel, 'lastBuildDate', $channelData->lastBuildDate);

        if ($channelData->copyright) {
            $this->addTextElement($dom, $channel, 'copyright', $channelData->copyright);
        }

        // Atom Link
        $atomLink = $dom->createElement('atom:link');
        $atomLink->setAttribute('href', $channelData->atomLink);
        $atomLink->setAttribute('rel', 'self');
        $atomLink->setAttribute('type', 'application/rss+xml');
        $channel->appendChild($atomLink);

        // Apply Extensions to Channel
        foreach ($this->extensions as $extension) {
            $extension->applyToChannel($channel, $channelData, $dom);
        }

        // Items
        foreach ($items as $itemData) {
            $item = $dom->createElement('item');

            $this->addTextElement($dom, $item, 'title', $itemData->title);
            $this->addTextElement($dom, $item, 'link', $itemData->link);
            $this->addTextElement($dom, $item, 'guid', $itemData->guid);
            $this->addTextElement($dom, $item, 'pubDate', $itemData->pubDate);

            if ($itemData->description) {
                $this->addTextElement($dom, $item, 'description', $itemData->description);
            }

            if ($itemData->content) {
                $content = $dom->createElement('content:encoded');
                $content->appendChild($dom->createCDATASection($itemData->content));
                $item->appendChild($content);
            }

            if ($itemData->author) {
                $this->addTextElement($dom, $item, 'author', $itemData->author);
            }

            if ($itemData->enclosure) {
                $enclosure = $dom->createElement('enclosure');
                $enclosure->setAttribute('url', $itemData->enclosure['url']);
                $enclosure->setAttribute('length', (string)$itemData->enclosure['length']);
                $enclosure->setAttribute('type', $itemData->enclosure['type']);
                $item->appendChild($enclosure);
            }

            // Apply Extensions to Item
            foreach ($this->extensions as $extension) {
                $extension->applyToItem($item, $itemData, $dom);
            }

            $channel->appendChild($item);
        }

        $xml = $dom->saveXML();
        if ($xml === false) {
            throw new \RuntimeException('Failed to generate RSS XML');
        }

        return $xml;
    }

    private function addTextElement(DOMDocument $dom, DOMElement $parent, string $name, string $value): void
    {
        $element = $dom->createElement($name);
        $element->appendChild($dom->createTextNode($value));
        $parent->appendChild($element);
    }
}
