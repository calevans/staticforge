<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\RssFeed;

use EICC\StaticForge\Features\RssFeed\RssFeedGenerator;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class RssFeedGeneratorTest extends UnitTestCase
{
    private RssFeedGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new RssFeedGenerator();
    }

    public function testGenerateFeedXml(): void
    {
        $files = [
            [
                'title' => 'Test Post',
                'url' => '/test-post.html',
                'date' => '2023-01-01',
                'description' => 'Test Description',
                'metadata' => ['author' => 'John Doe']
            ]
        ];

        $xml = $this->generator->generateFeedXml(
            'Tech',
            'tech',
            $files,
            'https://example.com',
            'My Site'
        );

        $this->assertStringContainsString('<title>My Site - Tech</title>', $xml);
        $this->assertStringContainsString('<link>https://example.com/tech/</link>', $xml);
        $this->assertStringContainsString('<item>', $xml);
        $this->assertStringContainsString('<title>Test Post</title>', $xml);
        $this->assertStringContainsString('<link>https://example.com/test-post.html</link>', $xml);
        $this->assertStringContainsString('<author>John Doe</author>', $xml);
    }

    public function testGenerateFeedXmlEscapesSpecialChars(): void
    {
        $files = [
            [
                'title' => 'Test & Demo',
                'url' => '/test.html',
                'date' => '2023-01-01',
                'description' => 'A < B',
                'metadata' => []
            ]
        ];

        $xml = $this->generator->generateFeedXml(
            'Tech & Science',
            'tech',
            $files,
            'https://example.com',
            'My Site'
        );

        $this->assertStringContainsString('Tech &amp; Science', $xml);
        $this->assertStringContainsString('Test &amp; Demo', $xml);
        $this->assertStringContainsString('A &lt; B', $xml);
    }
}
