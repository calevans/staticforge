<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\SiteFeed\Services;

use DateTimeImmutable;
use XMLWriter;

class Rss2Builder implements FeedBuilderInterface
{
    public function getFileName(): string
    {
        return 'feed.xml';
    }

    public function build(array $channel, array $items, DateTimeImmutable $now): string
    {
        $w = new XMLWriter();
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString('  ');
        $w->startDocument('1.0', 'UTF-8');

        $w->startElement('rss');
        $w->writeAttribute('version', '2.0');
        $w->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $w->writeAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
        $w->writeAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $w->startElement('channel');

        $this->text($w, 'title', $channel['title'] ?? '');
        $this->text($w, 'link', $channel['link'] ?? '');
        $this->text($w, 'description', $channel['description'] ?? '');
        if (!empty($channel['language']) && is_string($channel['language'])) {
            $this->text($w, 'language', $channel['language']);
        }

        if (!empty($channel['feed_url'])) {
            $w->startElement('atom:link');
            $w->writeAttribute('href', FeedText::xml((string) $channel['feed_url']));
            $w->writeAttribute('rel', 'self');
            $w->writeAttribute('type', 'application/rss+xml');
            $w->endElement();
        }

        if ($items !== []) {
            $newest = $items[0]->updated;
            foreach ($items as $item) {
                if ($item->updated > $newest) {
                    $newest = $item->updated;
                }
            }
            $this->text($w, 'lastBuildDate', $newest->format(DATE_RSS));
        }

        foreach ($items as $item) {
            $w->startElement('item');
            $this->text($w, 'title', $item->title);
            $this->text($w, 'link', $item->url);
            $w->startElement('guid');
            $w->writeAttribute('isPermaLink', $item->id === $item->url ? 'true' : 'false');
            $w->text(FeedText::xml($item->id));
            $w->endElement();
            $this->text($w, 'pubDate', $item->published->format(DATE_RSS));
            $this->text($w, 'description', $item->summary);
            $this->text($w, 'content:encoded', $item->contentHtml);
            if ($item->author !== null && $item->author !== '') {
                $this->text($w, 'dc:creator', $item->author);
            }
            foreach ($item->tags as $tag) {
                $this->text($w, 'category', $tag);
            }
            $w->endElement();
        }

        $w->endElement();
        $w->endElement();
        $w->endDocument();

        return $w->outputMemory();
    }

    private function text(XMLWriter $w, string $name, mixed $value): void
    {
        $w->writeElement($name, FeedText::xml((string) $value));
    }
}
