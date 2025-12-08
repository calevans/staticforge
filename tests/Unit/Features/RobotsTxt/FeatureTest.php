<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\RobotsTxt;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Features\RobotsTxt\Feature as RobotsTxtFeature;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;
use org\bovigo\vfs\vfsStream;

class RobotsTxtFeatureTest extends UnitTestCase
{
    private RobotsTxtFeature $feature;
    private EventManager $eventManager;
    private $root;

    protected function setUp(): void
    {
        parent::setUp();

      // Set up virtual filesystem
        $this->root = vfsStream::setup('test');

      // Use bootstrapped container from parent::setUp()
        $this->eventManager = new EventManager($this->container);

        $this->feature = new RobotsTxtFeature();
        $this->feature->register($this->eventManager, $this->container);
    }

    public function testRegisterFeature(): void
    {
        $listeners = $this->eventManager->getListeners('POST_GLOB');
        $this->assertNotEmpty($listeners);

        $listeners = $this->eventManager->getListeners('POST_LOOP');
        $this->assertNotEmpty($listeners);
    }

    public function testHandlePostGlobWithNoFiles(): void
    {
        $this->setContainerVariable('discovered_files', []);
        $this->setContainerVariable('SOURCE_DIR', 'content');

        $parameters = [];
        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $this->assertIsArray($result);
    }

    public function testScanMarkdownFileWithRobotsNo(): void
    {
      // Create a markdown file with robots=no
        $content = "---\ntitle=\"Test Page\"\nrobots=\"no\"\n---\n\n# Test Content";
        $filePath = vfsStream::url('test/content/test.md');

      // Create directory
        vfsStream::create(['content' => []], $this->root);
        file_put_contents($filePath, $content);

        $this->setContainerVariable('discovered_files', [
        ['path' => $filePath, 'url' => 'test.md', 'metadata' => ['robots' => 'no']]
        ]);
        $this->setContainerVariable('SOURCE_DIR', vfsStream::url('test/content'));

        $parameters = [];
        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $this->assertIsArray($result);
    }

    public function testScanMarkdownFileWithRobotsYes(): void
    {
      // Create a markdown file with robots=yes
        $content = "---\ntitle=\"Test Page\"\nrobots=\"yes\"\n---\n\n# Test Content";
        $filePath = vfsStream::url('test/content/allowed.md');

      // Create directory
        vfsStream::create(['content' => []], $this->root);
        file_put_contents($filePath, $content);

        $this->setContainerVariable('discovered_files', [
        ['path' => $filePath, 'url' => 'allowed.md', 'metadata' => ['robots' => 'yes']]
        ]);
        $this->setContainerVariable('SOURCE_DIR', vfsStream::url('test/content'));

        $parameters = [];
        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $this->assertIsArray($result);
    }

    public function testScanMarkdownFileWithoutRobotsField(): void
    {
      // Create a markdown file without robots field (should default to yes)
        $content = "---\ntitle=\"Test Page\"\n---\n\n# Test Content";
        $filePath = vfsStream::url('test/content/default.md');

      // Create directory
        vfsStream::create(['content' => []], $this->root);
        file_put_contents($filePath, $content);

        $this->setContainerVariable('discovered_files', [
        ['path' => $filePath, 'url' => 'default.md', 'metadata' => []]
        ]);
        $this->setContainerVariable('SOURCE_DIR', vfsStream::url('test/content'));

        $parameters = [];
        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $this->assertIsArray($result);
    }

    public function testScanHtmlFileWithRobotsNo(): void
    {
      // Create an HTML file with robots=no
        $content = "<!-- INI\ntitle=\"Test Page\"\nrobots=\"no\"\n-->\n<h1>Test Content</h1>";
        $filePath = vfsStream::url('test/content/test.html');

      // Create directory
        vfsStream::create(['content' => []], $this->root);
        file_put_contents($filePath, $content);

        $this->setContainerVariable('discovered_files', [
        ['path' => $filePath, 'url' => 'test.html', 'metadata' => ['robots' => 'no']]
        ]);
        $this->setContainerVariable('SOURCE_DIR', vfsStream::url('test/content'));

        $parameters = [];
        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $this->assertIsArray($result);
    }

