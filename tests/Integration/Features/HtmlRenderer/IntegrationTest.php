<?php

namespace EICC\StaticForge\Tests\Integration\Features\HtmlRenderer;

use EICC\StaticForge\Core\Application;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

/**
 * Integration test for HTML Renderer Feature
 * Tests end-to-end HTML processing through the Application
 *
 * @covers \EICC\StaticForge\Features\HtmlRenderer\Feature
 * @backupGlobals enabled
 */
class IntegrationTest extends TestCase
{
    private static int $testCounter = 0;
    private vfsStreamDirectory $root;
    private string $sourceDir;
    private string $outputDir;
    private string $envFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up virtual filesystem with unique name per test
        self::$testCounter++;
        $this->root = vfsStream::setup("integration_" . self::$testCounter);
        $this->sourceDir = $this->root->url() . '/source';
        $this->outputDir = $this->root->url() . '/output';
        $this->envFile = $this->root->url() . '/.env';

        mkdir($this->sourceDir);
        mkdir($this->outputDir);
        mkdir($this->root->url() . '/templates');
        mkdir($this->root->url() . '/features');

        // Create test environment file
        $envContent = <<<ENV
SOURCE_DIR="{$this->sourceDir}"
OUTPUT_DIR="{$this->outputDir}"
SITE_NAME="Integration Test Site"
SITE_BASE_URL="https://integration.test/"
TEMPLATE_DIR="{$this->root->url()}/templates"
FEATURES_DIR="/app/src/Features"
ENV;
        file_put_contents($this->envFile, $envContent);
    }

    protected function tearDown(): void
    {
        // Clean up environment variables to prevent test contamination
        $envVars = [
            'SOURCE_DIR',
            'OUTPUT_DIR',
            'SITE_NAME',
            'SITE_BASE_URL',
            'TEMPLATE_DIR',
            'FEATURES_DIR'
        ];

        foreach ($envVars as $var) {
            unset($_ENV[$var]);
        }

        parent::tearDown();
    }

    public function testEndToEndHtmlProcessing(): void
    {
        // Create multiple HTML files to test
        $this->createTestHtmlFiles();

        // Debug: Verify source files were created
        $this->assertFileExists($this->sourceDir . '/index.html');
        $this->assertFileExists($this->sourceDir . '/about.html');
        $this->assertFileExists($this->sourceDir . '/blog/first-post.html');

        // Run application
        $application = new Application($this->envFile);
        $result = $application->generate();

        // Verify generation succeeded
        $this->assertTrue($result, 'Application generation should succeed');

        // Verify output files were created
        $this->assertFileExists($this->outputDir . '/index.html');
        $this->assertFileExists($this->outputDir . '/about.html');
        $this->assertFileExists($this->outputDir . '/blog/first-post.html');

        // Verify content processing
        $indexContent = file_get_contents($this->outputDir . '/index.html');
        $this->assertStringContainsString('<title>Home Page | Integration Test Site</title>', $indexContent);
        $this->assertStringContainsString('<h1>Welcome to Our Site</h1>', $indexContent);
        $this->assertStringContainsString('<meta name="description" content="Welcome to our amazing website">', $indexContent);

        $aboutContent = file_get_contents($this->outputDir . '/about.html');
        $this->assertStringContainsString('<title>Untitled Page | Integration Test Site</title>', $aboutContent);
        $this->assertStringNotContainsString('---', $aboutContent); // YAML removed

        $blogContent = file_get_contents($this->outputDir . '/blog/first-post.html');
        $this->assertStringContainsString('<title>My First Blog Post | Integration Test Site</title>', $blogContent);
        $this->assertStringContainsString('<meta name="keywords" content="blog, first-post, welcome">', $blogContent);
    }

    public function testMixedFileTypesProcessing(): void
    {
        // Create HTML and non-HTML files
        $htmlContent = <<<HTML
---
title: HTML File
---
<h1>This is HTML</h1>
HTML;
        file_put_contents($this->sourceDir . '/page.html', $htmlContent);
        file_put_contents($this->sourceDir . '/data.txt', 'This is text data');
        file_put_contents($this->sourceDir . '/style.css', 'body { color: blue; }');

        // Run application
        $application = new Application($this->envFile);
        $application->generate();

        // Only HTML files should be processed by HTML renderer
        $this->assertFileExists($this->outputDir . '/page.html');

        // Verify HTML was processed (has template)
        $htmlOutput = file_get_contents($this->outputDir . '/page.html');
        $this->assertStringContainsString('<!DOCTYPE html>', $htmlOutput);
        $this->assertStringContainsString('<title>HTML File | Integration Test Site</title>', $htmlOutput);

        // Other files should not exist (no other processors registered)
        $this->assertFileDoesNotExist($this->outputDir . '/data.txt');
        $this->assertFileDoesNotExist($this->outputDir . '/style.css');
    }

    public function testEmptySourceDirectory(): void
    {
        // Empty source directory
        $application = new Application($this->envFile);
        $application->generate();

        // Output directory should exist but be empty (except for any dot files)
        $this->assertTrue(is_dir($this->outputDir));
        $files = glob($this->outputDir . '/*');
        $this->assertEmpty($files);
    }

    private function createTestHtmlFiles(): void
    {
        // Home page with metadata
        $indexHtml = <<<HTML
---
title: Home Page
description: Welcome to our amazing website
menu_position: 1
category: main
tags:
  - home
  - welcome
---
<h1>Welcome to Our Site</h1>
<p>This is the home page of our static site.</p>
<nav>
  <ul>
    <li><a href="about.html">About</a></li>
    <li><a href="blog/">Blog</a></li>
  </ul>
</nav>
HTML;
        file_put_contents($this->sourceDir . '/index.html', $indexHtml);

        // About page without metadata
        $aboutHtml = <<<HTML
<h1>About Us</h1>
<p>We are a company that builds static websites.</p>
<p>Our mission is to make web development simple.</p>
HTML;
        file_put_contents($this->sourceDir . '/about.html', $aboutHtml);

        // Blog post in subdirectory
        mkdir($this->sourceDir . '/blog');
        $blogHtml = <<<HTML
---
title: My First Blog Post
description: Welcome to our blog
category: blog
tags:
  - blog
  - first-post
  - welcome
date: 2025-01-01
author: Test Author
---
<article>
  <h1>My First Blog Post</h1>
  <p class="meta">Published on January 1, 2025 by Test Author</p>
  <p>Welcome to our blog! This is our very first post.</p>
  <p>We'll be sharing updates and insights here regularly.</p>
</article>
HTML;
        file_put_contents($this->sourceDir . '/blog/first-post.html', $blogHtml);
    }
}