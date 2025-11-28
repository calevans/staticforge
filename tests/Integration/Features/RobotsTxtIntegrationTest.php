<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration\Features;

use EICC\StaticForge\Tests\Integration\IntegrationTestCase;
use EICC\StaticForge\Core\Application;
use EICC\Utils\Container;

/**
 * Integration test for RobotsTxt feature
 * Tests the complete workflow of generating robots.txt
 */
class RobotsTxtIntegrationTest extends IntegrationTestCase
{
    private string $testOutputDir;
    private string $testContentDir;
    private string $testTemplateDir;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

      // Create temporary directories
        $this->testOutputDir = sys_get_temp_dir() . '/staticforge_robots_output_' . uniqid();
        $this->testContentDir = sys_get_temp_dir() . '/staticforge_robots_content_' . uniqid();
        $this->testTemplateDir = sys_get_temp_dir() . '/staticforge_robots_templates_' . uniqid();

        mkdir($this->testOutputDir, 0755, true);
        mkdir($this->testContentDir, 0755, true);
        mkdir($this->testTemplateDir . '/sample', 0755, true);

      // Override environment variables BEFORE loading bootstrap
        $_ENV['SOURCE_DIR'] = $this->testContentDir;
        $_ENV['OUTPUT_DIR'] = $this->testOutputDir;
        $_ENV['TEMPLATE_DIR'] = $this->testTemplateDir;
        $_ENV['SITE_BASE_URL'] = 'https://example.com';

      // Create container from integration env
        $this->container = $this->createContainer(__DIR__ . '/../../.env.integration');

      // Create base template
        $this->createBaseTemplate();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->testOutputDir);
        $this->removeDirectory($this->testContentDir);
        $this->removeDirectory($this->testTemplateDir);
    }

    private function createBaseTemplate(): void
    {
        $template = <<<'TWIG'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ title | default('Untitled') }}</title>
</head>
<body>
    <h1>{{ title | default('Untitled') }}</h1>
    <div class="content">
        {{ content | raw }}
    </div>
