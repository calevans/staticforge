<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\RssFeed;

use EICC\StaticForge\Features\RssFeed\Models\FeedChannel;
use EICC\StaticForge\Features\RssFeed\Models\FeedItem;
use EICC\StaticForge\Features\RssFeed\Services\RssBuilder;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class RssBuilderTest extends UnitTestCase
{
    private RssBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new RssBuilder();
    }

    public function testGenerateFeedXml(): void
    {
        $channel = new FeedChannel(
            'My Site - Tech',
            'https://example.com/tech/',
            'Tech articles from My Site',
            'https://example.com/tech/rss.xml'
        );

        $items = [
            new FeedItem(
                'Test Post',
                'https://example.com/test-post.html',
                'https://example.com/test-post.html',
                'Sun, 01 Jan 2023 00:00:00 +0000',
                ['author' => 'John Doe']
            )
        ];
        $items[0]->author = 'John Doe';

        $xml = $this->builder->build($channel, $items);

        $this->assertStringContainsString('<title>My Site - Tech</title>', $xml);
        $this->assertStringContainsString('<link>https://example.com/tech/</link>', $xml);
        $this->assertStringContainsString('<item>', $xml);
        $this->assertStringContainsString('<title>Test Post</title>', $xml);
        $this->assertStringContainsString('<link>https://example.com/test-post.html</link>', $xml);
        $this->assertStringContainsString('<author>John Doe</author>', $xml);
    }

    public function testGenerateFeedXmlEscapesSpecialChars(): void
    {
        $channel = new FeedChannel(
            'Tech & Science',
            'https://example.com/tech/',
            'A < B',
            'https://example.com/tech/rss.xml'
        );

        $items = [
            new FeedItem(
                'Test & Demo',
                'https://example.com/test.html',
                'https://example.com/test.html',
                'Sun, 01 Jan 2023 00:00:00 +0000'
            )
        ];

        $xml = $this->builder->build($channel, $items);

        $this->assertStringContainsString('Tech &amp; Science', $xml);
        $this->assertStringContainsString('Test &amp; Demo', $xml);
        $this->assertStringContainsString('A &lt; B', $xml);
    }
}
