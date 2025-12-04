<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features;

use EICC\StaticForge\Features\RssFeed\Feature;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class RssFeedFeatureTest extends UnitTestCase
{
    private Feature $feature;
    private EventManager $eventManager;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temp directory for tests with more entropy for parallel execution
        $this->tempDir = sys_get_temp_dir() . '/staticforge_rss_test_' . uniqid('', true) . '_' . getmypid();
        mkdir($this->tempDir, 0755, true);

        // Setup container
        $this->setContainerVariable('OUTPUT_DIR', $this->tempDir);
        $this->setContainerVariable('SITE_NAME', 'Test Site');
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com/');

        // Override site_config to ensure SITE_NAME is used or matches
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);

        $this->eventManager = new EventManager($this->container);

        $this->feature = new Feature();
        $this->feature->register($this->eventManager, $this->container);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->recursiveRemoveDirectory($this->tempDir);
        }
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testFeatureRegistration(): void
    {
        $this->assertInstanceOf(Feature::class, $this->feature);
        $this->assertEquals('RssFeed', $this->feature->getName());
    }

    public function testCollectsCategoryFiles(): void
    {
        $parameters = [
            'metadata' => [
                'title' => 'Test Article',
                'category' => 'Technology',
                'description' => 'A test article about technology'
            ],
            'output_path' => $this->tempDir . '/technology/test.html',
            'file_path' => $this->tempDir . '/content/test.md',
            'rendered_content' => '<p>This is a test article content.</p>'
        ];

        $result = $this->feature->collectCategoryFiles($this->container, $parameters);

        // Should return parameters unchanged
        $this->assertEquals($parameters, $result);
    }

    public function testIgnoresFilesWithoutCategory(): void
    {
        $parameters = [
            'metadata' => [
                'title' => 'Test Page'
            ],
            'output_path' => $this->tempDir . '/test.html',
            'file_path' => $this->tempDir . '/content/test.md',
            'rendered_content' => '<p>Test content</p>'
        ];

        $result = $this->feature->collectCategoryFiles($this->container, $parameters);

        // Should return parameters unchanged
        $this->assertEquals($parameters, $result);
    }

    public function testGeneratesRssFeedForSingleCategory(): void
    {
        // Collect some files
        $files = [
            [
                'title' => 'Article 1',
                'category' => 'Tech',
                'date' => '2024-01-01',
                'description' => 'First article'
            ],
            [
                'title' => 'Article 2',
                'category' => 'Tech',
                'date' => '2024-01-02',
                'description' => 'Second article'
            ]
        ];

        foreach ($files as $file) {
            $this->feature->collectCategoryFiles($this->container, [
                'metadata' => [
                    'title' => $file['title'],
                    'category' => $file['category'],
                    'date' => $file['date'],
                    'description' => $file['description']
                ],
                'output_path' => $this->tempDir . '/tech/' . strtolower(str_replace(' ', '-', $file['title'])) . '.html',
                'file_path' => $this->tempDir . '/content/' . strtolower(str_replace(' ', '-', $file['title'])) . '.md',
                'rendered_content' => '<p>' . $file['description'] . '</p>'
            ]);
        }

        // Generate RSS feeds
        $this->feature->generateRssFeeds($this->container, []);

        // Check that RSS file was created
        $rssPath = $this->tempDir . '/tech/rss.xml';
        $this->assertFileExists($rssPath);

        // Verify RSS content
        $xml = file_get_contents($rssPath);
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<rss version="2.0"', $xml);
        $this->assertStringContainsString('<title>Test Site - Tech</title>', $xml);
        $this->assertStringContainsString('<link>https://example.com/tech/</link>', $xml);
        $this->assertStringContainsString('Article 1', $xml);
        $this->assertStringContainsString('Article 2', $xml);
    }

    public function testGeneratesRssFeedForMultipleCategories(): void
    {
        // Collect files from different categories
        $files = [
            ['title' => 'Tech 1', 'category' => 'Technology', 'date' => '2024-01-01'],
            ['title' => 'Tech 2', 'category' => 'Technology', 'date' => '2024-01-02'],
            ['title' => 'Blog 1', 'category' => 'Blog', 'date' => '2024-01-03'],
            ['title' => 'Blog 2', 'category' => 'Blog', 'date' => '2024-01-04']
        ];

        foreach ($files as $file) {
            $sanitizedCategory = strtolower($file['category']);
            $this->feature->collectCategoryFiles($this->container, [
                'metadata' => [
                    'title' => $file['title'],
                    'category' => $file['category'],
                    'date' => $file['date']
                ],
                'output_path' => $this->tempDir . '/' . $sanitizedCategory . '/' . strtolower(str_replace(' ', '-', $file['title'])) . '.html',
                'file_path' => $this->tempDir . '/content/' . strtolower(str_replace(' ', '-', $file['title'])) . '.md',
                'rendered_content' => '<p>Test content</p>'
            ]);
        }

        $this->feature->generateRssFeeds($this->container, []);

        // Both category RSS feeds should exist
        $this->assertFileExists($this->tempDir . '/technology/rss.xml');
        $this->assertFileExists($this->tempDir . '/blog/rss.xml');

        // Each should contain only its articles
        $techXml = file_get_contents($this->tempDir . '/technology/rss.xml');
        $this->assertStringContainsString('Tech 1', $techXml);
        $this->assertStringContainsString('Tech 2', $techXml);
        $this->assertStringNotContainsString('Blog 1', $techXml);

        $blogXml = file_get_contents($this->tempDir . '/blog/rss.xml');
        $this->assertStringContainsString('Blog 1', $blogXml);
        $this->assertStringContainsString('Blog 2', $blogXml);
        $this->assertStringNotContainsString('Tech 1', $blogXml);
    }

    public function testSortsArticlesByDateNewestFirst(): void
    {
        // Collect files with different dates (not in order)
        $files = [
            ['title' => 'Old Article', 'date' => '2024-01-01'],
            ['title' => 'New Article', 'date' => '2024-01-05'],
            ['title' => 'Middle Article', 'date' => '2024-01-03']
        ];

        foreach ($files as $file) {
            $this->feature->collectCategoryFiles($this->container, [
                'metadata' => [
                    'title' => $file['title'],
                    'category' => 'Tech',
                    'date' => $file['date']
                ],
                'output_path' => $this->tempDir . '/tech/' . strtolower(str_replace(' ', '-', $file['title'])) . '.html',
                'file_path' => $this->tempDir . '/content/' . strtolower(str_replace(' ', '-', $file['title'])) . '.md',
                'rendered_content' => '<p>Content</p>'
            ]);
        }

        $this->feature->generateRssFeeds($this->container, []);

        $xml = file_get_contents($this->tempDir . '/tech/rss.xml');

        // Check that newest article appears first
        $posNew = strpos($xml, 'New Article');
        $posMiddle = strpos($xml, 'Middle Article');
        $posOld = strpos($xml, 'Old Article');

        $this->assertLessThan($posMiddle, $posNew, 'New Article should appear before Middle Article');
        $this->assertLessThan($posOld, $posMiddle, 'Middle Article should appear before Old Article');
    }

    public function testIncludesDescriptionFromMetadata(): void
    {
        $this->feature->collectCategoryFiles($this->container, [
            'metadata' => [
                'title' => 'Test Article',
                'category' => 'Tech',
                'description' => 'This is a custom description from metadata'
            ],
            'output_path' => $this->tempDir . '/tech/test.html',
            'file_path' => $this->tempDir . '/content/test.md',
            'rendered_content' => '<p>This is the full article content that is much longer.</p>'
        ]);

        $this->feature->generateRssFeeds($this->container, []);

        $xml = file_get_contents($this->tempDir . '/tech/rss.xml');
        $this->assertStringContainsString('This is a custom description from metadata', $xml);
    }

    public function testExtractsDescriptionFromContent(): void
    {
        $longContent = '<p>' . str_repeat('This is a very long article content. ', 50) . '</p>';

        $this->feature->collectCategoryFiles($this->container, [
            'metadata' => [
                'title' => 'Test Article',
                'category' => 'Tech'
            ],
            'output_path' => $this->tempDir . '/tech/test.html',
            'file_path' => $this->tempDir . '/content/test.md',
            'rendered_content' => $longContent
        ]);

        $this->feature->generateRssFeeds($this->container, []);

        $xml = file_get_contents($this->tempDir . '/tech/rss.xml');
        $this->assertStringContainsString('<description>', $xml);
        // Description should be truncated
        $this->assertStringContainsString('...', $xml);
    }

    public function testIncludesAuthorWhenProvided(): void
    {
        $this->feature->collectCategoryFiles($this->container, [
            'metadata' => [
                'title' => 'Test Article',
                'category' => 'Tech',
                'author' => 'John Doe'
            ],
            'output_path' => $this->tempDir . '/tech/test.html',
            'file_path' => $this->tempDir . '/content/test.md',
            'rendered_content' => '<p>Content</p>'
        ]);

        $this->feature->generateRssFeeds($this->container, []);

        $xml = file_get_contents($this->tempDir . '/tech/rss.xml');
        $this->assertStringContainsString('<author>John Doe</author>', $xml);
    }

    public function testUsesPublishedDateFromMetadata(): void
    {
        $this->feature->collectCategoryFiles($this->container, [
            'metadata' => [
                'title' => 'Test Article',
                'category' => 'Tech',
                'published_date' => '2024-06-15'
            ],
            'output_path' => $this->tempDir . '/tech/test.html',
            'file_path' => $this->tempDir . '/content/test.md',
            'rendered_content' => '<p>Content</p>'
        ]);

        $this->feature->generateRssFeeds($this->container, []);

        $xml = file_get_contents($this->tempDir . '/tech/rss.xml');
        // Should contain the formatted date
        $this->assertStringContainsString('2024', $xml);
        $this->assertStringContainsString('Jun', $xml);
    }

    public function testEscapesXmlSpecialCharacters(): void
    {
        $this->feature->collectCategoryFiles($this->container, [
            'metadata' => [
                'title' => 'Test & Special <Characters>',
                'category' => 'Tech',
                'description' => 'Description with "quotes" and \'apostrophes\''
            ],
            'output_path' => $this->tempDir . '/tech/test.html',
            'file_path' => $this->tempDir . '/content/test.md',
            'rendered_content' => '<p>Content</p>'
        ]);

        $this->feature->generateRssFeeds($this->container, []);

        $xml = file_get_contents($this->tempDir . '/tech/rss.xml');

        // XML should be valid (no unescaped special characters)
        $this->assertStringContainsString('&amp;', $xml);
        $this->assertStringContainsString('&lt;', $xml);
        $this->assertStringContainsString('&gt;', $xml);
        // DOMDocument doesn't always escape quotes in text nodes as it's not strictly required
        $this->assertStringContainsString('Description with "quotes" and \'apostrophes\'', $xml);
    }

    public function testSkipsGenerationWhenNoCategories(): void
    {
        // Don't collect any files
        $result = $this->feature->generateRssFeeds($this->container, []);

        // Should return parameters unchanged
        $this->assertEquals([], $result);

        // Should not create any RSS files
        $files = glob($this->tempDir . '/*/rss.xml');
        $this->assertEmpty($files);
    }

    public function testSanitizesCategoryNames(): void
    {
        $this->feature->collectCategoryFiles($this->container, [
            'metadata' => [
                'title' => 'Test Article',
                'category' => 'Web Development & Design'
            ],
            'output_path' => $this->tempDir . '/web-development-design/test.html',
            'file_path' => $this->tempDir . '/content/test.md',
            'rendered_content' => '<p>Content</p>'
        ]);

        $this->feature->generateRssFeeds($this->container, []);

        // RSS file should be in sanitized directory
        $this->assertFileExists($this->tempDir . '/web-development-design/rss.xml');
    }

    public function testIncludesCorrectRssMetadata(): void
    {
        $this->feature->collectCategoryFiles($this->container, [
            'metadata' => [
                'title' => 'Test Article',
                'category' => 'Tech'
            ],
            'output_path' => $this->tempDir . '/tech/test.html',
            'file_path' => $this->tempDir . '/content/test.md',
            'rendered_content' => '<p>Content</p>'
        ]);

        $this->feature->generateRssFeeds($this->container, []);

        $xml = file_get_contents($this->tempDir . '/tech/rss.xml');

        // Check RSS metadata
        $this->assertStringContainsString('xmlns:atom="http://www.w3.org/2005/Atom"', $xml);
        $this->assertStringContainsString('<language>en-us</language>', $xml);
        $this->assertStringContainsString('<lastBuildDate>', $xml);
        $this->assertStringContainsString('atom:link', $xml);
        $this->assertStringContainsString('rel="self"', $xml);
        $this->assertStringContainsString('type="application/rss+xml"', $xml);
    }

    public function testGeneratesValidRssXml(): void
    {
        $this->feature->collectCategoryFiles($this->container, [
            'metadata' => [
                'title' => 'Test Article',
                'category' => 'Tech',
                'description' => 'Test description'
            ],
            'output_path' => $this->tempDir . '/tech/test.html',
            'file_path' => $this->tempDir . '/content/test.md',
            'rendered_content' => '<p>Content</p>'
        ]);

        $this->feature->generateRssFeeds($this->container, []);

        $xml = file_get_contents($this->tempDir . '/tech/rss.xml');

        // Try to parse XML to ensure it's valid
        $doc = new \DOMDocument();
        $result = @$doc->loadXML($xml);

        $this->assertTrue($result, 'Generated RSS should be valid XML');
    }

    public function testGeneratesCorrectItemUrls(): void
    {
        $this->feature->collectCategoryFiles($this->container, [
            'metadata' => [
                'title' => 'Test Article',
                'category' => 'Tech'
            ],
            'output_path' => $this->tempDir . '/tech/article.html',
            'file_path' => $this->tempDir . '/content/article.md',
            'rendered_content' => '<p>Content</p>'
        ]);

        $this->feature->generateRssFeeds($this->container, []);

        $xml = file_get_contents($this->tempDir . '/tech/rss.xml');

        // Should contain full URL
        $this->assertStringContainsString('https://example.com/tech/article.html', $xml);
        // Should have both link and guid
        $this->assertStringContainsString('<link>https://example.com/tech/article.html</link>', $xml);
        $this->assertStringContainsString('<guid>https://example.com/tech/article.html</guid>', $xml);
    }
}
