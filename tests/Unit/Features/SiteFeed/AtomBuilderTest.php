<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\SiteFeed;

use DOMDocument;
use EICC\StaticForge\Features\SiteFeed\Services\AtomBuilder;
use PHPUnit\Framework\TestCase;

class AtomBuilderTest extends TestCase
{
    use ItemFactory;

    private const NS = 'http://www.w3.org/2005/Atom';

    private function dom(string $xml): DOMDocument
    {
        $dom = new DOMDocument();
        if ($xml === '') {
            $this->fail('feed is empty');
        }
        $this->assertTrue($dom->loadXML($xml), 'output must be well-formed XML');

        return $dom;
    }

    public function testFileNameIsFeedAtom(): void
    {
        $this->assertSame('feed.atom', (new AtomBuilder())->getFileName());
    }

    public function testFeedAndEntryCarryTheRequiredElements(): void
    {
        $dom = $this->dom((new AtomBuilder())->build($this->channel(), [$this->item(tags: ['t'])], $this->now()));

        $this->assertSame(self::NS, $dom->documentElement?->namespaceURI);
        foreach (['id', 'title', 'updated'] as $name) {
            $this->assertGreaterThanOrEqual(2, $dom->getElementsByTagNameNS(self::NS, $name)->length, $name);
        }
        $this->assertSame('https://example.com/feed', $dom->getElementsByTagNameNS(self::NS, 'id')->item(0)?->textContent);
        $this->assertSame('t', $dom->getElementsByTagNameNS(self::NS, 'category')->item(0)?->getAttribute('term'));
        $this->assertSame('html', $dom->getElementsByTagNameNS(self::NS, 'content')->item(0)?->getAttribute('type'));
    }

    public function testFeedUpdatedIsTheNewestEntryUpdated(): void
    {
        $xml = (new AtomBuilder())->build($this->channel(), [
            $this->item('a', published: '2024-03-01T00:00:00+00:00'),
            $this->item('b', published: '2024-01-01T00:00:00+00:00', updated: '2024-07-01T00:00:00+00:00'),
        ], $this->now());

        $dom = $this->dom($xml);
        $this->assertSame('2024-07-01T00:00:00+00:00', $dom->getElementsByTagNameNS(self::NS, 'updated')->item(0)?->textContent);
    }

    public function testEmptyFeedUsesAFixedEpochForUpdatedAndHasNoEntries(): void
    {
        $dom = $this->dom((new AtomBuilder())->build($this->channel(), [], $this->now()));

        $this->assertSame(0, $dom->getElementsByTagNameNS(self::NS, 'entry')->length);
        $this->assertSame('1970-01-01T00:00:00+00:00', $dom->getElementsByTagNameNS(self::NS, 'updated')->item(0)?->textContent);
    }

    public function testFeedAuthorIsAlwaysPresentBecauseAtomRequiresOne(): void
    {
        $dom = $this->dom((new AtomBuilder())->build($this->channel(), [$this->item()], $this->now()));

        $this->assertSame('Site', $dom->getElementsByTagNameNS(self::NS, 'name')->item(0)?->textContent);
    }

    public function testTerminatorControlCharsAndInvalidUtf8StillProduceWellFormedXml(): void
    {
        $bad = "a ]]> b \x00\x01\x0B\x1F c \xFF d \xC3\x28";
        $xml = (new AtomBuilder())->build(
            $this->channel(),
            [$this->item($bad, "<p>{$bad}</p>", $bad, author: $bad, tags: [$bad])],
            $this->now()
        );

        $this->dom($xml);
        $this->assertSame(0, preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $xml));
        $this->assertStringNotContainsString('<![CDATA[', $xml);
        $this->assertTrue(mb_check_encoding($xml, 'UTF-8'));
    }

    public function testHrefWithQuoteAndAngleBracketCannotBreakOutOfTheAttribute(): void
    {
        $item = $this->item(url: 'https://example.com/"><evil/>');

        $xml = (new AtomBuilder())->build($this->channel(), [$item], $this->now());

        $dom = $this->dom($xml);
        $this->assertSame(0, $dom->getElementsByTagName('evil')->length);
    }

    public function testXmlLangIsWrittenWhenChannelProvidesLanguage(): void
    {
        $channel = $this->channel();
        $channel['language'] = 'fr';

        $dom = $this->dom((new AtomBuilder())->build($channel, [], $this->now()));

        $this->assertSame('fr', $dom->documentElement?->getAttribute('xml:lang'));
    }
}
