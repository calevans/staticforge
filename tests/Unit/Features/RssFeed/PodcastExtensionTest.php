<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\RssFeed;

use EICC\StaticForge\Features\RssFeed\Models\FeedChannel;
use EICC\StaticForge\Features\RssFeed\Models\FeedItem;
use EICC\StaticForge\Features\RssFeed\Services\Extensions\PodcastExtension;
use EICC\StaticForge\Features\RssFeed\Services\RssBuilder;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class PodcastExtensionTest extends UnitTestCase
{
    private RssBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new RssBuilder();
        $this->builder->addExtension(new PodcastExtension());
    }

    public function testGeneratePodcastFeedXml(): void
    {
        $categoryMetadata = [
            'rss_type' => 'podcast',
            'itunes_author' => 'Channel Author',
            'itunes_summary' => 'Channel Summary',
            'itunes_owner_name' => 'Owner Name',
            'itunes_owner_email' => 'owner@example.com',
            'itunes_image' => 'https://example.com/images/channel.jpg',
            'itunes_category' => ['Arts']
        ];

        $channel = new FeedChannel(
            'Podcast',
            'https://example.com/podcast/',
            'Podcast Description',
            'https://example.com/podcast/rss.xml',
            $categoryMetadata
        );

        $itemMetadata = [
            'author' => 'John Doe',
            'itunes_author' => 'Jane Doe',
            'itunes_summary' => 'iTunes Summary',
            'itunes_subtitle' => 'iTunes Subtitle',
            'itunes_duration' => '10:00',
            'itunes_explicit' => 'false',
            'itunes_episode' => 1,
            'itunes_season' => 1,
            'itunes_image' => 'https://example.com/images/cover.jpg'
        ];

        $item = new FeedItem(
            'Episode 1',
            'https://example.com/episode-1.html',
            'https://example.com/episode-1.html',
            'Sun, 01 Jan 2023 00:00:00 +0000',
            $itemMetadata
        );

        $item->enclosure = [
            'url' => 'https://example.com/audio.mp3',
            'length' => 123456,
            'type' => 'audio/mpeg'
        ];

        $xml = $this->builder->build($channel, [$item]);

        // Check Channel Tags
        $this->assertStringContainsString('xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"', $xml);
        $this->assertStringContainsString('<itunes:author>Channel Author</itunes:author>', $xml);
        $this->assertStringContainsString('<itunes:summary>Channel Summary</itunes:summary>', $xml);

        // Check Item Tags
        $this->assertStringContainsString('<enclosure url="https://example.com/audio.mp3"', $xml);
        $this->assertStringContainsString('<itunes:duration>10:00</itunes:duration>', $xml);
        $this->assertStringContainsString('<itunes:explicit>false</itunes:explicit>', $xml);
        $this->assertStringContainsString('<itunes:episode>1</itunes:episode>', $xml);
        $this->assertStringContainsString('<itunes:season>1</itunes:season>', $xml);
        $this->assertStringContainsString('<itunes:image href="https://example.com/images/cover.jpg"', $xml);

        $this->assertStringContainsString('<itunes:author>Jane Doe</itunes:author>', $xml);
        $this->assertStringContainsString('<itunes:summary>iTunes Summary</itunes:summary>', $xml);
        $this->assertStringContainsString('<itunes:subtitle>iTunes Subtitle</itunes:subtitle>', $xml);
    }

    public function testGeneratePodcastFeedXmlDefaults(): void
    {
        $categoryMetadata = [
            'rss_type' => 'podcast'
        ];

        $channel = new FeedChannel(
            'Podcast',
            'https://example.com/podcast/',
            'Podcast Description',
            'https://example.com/podcast/rss.xml',
            $categoryMetadata
        );

        $item = new FeedItem(
            'Episode 1',
            'https://example.com/episode-1.html',
            'https://example.com/episode-1.html',
            'Sun, 01 Jan 2023 00:00:00 +0000',
            ['author' => 'John Doe']
        );

        $xml = $this->builder->build($channel, [$item]);

        $this->assertStringContainsString('xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"', $xml);
        $this->assertStringContainsString('<itunes:type>episodic</itunes:type>', $xml);
    }
}
