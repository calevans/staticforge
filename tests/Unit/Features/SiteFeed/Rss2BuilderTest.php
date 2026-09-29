<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\SiteFeed;

use DOMDocument;
use EICC\StaticForge\Features\SiteFeed\Services\Rss2Builder;
use PHPUnit\Framework\TestCase;

class Rss2BuilderTest extends TestCase
{
    use ItemFactory;

    private function dom(string $xml): DOMDocument
    {
        $dom = new DOMDocument();
        if ($xml === '') {
            $this->fail('feed is empty');
        }
        $this->assertTrue($dom->loadXML($xml), 'output must be well-formed XML');

        return $dom;
    }

    public function testFileNameIsFeedXml(): void
    {
        $this->assertSame('feed.xml', (new Rss2Builder())->getFileName());
    }

    public function testItemFieldsAreWrittenAsRss2Elements(): void
    {
        $xml = (new Rss2Builder())->build(
            $this->channel(),
            [$this->item('Hello', '<p>x</p>', 'Sum', author: 'Ada', tags: ['a', 'b'])],
            $this->now()
        );

        $dom = $this->dom($xml);
        $this->assertSame('Hello', $dom->getElementsByTagName('title')->item(1)?->textContent);
        $this->assertSame('<p>x</p>', $dom->getElementsByTagName('encoded')->item(0)?->textContent);
        $this->assertSame('Ada', $dom->getElementsByTagName('creator')->item(0)?->textContent);
        $this->assertSame(2, $dom->getElementsByTagName('category')->length);
        $this->assertSame('true', $dom->getElementsByTagName('guid')->item(0)?->getAttribute('isPermaLink'));
    }

    public function testGuidIsNotPermalinkWhenIdDiffersFromUrl(): void
    {
        $item = $this->item();
        $item->id = 'tag:example.com,2024:a';

        $dom = $this->dom((new Rss2Builder())->build($this->channel(), [$item], $this->now()));

        $this->assertSame('false', $dom->getElementsByTagName('guid')->item(0)?->getAttribute('isPermaLink'));
    }

    public function testLastBuildDateIsTheNewestUpdatedNotTheWallClock(): void
    {
        $xml = (new Rss2Builder())->build($this->channel(), [
            $this->item('Newer published', published: '2024-03-01T00:00:00+00:00'),
            $this->item('Edited later', published: '2024-01-01T00:00:00+00:00', updated: '2024-06-01T12:00:00+00:00'),
        ], $this->now());

        $this->assertStringContainsString('<lastBuildDate>Sat, 01 Jun 2024 12:00:00 +0000</lastBuildDate>', $xml);
    }

    public function testEmptyItemListIsValidAndOmitsLastBuildDate(): void
    {
        $dom = $this->dom((new Rss2Builder())->build($this->channel(), [], $this->now()));

        $this->assertSame(1, $dom->getElementsByTagName('channel')->length);
        $this->assertSame(0, $dom->getElementsByTagName('item')->length);
        $this->assertSame(0, $dom->getElementsByTagName('lastBuildDate')->length);
    }

    public function testCdataTerminatorControlCharsAndInvalidUtf8StillProduceWellFormedXml(): void
    {
        $bad = "a ]]> b \x00\x01\x0B\x1F c \xFF d \xC3\x28";
        $item = $this->item($bad, "<p>{$bad}</p>", $bad, author: $bad, tags: [$bad]);

        $xml = (new Rss2Builder())->build($this->channel(), [$item], $this->now());

        $dom = $this->dom($xml);
        $this->assertStringContainsString(']]>', $dom->getElementsByTagName('title')->item(1)->textContent ?? '');
        $this->assertSame(0, preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $xml));
        $this->assertStringNotContainsString('<![CDATA[', $xml);
        $this->assertTrue(mb_check_encoding($xml, 'UTF-8'));
    }

    public function testLanguageIsWrittenWhenChannelProvidesOne(): void
    {
        $channel = $this->channel();
        $channel['language'] = 'en-gb';

        $dom = $this->dom((new Rss2Builder())->build($channel, [], $this->now()));

        $this->assertSame('en-gb', $dom->getElementsByTagName('language')->item(0)?->textContent);
    }

    public function testSelfLinkIsOmittedWithoutAFeedUrl(): void
    {
        $channel = $this->channel();
        unset($channel['feed_url']);

        $xml = (new Rss2Builder())->build($channel, [], $this->now());

        $this->assertStringNotContainsString('rel="self"', $xml);
    }
}