    public function testScanCategoryFileWithRobotsNo(): void
    {
      // Create a category file with robots=no
        $content = "---\ntitle=\"Private Category\"\ntype=\"category\"\ncategory=\"private\"\nrobots=\"no\"\n---\n\n# Private Category";
        $filePath = vfsStream::url('test/content/private.md');

      // Create directory
        vfsStream::create(['content' => []], $this->root);
        file_put_contents($filePath, $content);

        $this->setContainerVariable('discovered_files', [
        ['path' => $filePath, 'url' => 'private.html', 'metadata' => ['type' => 'category', 'category' => 'private', 'robots' => 'no']]
        ]);
        $this->setContainerVariable('SOURCE_DIR', vfsStream::url('test/content'));

        $parameters = [];
        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $this->assertIsArray($result);
    }

    public function testGenerateRobotsTxtWithNoDisallowedPaths(): void
    {
        $outputDir = vfsStream::url('test/output');
        vfsStream::create(['output' => []], $this->root);

        $this->setContainerVariable('OUTPUT_DIR', $outputDir);
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');
      // Must have at least one file to trigger generation
        $this->setContainerVariable('discovered_files', [
        ['path' => '/tmp/dummy.md', 'url' => 'dummy.html', 'metadata' => []]
        ]);
        $this->setContainerVariable('SOURCE_DIR', vfsStream::url('test/content'));

      // Run POST_GLOB to scan files (none in this case)
        $parameters = [];
        $this->feature->handlePostGlob($this->container, $parameters);

      // Generate robots.txt
        $this->feature->handlePostLoop($this->container, $parameters);

        $robotsTxtPath = $outputDir . '/robots.txt';
        $this->assertTrue(file_exists($robotsTxtPath));

        $content = file_get_contents($robotsTxtPath);
        $this->assertStringContainsString('User-agent: *', $content);
        $this->assertStringContainsString('Disallow:', $content);
        $this->assertStringContainsString('Sitemap: https://example.com/sitemap.xml', $content);
    }

    public function testGenerateRobotsTxtWithDisallowedPaths(): void
    {
        $outputDir = vfsStream::url('test/output');
        $contentDir = vfsStream::url('test/content');
        vfsStream::create(['output' => [], 'content' => []], $this->root);

      // Create multiple files with robots=no
        $file1 = $contentDir . '/private.md';
        $file2 = $contentDir . '/secret.md';
        file_put_contents($file1, "---\nrobots=\"no\"\n---\n# Private");
        file_put_contents($file2, "---\nrobots=\"no\"\n---\n# Secret");

        $this->setContainerVariable('OUTPUT_DIR', $outputDir);
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');
        $this->setContainerVariable('discovered_files', [
        ['path' => $file1, 'url' => 'private.html', 'metadata' => ['robots' => 'no']],
        ['path' => $file2, 'url' => 'secret.html', 'metadata' => ['robots' => 'no']]
        ]);
        $this->setContainerVariable('SOURCE_DIR', $contentDir);

      // Run POST_GLOB to scan files
        $parameters = [];
        $this->feature->handlePostGlob($this->container, $parameters);

      // Generate robots.txt
        $this->feature->handlePostLoop($this->container, $parameters);

        $robotsTxtPath = $outputDir . '/robots.txt';
        $this->assertTrue(file_exists($robotsTxtPath));

        $content = file_get_contents($robotsTxtPath);
        $this->assertStringContainsString('User-agent: *', $content);
        $this->assertStringContainsString('Disallow: /private.html', $content);
        $this->assertStringContainsString('Disallow: /secret.html', $content);
    }