</body>
</html>
TWIG;

        file_put_contents(
            $this->testTemplateDir . '/sample/base.html.twig',
            $template
        );
    }

    public function testGeneratesRobotsTxtWithAllowedPages(): void
    {
      // Create a simple allowed page
        $content = <<<'MD'
---
title: "Public Page"
robots: "yes"
---
# Public Page

This is a public page.
MD;
        file_put_contents($this->testContentDir . '/public.md', $content);

      // Generate the site
        $app = new Application($this->container);
        $app->generate();

      // Check that robots.txt was generated
        $robotsTxtPath = $this->testOutputDir . '/robots.txt';
        $this->assertFileExists($robotsTxtPath);

        $robotsTxtContent = file_get_contents($robotsTxtPath);
        $this->assertStringContainsString('User-agent: *', $robotsTxtContent);
        $this->assertStringContainsString('Disallow:', $robotsTxtContent);
        $this->assertStringContainsString('Sitemap: https://example.com/sitemap.xml', $robotsTxtContent);

      // Should not contain any specific disallow paths for allowed pages
        $this->assertStringNotContainsString('Disallow: /public.html', $robotsTxtContent);
    }

    public function testGeneratesRobotsTxtWithDisallowedPages(): void
    {
      // Create allowed and disallowed pages
        $publicContent = <<<'MD'
---
title: "Public Page"
robots: "yes"
---
# Public Page
MD;
        file_put_contents($this->testContentDir . '/public.md', $publicContent);

        $privateContent = <<<'MD'
---
title: "Private Page"
robots: "no"
---
# Private Page
MD;
        file_put_contents($this->testContentDir . '/private.md', $privateContent);

        $secretContent = <<<'MD'
---
title: "Secret Page"
robots: "no"
---
# Secret Page
MD;
        file_put_contents($this->testContentDir . '/secret.md', $secretContent);

      // Generate the site
        $app = new Application($this->container);
        $app->generate();

      // Check robots.txt
        $robotsTxtPath = $this->testOutputDir . '/robots.txt';
        $this->assertFileExists($robotsTxtPath);

        $robotsTxtContent = file_get_contents($robotsTxtPath);

      // Should contain disallow for private pages
        $this->assertStringContainsString('Disallow: /private.html', $robotsTxtContent);
        $this->assertStringContainsString('Disallow: /secret.html', $robotsTxtContent);

      // Should not contain disallow for public page
        $this->assertStringNotContainsString('Disallow: /public.html', $robotsTxtContent);
    }

    public function testGeneratesRobotsTxtWithDisallowedCategory(): void
    {
      // Create a category definition with robots=no
        $categoryContent = <<<'MD'
---
type: "category"
category: "private-stuff"
title: "Private Category"
robots: "no"
---
# Private Category
MD;
        file_put_contents($this->testContentDir . '/private-category.md', $categoryContent);

      // Create a regular page
        $pageContent = <<<'MD'
---
title: "Regular Page"
---
# Regular Page
MD;
        file_put_contents($this->testContentDir . '/page.md', $pageContent);

      // Generate the site
        $app = new Application($this->container);
        $app->generate();

      // Check robots.txt
        $robotsTxtPath = $this->testOutputDir . '/robots.txt';
        $this->assertFileExists($robotsTxtPath);

        $robotsTxtContent = file_get_contents($robotsTxtPath);

      // Should contain disallow for category directory
        $this->assertStringContainsString('Disallow: /private-stuff/', $robotsTxtContent);

      // Should not contain disallow for regular page
        $this->assertStringNotContainsString('Disallow: /page.html', $robotsTxtContent);
    }

    public function testGeneratesRobotsTxtWithHtmlFiles(): void
    {
      // Create HTML file with robots=no
        $htmlContent = <<<'HTML'
<!--
---
title: "Private HTML Page"
robots: "no"
---
-->
<h1>Private HTML</h1>
<p>This should be disallowed.</p>
HTML;
        file_put_contents($this->testContentDir . '/private.html', $htmlContent);

      // Create HTML file with robots=yes
        $publicHtmlContent = <<<'HTML'
<!--
---
title: "Public HTML Page"
robots: "yes"
---
-->
<h1>Public HTML</h1>
<p>This should be allowed.</p>
HTML;
        file_put_contents($this->testContentDir . '/public.html', $publicHtmlContent);

      // Generate the site
        $app = new Application($this->container);
        $app->generate();

      // Check robots.txt
        $robotsTxtPath = $this->testOutputDir . '/robots.txt';
        $this->assertFileExists($robotsTxtPath);

        $robotsTxtContent = file_get_contents($robotsTxtPath);

      // Should contain disallow for private HTML
        $this->assertStringContainsString('Disallow: /private.html', $robotsTxtContent);

      // Should not contain disallow for public HTML
        $this->assertStringNotContainsString('Disallow: /public.html', $robotsTxtContent);
    }

    public function testRobotsTxtPathsAreSorted(): void
    {
      // Create multiple disallowed pages in non-alphabetical order
        $files = ['zebra', 'alpha', 'beta', 'charlie'];
        foreach ($files as $filename) {
            $content = <<<'MD'
---
title: "$filename"
robots: "no"
---
# $filename
MD;
            file_put_contents($this->testContentDir . "/{$filename}.md", $content);
        }

      // Generate the site
        $app = new Application($this->container);
        $app->generate();

      // Check robots.txt
        $robotsTxtPath = $this->testOutputDir . '/robots.txt';
        $robotsTxtContent = file_get_contents($robotsTxtPath);

      // Extract all Disallow lines
        preg_match_all('/Disallow: (.+)/', $robotsTxtContent, $matches);
        $disallowedPaths = $matches[1];

      // Verify they are sorted
        $sortedPaths = $disallowedPaths;
        sort($sortedPaths);

        $this->assertEquals($sortedPaths, $disallowedPaths);
    }
}
