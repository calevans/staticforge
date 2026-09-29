<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\SiteFeed\Services;

use DateTimeImmutable;
use XMLWriter;

class AtomBuilder implements FeedBuilderInterface
{
    public function getFileName(): string
    {
        return 'feed.atom';
    }

    public function build(array $channel, array $items, DateTimeImmutable $now): string
    {
        // Fixed so an empty feed is byte-identical between builds
        $updated = new DateTimeImmutable('1970-01-01T00:00:00Z');
        if ($items !== []) {
            $updated = $items[0]->updated;
            foreach ($items as $item) {
                if ($item->updated > $updated) {
                    $updated = $item->updated;
                }
            }
        }

        $w = new XMLWriter();
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString('  ');
        $w->startDocument('1.0', 'UTF-8');

        $w->startElement('feed');
        $w->writeAttribute('xmlns', 'http://www.w3.org/2005/Atom');
        if (!empty($channel['language']) && is_string($channel['language'])) {
            $w->writeAttribute('xml:lang', FeedText::xml($channel['language']));
        }

        $this->text($w, 'title', $channel['title'] ?? '');
        $this->text($w, 'subtitle', $channel['description'] ?? '');
        $this->text($w, 'id', $channel['feed_url'] ?? ($channel['link'] ?? ''));
        $this->text($w, 'updated', $updated->format(DATE_ATOM));
        $this->link($w, 'self', (string) ($channel['feed_url'] ?? ''), 'application/atom+xml');
        $this->link($w, 'alternate', (string) ($channel['link'] ?? ''), 'text/html');

        // Atom requires an author on the feed or on every entry
        $w->startElement('author');
        $this->text($w, 'name', $channel['author'] ?? ($channel['title'] ?? ''));
        $w->endElement();

        foreach ($items as $item) {
            $w->startElement('entry');
            $this->text($w, 'title', $item->title);
            $this->text($w, 'id', $item->id);
            $this->link($w, 'alternate', $item->url, 'text/html');
            $this->text($w, 'published', $item->published->format(DATE_ATOM));
            $this->text($w, 'updated', $item->updated->format(DATE_ATOM));
            if ($item->author !== null && $item->author !== '') {
                $w->startElement('author');
                $this->text($w, 'name', $item->author);
                $w->endElement();
            }
            foreach ($item->tags as $tag) {
                $w->startElement('category');
                $w->writeAttribute('term', FeedText::xml($tag));
                $w->endElement();
            }
            $this->text($w, 'summary', $item->summary);
            $w->startElement('content');
            $w->writeAttribute('type', 'html');
            $w->text(FeedText::xml($item->contentHtml));
            $w->endElement();
            $w->endElement();
        }

        $w->endElement();
        $w->endDocument();

        return $w->outputMemory();
    }

    private function link(XMLWriter $w, string $rel, string $href, string $type): void
    {
        if ($href === '') {
            return;
        }
        $w->startElement('link');
        $w->writeAttribute('rel', $rel);
        $w->writeAttribute('type', $type);
        $w->writeAttribute('href', FeedText::xml($href));
        $w->endElement();
    }

    private function text(XMLWriter $w, string $name, mixed $value): void
    {
        $w->writeElement($name, FeedText::xml((string) $value));
    }
}
