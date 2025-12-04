<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\RssFeed;

use EICC\StaticForge\Features\RssFeed\RssFeedGenerator;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class RssFeedGeneratorPodcastTest extends UnitTestCase
{
    private RssFeedGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new RssFeedGenerator();
    }

    public function testGeneratePodcastFeedXml(): void
    {
        $files = [
            [
                'title' => 'Episode 1',
                'url' => '/episode-1.html',
                'date' => '2023-01-01',
                'description' => 'Episode Description',
                'metadata' => [
                    'author' => 'John Doe',
                    'itunes_author' => 'Jane Doe',
                    'itunes_summary' => 'iTunes Summary',
                    'itunes_subtitle' => 'iTunes Subtitle',
                    'itunes_duration' => '10:00',
                    'itunes_explicit' => 'false',
                    'itunes_episode' => 1,
                    'itunes_season' => 1,
                    'itunes_image' => '/images/cover.jpg'
                ],
                'enclosure' => [
                    'url' => '/audio.mp3',
                    'length' => 123456,
                    'type' => 'audio/mpeg'
                ]
            ]
        ];

        $categoryMetadata = [
            'rss_type' => 'podcast',
            'itunes_author' => 'Channel Author',
            'itunes_summary' => 'Channel Summary',
            'itunes_owner_name' => 'Owner Name',
            'itunes_owner_email' => 'owner@example.com',
            'itunes_image' => '/images/channel.jpg',
            'itunes_category' => ['Arts']
        ];

        $xml = $this->generator->generateFeedXml(
            'Podcast',
            'podcast',
            $files,
            'https://example.com',
            'My Site',
            $categoryMetadata
        );

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
        $this->assertStringContainsString('<itunes:image href="https://example.com/images/cover.jpg" />', $xml);

        // These are currently missing and expected to fail until implemented
        $this->assertStringContainsString('<itunes:author>Jane Doe</itunes:author>', $xml);
        $this->assertStringContainsString('<itunes:summary>iTunes Summary</itunes:summary>', $xml);
        $this->assertStringContainsString('<itunes:subtitle>iTunes Subtitle</itunes:subtitle>', $xml);
    }

    public function testGeneratePodcastFeedXmlDefaults(): void
    {
        $files = [
            [
                'title' => 'Episode 1',
                'url' => '/episode-1.html',
                'date' => '2023-01-01',
                'description' => 'Episode Description',
                'metadata' => [
                    'author' => 'John Doe'
                ]
            ]
        ];

        $categoryMetadata = [
            'rss_type' => 'podcast'
        ];

        $xml = $this->generator->generateFeedXml(
            'Podcast',
            'podcast',
            $files,
            'https://example.com',
            'My Site',
            $categoryMetadata
        );

        // Should default itunes:summary to description
        $this->assertStringContainsString('<itunes:summary>Episode Description</itunes:summary>', $xml);
        // Should default itunes:author to author
        $this->assertStringContainsString('<itunes:author>John Doe</itunes:author>', $xml);
    }

    public function testGeneratePodcastFeedXmlUsesCategoryAuthorFallback(): void
    {
        $files = [
            [
                'title' => 'Episode 1',
                'url' => '/episode-1.html',
                'date' => '2023-01-01',
                'description' => 'Episode Description',
                'metadata' => [] // No author here
            ]
        ];

        $categoryMetadata = [
            'rss_type' => 'podcast',
            'itunes_author' => 'Category Author'
        ];

        $xml = $this->generator->generateFeedXml(
            'Podcast',
            'podcast',
            $files,
            'https://example.com',
            'My Site',
            $categoryMetadata
        );

        // Should use category author when item author is missing
        // We need to make sure it's inside the item, not just the channel
        // Simple check: split by <item> and check the second part
        $parts = explode('<item>', $xml);
        $this->assertCount(2, $parts);
        $itemXml = $parts[1];

        $this->assertStringContainsString('<itunes:author>Category Author</itunes:author>', $itemXml);
    }

    public function testGeneratePodcastFeedXmlIncludesContentEncoded(): void
    {
        $files = [
            [
                'title' => 'Episode 1',
                'url' => '/episode-1.html',
                'date' => '2023-01-01',
                'description' => 'Episode Description',
                'content' => '<p>This is the <strong>HTML</strong> content.</p>',
                'metadata' => []
            ]
        ];

        $categoryMetadata = [
            'rss_type' => 'podcast'
        ];

        $xml = $this->generator->generateFeedXml(
            'Podcast',
            'podcast',
            $files,
            'https://example.com',
            'My Site',
            $categoryMetadata
        );

        $this->assertStringContainsString('xmlns:content="http://purl.org/rss/1.0/modules/content/"', $xml);
        $this->assertStringContainsString('<content:encoded><![CDATA[<p>This is the <strong>HTML</strong> content.</p>]]></content:encoded>', $xml);
    }
}