    public function testGenerateRobotsTxtWithCategoryDisallowed(): void
    {
        $outputDir = vfsStream::url('test/output');
        $contentDir = vfsStream::url('test/content');
        vfsStream::create(['output' => [], 'content' => []], $this->root);

      // Create a category file with robots=no
        $categoryFile = $contentDir . '/private-cat.md';
        file_put_contents(
            $categoryFile,
            "---\ntype=\"category\"\ncategory=\"private-stuff\"\nrobots=\"no\"\n---\n# Private Category"
        );

        $this->setContainerVariable('OUTPUT_DIR', $outputDir);
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');
        $this->setContainerVariable('discovered_files', [
        ['path' => $categoryFile, 'url' => 'private-cat.html', 'metadata' => ['type' => 'category', 'category' => 'private-stuff', 'robots' => 'no']]
        ]);
        $this->setContainerVariable('SOURCE_DIR', $contentDir);

      // Run POST_GLOB to scan files
        $parameters = [];
        $this->feature->handlePostGlob($this->container, $parameters);

      // Generate robots.txt
        $this->feature->handlePostLoop($this->container, $parameters);

        $robotsTxtPath = $outputDir . '/robots.txt';
        $this->assertTrue(file_exists($robotsTxtPath));

        $content = file_get_contents($robotsTxtPath);
        $this->assertStringContainsString('Disallow: /private-stuff/', $content);
    }

    public function testPathsAreSortedAndUnique(): void
    {
        $outputDir = vfsStream::url('test/output');
        $contentDir = vfsStream::url('test/content');
        vfsStream::create(['output' => [], 'content' => []], $this->root);

      // Create files that would create duplicate paths (shouldn't happen, but test it)
        $file1 = $contentDir . '/test.md';
        $file2 = $contentDir . '/another.md';
        $file3 = $contentDir . '/zebra.md';

        file_put_contents($file1, "---\nrobots=\"no\"\n---\n# Test");
        file_put_contents($file2, "---\nrobots=\"no\"\n---\n# Another");
        file_put_contents($file3, "---\nrobots=\"no\"\n---\n# Zebra");

        $this->setContainerVariable('OUTPUT_DIR', $outputDir);
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');
        $this->setContainerVariable('discovered_files', [
        ['path' => $file1, 'url' => 'test.html', 'metadata' => ['robots' => 'no']],
        ['path' => $file2, 'url' => 'another.html', 'metadata' => ['robots' => 'no']],
        ['path' => $file3, 'url' => 'zebra.html', 'metadata' => ['robots' => 'no']]
        ]);
        $this->setContainerVariable('SOURCE_DIR', $contentDir);

      // Run POST_GLOB to scan files
        $parameters = [];
        $this->feature->handlePostGlob($this->container, $parameters);

      // Generate robots.txt
        $this->feature->handlePostLoop($this->container, $parameters);

        $robotsTxtPath = $outputDir . '/robots.txt';
        $content = file_get_contents($robotsTxtPath);

      // Verify paths are in sorted order
        $this->assertMatchesRegularExpression('/Disallow: \/another\.html.*Disallow: \/test\.html.*Disallow: \/zebra\.html/s', $content);
    }

    public function testRobotsTxtWithoutSiteBaseUrl(): void
    {
        $outputDir = vfsStream::url('test/output');
        vfsStream::create(['output' => []], $this->root);

        $this->setContainerVariable('OUTPUT_DIR', $outputDir);
        $this->setContainerVariable('SITE_BASE_URL', ''); // Empty base URL
      // Must have at least one file to trigger generation
        $this->setContainerVariable('discovered_files', [
        ['path' => '/tmp/dummy.md', 'url' => 'dummy.html', 'metadata' => []]
        ]);
        $this->setContainerVariable('SOURCE_DIR', vfsStream::url('test/content'));

      // Run POST_GLOB
        $parameters = [];
        $this->feature->handlePostGlob($this->container, $parameters);

      // Generate robots.txt
        $this->feature->handlePostLoop($this->container, $parameters);

        $robotsTxtPath = $outputDir . '/robots.txt';
        $content = file_get_contents($robotsTxtPath);

      // Should not contain Sitemap line when base URL is empty
        $this->assertStringNotContainsString('Sitemap:', $content);
    }

