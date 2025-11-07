<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration;

use EICC\StaticForge\Core\Application;

/**
 * RSS Feed feature integration test
 * Tests that RSS feeds are generated correctly for categorized content
 */
class RssFeedIntegrationTest extends IntegrationTestCase
{
    private string $testOutputDir;
    private string $testContentDir;
    private string $testTemplateDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Use more entropy for parallel test execution
        $this->testOutputDir = sys_get_temp_dir() . '/staticforge_rss_output_' . uniqid('', true) . '_' . getmypid();
        $this->testContentDir = sys_get_temp_dir() . '/staticforge_rss_content_' . uniqid('', true) . '_' . getmypid();
        $this->testTemplateDir = sys_get_temp_dir() . '/staticforge_rss_templates_' . uniqid('', true) . '_' . getmypid();

        mkdir($this->testOutputDir, 0755, true);
        mkdir($this->testContentDir, 0755, true);
        mkdir($this->testTemplateDir . '/sample', 0755, true);

        // Override environment variables BEFORE loading bootstrap
        $_ENV['SOURCE_DIR'] = $this->testContentDir;
        $_ENV['OUTPUT_DIR'] = $this->testOutputDir;
        $_ENV['TEMPLATE_DIR'] = $this->testTemplateDir;
        $_ENV['SITE_NAME'] = 'RSS Test Site';
        $_ENV['SITE_BASE_URL'] = 'https://example.com/';

        $this->createSimpleTemplate();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->testOutputDir);
        $this->removeDirectory($this->testContentDir);
        $this->removeDirectory($this->testTemplateDir);
    }

    private function createSimpleTemplate(): void
    {
        $template = <<<'TWIG'
<!DOCTYPE html>
<html>
<head>
    <title>{{ title | default('Untitled') }}</title>
</head>
<body>
    <h1>{{ title }}</h1>
    <main>{{ content | raw }}</main>
</body>
</html>
TWIG;

        file_put_contents(
            $this->testTemplateDir . '/sample/base.html.twig',
            $template
        );
    }

    public function testGeneratesRssFeedForCategory(): void
    {
        // Create test content files with categories
        $file1 = <<<'MD'
---
title = "Technology Article 1"
category = "Technology"
description = "First tech article"
date = "2024-01-01"
---

This is the content of the first technology article.
MD;

        $file2 = <<<'MD'
---
title = "Technology Article 2"
category = "Technology"
description = "Second tech article"
date = "2024-01-02"
author = "Jane Doe"
---

This is the content of the second technology article.
MD;

        file_put_contents($this->testContentDir . '/tech1.md', $file1);
        file_put_contents($this->testContentDir . '/tech2.md', $file2);

        // Run the application
        $container = $this->createContainer(__DIR__ . '/../.env.testing');
        $application = new Application($container);
        $application->run();

        // Verify RSS file was created
        $rssPath = $this->testOutputDir . '/technology/rss.xml';
        $this->assertFileExists($rssPath, 'RSS feed should be generated in category directory');

        // Verify RSS content
        $xml = file_get_contents($rssPath);

        // Check basic RSS structure
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<rss version="2.0"', $xml);
        $this->assertStringContainsString('</rss>', $xml);

        // Check channel info
        $this->assertStringContainsString('<title>RSS Test Site - Technology</title>', $xml);
        $this->assertStringContainsString('<link>https://example.com/technology/</link>', $xml);
        $this->assertStringContainsString('xmlns:atom="http://www.w3.org/2005/Atom"', $xml);

        // Check articles are included
        $this->assertStringContainsString('Technology Article 1', $xml);
        $this->assertStringContainsString('Technology Article 2', $xml);

        // Check descriptions
        $this->assertStringContainsString('First tech article', $xml);
        $this->assertStringContainsString('Second tech article', $xml);

        // Check author
        $this->assertStringContainsString('<author>Jane Doe</author>', $xml);

        // Verify articles are sorted by date (newest first)
        $pos1 = strpos($xml, 'Technology Article 1');
        $pos2 = strpos($xml, 'Technology Article 2');
        $this->assertLessThan($pos1, $pos2, 'Article 2 (newer) should appear before Article 1');
    }

    public function testGeneratesMultipleCategoryRssFeeds(): void
    {
        // Create files in different categories
        $tech = <<<'MD'
---
title = "Tech Article"
category = "Technology"
date = "2024-01-01"
---
Tech content
MD;

        $blog = <<<'MD'
---
title = "Blog Post"
category = "Blog"
date = "2024-01-02"
---
Blog content
MD;

        file_put_contents($this->testContentDir . '/tech.md', $tech);
        file_put_contents($this->testContentDir . '/blog.md', $blog);

        // Run the application
        $container = $this->createContainer(__DIR__ . '/../.env.testing');
        $application = new Application($container);
        $application->run();

        // Verify both RSS files exist
        $this->assertFileExists($this->testOutputDir . '/technology/rss.xml');
        $this->assertFileExists($this->testOutputDir . '/blog/rss.xml');

        // Verify each RSS file contains only its own content
        $techXml = file_get_contents($this->testOutputDir . '/technology/rss.xml');
        $this->assertStringContainsString('Tech Article', $techXml);
        $this->assertStringNotContainsString('Blog Post', $techXml);

        $blogXml = file_get_contents($this->testOutputDir . '/blog/rss.xml');
        $this->assertStringContainsString('Blog Post', $blogXml);
        $this->assertStringNotContainsString('Tech Article', $blogXml);
    }

    public function testRssFeedWithoutCategoriesNotGenerated(): void
    {
        // Create content without categories
        $file = <<<'MD'
---
title = "Uncategorized Article"
---
Content without category
MD;

        file_put_contents($this->testContentDir . '/article.md', $file);

        // Run the application
        $container = $this->createContainer(__DIR__ . '/../.env.testing');
        $application = new Application($container);
        $application->run();

        // Verify no RSS files were created
        $rssFiles = glob($this->testOutputDir . '/*/rss.xml');
        $this->assertEmpty($rssFiles, 'No RSS feeds should be generated for uncategorized content');
    }

    public function testRssXmlIsValid(): void
    {
        // Create test content
        $file = <<<'MD'
---
title = "Test Article & More <Tags>"
category = "Technology"
description = "Description with special chars: & < > \" '"
---
Content with special characters
MD;

        file_put_contents($this->testContentDir . '/test.md', $file);

        // Run the application
        $container = $this->createContainer(__DIR__ . '/../.env.testing');
        $application = new Application($container);
        $application->run();

        // Load and validate XML
        $xml = file_get_contents($this->testOutputDir . '/technology/rss.xml');
        
        $doc = new \DOMDocument();
        $result = @$doc->loadXML($xml);

        $this->assertTrue($result, 'Generated RSS should be valid XML');

        // Verify special characters are properly escaped
        $this->assertStringContainsString('&amp;', $xml);
        $this->assertStringContainsString('&lt;', $xml);
        $this->assertStringContainsString('&gt;', $xml);
    }
}
