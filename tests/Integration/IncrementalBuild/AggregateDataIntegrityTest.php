<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration\IncrementalBuild;

use EICC\StaticForge\Tests\Integration\IntegrationTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use EICC\StaticForge\Features\SiteBuilder\Commands\RenderSiteCommand;

/**
 * Proves the core safety invariant of incremental builds: when one content file is
 * modified and `--incremental` causes the other unchanged files to skip RENDER, every
 * aggregate feature (Sitemap, RssFeed, CategoryIndex, Tags, Search) still includes the
 * unchanged files in its output, because POST_RENDER always fires for every file with
 * a complete payload, cache hit or not.
 */
class AggregateDataIntegrityTest extends IntegrationTestCase
{
    private string $testOutputDir;
    private string $testContentDir;
    private string $testTemplateDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testOutputDir = sys_get_temp_dir() . '/staticforge_incagg_output_' . uniqid();
        $this->testContentDir = sys_get_temp_dir() . '/staticforge_incagg_content_' . uniqid();
        $this->testTemplateDir = sys_get_temp_dir() . '/staticforge_incagg_templates_' . uniqid();

        mkdir($this->testOutputDir, 0755, true);
        mkdir($this->testContentDir, 0755, true);
        mkdir($this->testTemplateDir . '/sample', 0755, true);

        $_ENV['SOURCE_DIR'] = $this->testContentDir;
        $_ENV['OUTPUT_DIR'] = $this->testOutputDir;
        $_ENV['TEMPLATE_DIR'] = $this->testTemplateDir;

        $baseTemplate = '<!DOCTYPE html>
<html>
<head><title>{{ title | default("Test Site") }}</title></head>
<body>
    <h1>{{ title | default("Test Site") }}</h1>
    <main>{{ content | raw }}</main>
</body>
</html>';
        file_put_contents($this->testTemplateDir . '/sample/base.html.twig', $baseTemplate);

        $categoryIndexTemplate = '<!DOCTYPE html>
<html>
<head><title>{{ title | default("Category") }}</title></head>
<body>
    <h1>{{ title | default("Category") }}</h1>
    {% for file in category_files %}
    <article><h2><a href="{{ file.url }}">{{ file.title }}</a></h2></article>
    {% endfor %}
</body>
</html>';
        file_put_contents($this->testTemplateDir . '/sample/category-index.html.twig', $categoryIndexTemplate);

        $tagIndexTemplate = '<!DOCTYPE html>
<html>
<head><title>{{ title | default("Tag") }}</title></head>
<body>
    <h1>{{ title | default("Tag") }}</h1>
    {% for file in tag_files %}
    <article><h2><a href="{{ file.url }}">{{ file.title }}</a></h2></article>
    {% endfor %}
</body>
</html>';
        file_put_contents($this->testTemplateDir . '/sample/tag-index.html.twig', $tagIndexTemplate);

        // The category-definer file must be Markdown, not HTML: HtmlRendererService
        // unconditionally recalculates output_path and ignores the output_path
        // CategoryPageService passes in, which breaks the category-index page's URL.
        // MarkdownRendererService correctly respects a pre-set output_path.
        $categoryDefiner = <<<MD
---
title: News
type: category
template: category-index
---

MD;
        file_put_contents($this->testContentDir . '/news.md', $categoryDefiner);