    public function testMixedRobotsValues(): void
    {
        $outputDir = vfsStream::url('test/output');
        $contentDir = vfsStream::url('test/content');
        vfsStream::create(['output' => [], 'content' => []], $this->root);

      // Create files with different robots values
        $file1 = $contentDir . '/allowed.md';
        $file2 = $contentDir . '/disallowed.md';
        $file3 = $contentDir . '/default.md';

        file_put_contents($file1, "---\nrobots=\"yes\"\n---\n# Allowed");
        file_put_contents($file2, "---\nrobots=\"no\"\n---\n# Disallowed");
        file_put_contents($file3, "---\ntitle=\"Default\"\n---\n# Default (no robots field)");

        $this->setContainerVariable('OUTPUT_DIR', $outputDir);
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');
        $this->setContainerVariable('discovered_files', [
        ['path' => $file1, 'url' => 'allowed.html', 'metadata' => ['robots' => 'yes']],
        ['path' => $file2, 'url' => 'disallowed.html', 'metadata' => ['robots' => 'no']],
        ['path' => $file3, 'url' => 'default.html', 'metadata' => []]
        ]);
        $this->setContainerVariable('SOURCE_DIR', $contentDir);

      // Run POST_GLOB
        $parameters = [];
        $this->feature->handlePostGlob($this->container, $parameters);

      // Generate robots.txt
        $this->feature->handlePostLoop($this->container, $parameters);

        $robotsTxtPath = $outputDir . '/robots.txt';
        $content = file_get_contents($robotsTxtPath);

      // Only disallowed.html should be in robots.txt
        $this->assertStringContainsString('Disallow: /disallowed.html', $content);
        $this->assertStringNotContainsString('/allowed.html', $content);
        $this->assertStringNotContainsString('/default.html', $content);
    }

    public function testCaseInsensitiveRobotsValue(): void
    {
        $outputDir = vfsStream::url('test/output');
        $contentDir = vfsStream::url('test/content');
        vfsStream::create(['output' => [], 'content' => []], $this->root);

      // Test various case combinations
        $file1 = $contentDir . '/uppercase.md';
        $file2 = $contentDir . '/mixedcase.md';

        file_put_contents($file1, "---\nrobots=\"NO\"\n---\n# Upper");
        file_put_contents($file2, "---\nrobots=\"No\"\n---\n# Mixed");

        $this->setContainerVariable('OUTPUT_DIR', $outputDir);
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');
        $this->setContainerVariable('discovered_files', [
        ['path' => $file1, 'url' => 'uppercase.html', 'metadata' => ['robots' => 'NO']],
        ['path' => $file2, 'url' => 'mixedcase.html', 'metadata' => ['robots' => 'No']]
        ]);
        $this->setContainerVariable('SOURCE_DIR', $contentDir);

      // Run POST_GLOB
        $parameters = [];
        $this->feature->handlePostGlob($this->container, $parameters);

      // Generate robots.txt
        $this->feature->handlePostLoop($this->container, $parameters);

        $robotsTxtPath = $outputDir . '/robots.txt';
        $content = file_get_contents($robotsTxtPath);

      // Both should be disallowed
        $this->assertStringContainsString('Disallow: /uppercase.html', $content);
        $this->assertStringContainsString('Disallow: /mixedcase.html', $content);
    }

    public function testNestedContentPaths(): void
    {
        $outputDir = vfsStream::url('test/output');
        $contentDir = vfsStream::url('test/content');
        vfsStream::create([
        'output' => [],
        'content' => [
        'subdir' => []
        ]
        ], $this->root);

      // Create a file in a subdirectory
        $file = $contentDir . '/subdir/nested.md';
        file_put_contents($file, "---\nrobots=\"no\"\n---\n# Nested");

        $this->setContainerVariable('OUTPUT_DIR', $outputDir);
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');
        $this->setContainerVariable('discovered_files', [
        ['path' => $file, 'url' => 'subdir/nested.html', 'metadata' => ['robots' => 'no']]
        ]);
        $this->setContainerVariable('SOURCE_DIR', $contentDir);

      // Run POST_GLOB
        $parameters = [];
        $this->feature->handlePostGlob($this->container, $parameters);

      // Generate robots.txt
        $this->feature->handlePostLoop($this->container, $parameters);

        $robotsTxtPath = $outputDir . '/robots.txt';
        $content = file_get_contents($robotsTxtPath);

      // Should preserve subdirectory in path
        $this->assertStringContainsString('Disallow: /subdir/nested.html', $content);
    }
}
