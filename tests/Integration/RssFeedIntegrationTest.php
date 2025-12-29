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
        $_ENV['PUBLIC_DIR'] = $this->testOutputDir; // RSS feed uses PUBLIC_DIR
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
title: "Technology Article 1"
category: "Technology"
description: "First tech article"
date: "2024-01-01"
---
This is the content of the first technology article.
MD;

        $file2 = <<<'MD'
---
title: "Technology Article 2"
category: "Technology"
description: "Second tech article"
date: "2024-01-02"
author: "Jane Doe"
---
This is the content of the second technology article.
MD;

        file_put_contents($this->testContentDir . '/tech1.md', $file1);
        file_put_contents($this->testContentDir . '/tech2.md', $file2);

        // Run the application
        $container = $this->createContainer(__DIR__ . '/../.env.testing');

        // Ensure PUBLIC_DIR is set correctly in container
        if (!$container->hasVariable('PUBLIC_DIR')) {
            $container->setVariable('PUBLIC_DIR', $this->testOutputDir);
        } else {
            $container->updateVariable('PUBLIC_DIR', $this->testOutputDir);
        }

        $this->assertEquals($this->testOutputDir, $container->getVariable('PUBLIC_DIR'), 'PUBLIC_DIR should be set in container');

        // Override site_config to ensure SITE_NAME is used or matches
        if ($container->hasVariable('site_config')) {
            $container->updateVariable('site_config', ['site' => ['name' => 'Test Site']]);
        } else {
            $container->setVariable('site_config', ['site' => ['name' => 'Test Site']]);
        }

        $application = new Application($container);
        $application->generate();

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
        $this->assertStringContainsString('<title>Test Site - Technology</title>', $xml);
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
        // Create content for multiple categories
        $tech = <<<'MD'
---
title: "Tech Article"
category: "Technology"
date: "2024-01-01"
---
Tech content
MD;

        $life = <<<'MD'
---
title: "Life Article"
category: "Lifestyle"
date: "2024-01-01"
---
Lifestyle content
MD;

        file_put_contents($this->testContentDir . '/tech.md', $tech);
        file_put_contents($this->testContentDir . '/life.md', $life);

        // Run application
        $container = $this->createContainer(__DIR__ . '/../.env.testing');

        // Ensure PUBLIC_DIR is set correctly in container
        if (!$container->hasVariable('PUBLIC_DIR')) {
            $container->setVariable('PUBLIC_DIR', $this->testOutputDir);
        } else {
            $container->updateVariable('PUBLIC_DIR', $this->testOutputDir);
        }

        $this->assertEquals($this->testOutputDir, $container->getVariable('PUBLIC_DIR'), 'PUBLIC_DIR should be set in container');

        // Override site_config to ensure SITE_NAME is used or matches
        if ($container->hasVariable('site_config')) {
            $container->updateVariable('site_config', ['site' => ['name' => 'Test Site']]);
        } else {
            $container->setVariable('site_config', ['site' => ['name' => 'Test Site']]);
        }

        $application = new Application($container);
        $application->generate();

        // Check both feeds exist
        $this->assertFileExists($this->testOutputDir . '/technology/rss.xml');
        $this->assertFileExists($this->testOutputDir . '/lifestyle/rss.xml');
    }

    public function testRssFeedWithoutCategoriesNotGenerated(): void
    {
        // Content without category
        $content = <<<'MD'
---
title: "No Category"
date: "2024-01-01"
---
Content
MD;

        file_put_contents($this->testContentDir . '/page.md', $content);

        // Run application
        $container = $this->createContainer(__DIR__ . '/../.env.testing');

        // Ensure PUBLIC_DIR is set correctly in container
        if (!$container->hasVariable('PUBLIC_DIR')) {
            $container->setVariable('PUBLIC_DIR', $this->testOutputDir);
        } else {
            $container->updateVariable('PUBLIC_DIR', $this->testOutputDir);
        }

        $this->assertEquals($this->testOutputDir, $container->getVariable('PUBLIC_DIR'), 'PUBLIC_DIR should be set in container');

        // Override site_config to ensure SITE_NAME is used or matches
        if ($container->hasVariable('site_config')) {
            $container->updateVariable('site_config', ['site' => ['name' => 'Test Site']]);
        } else {
            $container->setVariable('site_config', ['site' => ['name' => 'Test Site']]);
        }

        $application = new Application($container);
        $application->generate();

        // Should not generate RSS feed
        $this->assertFileDoesNotExist($this->testOutputDir . '/rss.xml');

        // Check no subdirectories created
        $dirs = glob($this->testOutputDir . '/*', GLOB_ONLYDIR);
        // Filter out assets directory if it exists (created by TemplateAssets)
        $dirs = array_filter($dirs, fn($dir) => basename($dir) !== 'assets');
        $this->assertEmpty($dirs, 'Unexpected directories found: ' . implode(', ', $dirs));
    }

    public function testRssXmlIsValid(): void
    {
        // Create content
        $content = <<<'MD'
---
title: "Valid XML Test"
category: "Testing"
date: "2024-01-01"
description: "Testing XML validity"
---
Content with special chars: & < > " '
MD;

        file_put_contents($this->testContentDir . '/test.md', $content);

        // Run application
        $container = $this->createContainer(__DIR__ . '/../.env.testing');

        // Ensure PUBLIC_DIR is set correctly in container
        if (!$container->hasVariable('PUBLIC_DIR')) {
            $container->setVariable('PUBLIC_DIR', $this->testOutputDir);
        } else {
            $container->updateVariable('PUBLIC_DIR', $this->testOutputDir);
        }

        $this->assertEquals($this->testOutputDir, $container->getVariable('PUBLIC_DIR'), 'PUBLIC_DIR should be set in container');

        // Override site_config to ensure SITE_NAME is used or matches
        if ($container->hasVariable('site_config')) {
            $container->updateVariable('site_config', ['site' => ['name' => 'Test Site']]);
        } else {
            $container->setVariable('site_config', ['site' => ['name' => 'Test Site']]);
        }

        $application = new Application($container);
        $application->generate();

        $rssFile = $this->testOutputDir . '/testing/rss.xml';
        $this->assertFileExists($rssFile);

        // Validate XML
        $dom = new \DOMDocument();
        $dom->validateOnParse = true;
        $this->assertTrue($dom->loadXML(file_get_contents($rssFile)), 'RSS XML should be valid');
    }
}