        $this->writeContentFile('a.html', 'A Article', 'Original content for article A.');
        $this->writeContentFile('b.html', 'B Article', 'Content for article B.');
        $this->writeContentFile('c.html', 'C Article', 'Content for article C.');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testOutputDir);
        $this->removeDirectory($this->testContentDir);
        $this->removeDirectory($this->testTemplateDir);

        parent::tearDown();
    }

    private function writeContentFile(string $filename, string $title, string $body): void
    {
        $content = <<<HTML
<!--
---
title: "{$title}"
category: "news"
tags: ["incremental", "news"]
---
-->
<h2>{$title}</h2>
<p>{$body}</p>
HTML;
        file_put_contents($this->testContentDir . '/' . $filename, $content);
    }

    /**
     * Builds a fresh container per invocation, mirroring how `--incremental` is actually
     * used in practice: as a separate `site:render` CLI process per build. Reusing one
     * container/process for multiple builds is not a scenario this codebase supports today
     * (every feature's in-memory aggregate state, e.g. SitemapService::$urls, is built once
     * per process and never reset), and the safety invariant this test proves is about the
     * on-disk cache (mtime comparisons against files in OUTPUT_DIR), which persists across
     * process boundaries exactly as it would for real consecutive CLI invocations.
     */
    private function runRenderCommand(array $options = []): int
    {
        $container = $this->createContainer(__DIR__ . '/../../.env.testing');
        $container->updateVariable('SOURCE_DIR', $this->testContentDir);
        $container->updateVariable('OUTPUT_DIR', $this->testOutputDir);
        $container->updateVariable('TEMPLATE_DIR', $this->testTemplateDir);

        $application = new Application();
        $application->add(new RenderSiteCommand($container));

        $command = $application->find('site:render');
        $commandTester = new CommandTester($command);

        return $commandTester->execute(array_merge(['command' => $command->getName()], $options));
    }

    public function testUnchangedFilesSurviveIncrementalRebuildInAllAggregates(): void
    {
        // Step 1: full build, no --incremental.
        $result = $this->runRenderCommand();
        $this->assertEquals(0, $result);

        $sitemapAfterFullBuild = file_get_contents($this->testOutputDir . '/sitemap.xml');
        $this->assertNotFalse($sitemapAfterFullBuild);
        // 4 URLs: a, b, c articles plus the news category index page itself.
        $this->assertSame(4, substr_count($sitemapAfterFullBuild, '<url>'));

        $categoryIndexAfterFullBuild = file_get_contents($this->testOutputDir . '/news/index.html');
        $this->assertNotFalse($categoryIndexAfterFullBuild);
        $this->assertStringContainsString('A Article', $categoryIndexAfterFullBuild);
        $this->assertStringContainsString('B Article', $categoryIndexAfterFullBuild);
        $this->assertStringContainsString('C Article', $categoryIndexAfterFullBuild);

        $tagIndexAfterFullBuild = file_get_contents($this->testOutputDir . '/tags/incremental/index.html');
        $this->assertNotFalse($tagIndexAfterFullBuild);
        $this->assertStringContainsString('A Article', $tagIndexAfterFullBuild);
        $this->assertStringContainsString('B Article', $tagIndexAfterFullBuild);
        $this->assertStringContainsString('C Article', $tagIndexAfterFullBuild);

        $searchJsonAfterFullBuild = file_get_contents($this->testOutputDir . '/search.json');
        $this->assertNotFalse($searchJsonAfterFullBuild);
        $this->assertStringContainsString('A Article', $searchJsonAfterFullBuild);
        $this->assertStringContainsString('B Article', $searchJsonAfterFullBuild);
        $this->assertStringContainsString('C Article', $searchJsonAfterFullBuild);

        $rssAfterFullBuild = file_get_contents($this->testOutputDir . '/news/rss.xml');
        $this->assertNotFalse($rssAfterFullBuild);
        $this->assertStringContainsString('A Article', $rssAfterFullBuild);
        $this->assertStringContainsString('B Article', $rssAfterFullBuild);
        $this->assertStringContainsString('C Article', $rssAfterFullBuild);

        // Articles are moved under /news/ by the Categories feature (post-render output
        // path rewrite based on the `category` frontmatter field).
        $bOutputBefore = file_get_contents($this->testOutputDir . '/news/b.html');
        $cOutputBefore = file_get_contents($this->testOutputDir . '/news/c.html');
        $bMtimeBefore = filemtime($this->testOutputDir . '/news/b.html');
        $cMtimeBefore = filemtime($this->testOutputDir . '/news/c.html');

        // Step 2: modify only A, ensure its mtime moves forward so the cache check sees it
        // as newer than its existing output file. Leave B and C untouched.
        sleep(1);
        $this->writeContentFile('a.html', 'A Article Updated', 'Updated content for article A.');
        touch($this->testContentDir . '/a.html');

        // Step 3: run again with --incremental.
        $result = $this->runRenderCommand(['--incremental' => true]);
        $this->assertEquals(0, $result);

        // A's HTML reflects the new content.
        $aOutputAfter = file_get_contents($this->testOutputDir . '/news/a.html');
        $this->assertNotFalse($aOutputAfter);
        $this->assertStringContainsString('A Article Updated', $aOutputAfter);

        // B and C's HTML output is byte-identical and their mtimes are unchanged,
        // proving their RENDER was actually skipped, not just coincidentally identical.
        $this->assertSame($bOutputBefore, file_get_contents($this->testOutputDir . '/news/b.html'));
        $this->assertSame($cOutputBefore, file_get_contents($this->testOutputDir . '/news/c.html'));
        $this->assertSame($bMtimeBefore, filemtime($this->testOutputDir . '/news/b.html'));
        $this->assertSame($cMtimeBefore, filemtime($this->testOutputDir . '/news/c.html'));

        // Sitemap still contains exactly 4 URLs (a, b, c, news index), including B and C.
        $sitemapAfterIncremental = file_get_contents($this->testOutputDir . '/sitemap.xml');
        $this->assertNotFalse($sitemapAfterIncremental);
        $this->assertSame(4, substr_count($sitemapAfterIncremental, '<url>'));
        $this->assertStringContainsString('<loc>https://test.example.com/news/b.html</loc>', $sitemapAfterIncremental);
        $this->assertStringContainsString('<loc>https://test.example.com/news/c.html</loc>', $sitemapAfterIncremental);

        // News category index still lists all 3 files, including B and C.
        $categoryIndexAfterIncremental = file_get_contents($this->testOutputDir . '/news/index.html');
        $this->assertNotFalse($categoryIndexAfterIncremental);
        $this->assertStringContainsString('A Article Updated', $categoryIndexAfterIncremental);
        $this->assertStringContainsString('B Article', $categoryIndexAfterIncremental);
        $this->assertStringContainsString('C Article', $categoryIndexAfterIncremental);

        // Tag archive page still lists B and C (regression guard - Tags is POST_GLOB-driven).
        $tagIndexAfterIncremental = file_get_contents($this->testOutputDir . '/tags/incremental/index.html');
        $this->assertNotFalse($tagIndexAfterIncremental);
        $this->assertStringContainsString('B Article', $tagIndexAfterIncremental);
        $this->assertStringContainsString('C Article', $tagIndexAfterIncremental);

        // search.json still contains documents for B and C, not just A.
        $searchJsonAfterIncremental = file_get_contents($this->testOutputDir . '/search.json');
        $this->assertNotFalse($searchJsonAfterIncremental);
        $this->assertStringContainsString('B Article', $searchJsonAfterIncremental);
        $this->assertStringContainsString('C Article', $searchJsonAfterIncremental);

        // RSS feed for the news category still contains items for all 3 files.
        $rssAfterIncremental = file_get_contents($this->testOutputDir . '/news/rss.xml');
        $this->assertNotFalse($rssAfterIncremental);
        $this->assertStringContainsString('A Article Updated', $rssAfterIncremental);
        $this->assertStringContainsString('B Article', $rssAfterIncremental);
        $this->assertStringContainsString('C Article', $rssAfterIncremental);
    }

    public function testCleanAndIncrementalTogetherStillProduceFullRebuild(): void
    {
        $result = $this->runRenderCommand();
        $this->assertEquals(0, $result);

        $bMtimeBefore = filemtime($this->testOutputDir . '/news/b.html');

        // --clean wipes public/ first, so even unchanged files lose their cached output
        // and must be fully re-rendered, regardless of --incremental.
        sleep(1);
        $result = $this->runRenderCommand(['--clean' => true, '--incremental' => true]);
        $this->assertEquals(0, $result);

        $this->assertFileExists($this->testOutputDir . '/news/b.html');
        $bMtimeAfter = filemtime($this->testOutputDir . '/news/b.html');
        $this->assertGreaterThan($bMtimeBefore, $bMtimeAfter);

        $sitemap = file_get_contents($this->testOutputDir . '/sitemap.xml');
        $this->assertNotFalse($sitemap);
        $this->assertSame(4, substr_count($sitemap, '<url>'));
    }
}
