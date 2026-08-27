<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration;

use EICC\StaticForge\Core\Application;
use EICC\Utils\Container;

/**
 * Regression test for unquoted YAML dates in content frontmatter.
 *
 * `date: 2024-03-05` (no quotes) is resolved by Symfony's YAML parser to an
 * int timestamp rather than a string. The failure that exposed this did not
 * happen in the parser: it surfaced layers away, in Sitemap at POST_RENDER
 * and RssFeed at POST_LOOP, both of which hand the value to strtotime() or
 * return it from a `: string` method. A test against the parser alone would
 * not have caught it, so this drives a real end-to-end build and asserts on
 * the generated artifacts.
 */
class UnquotedFrontmatterDateTest extends IntegrationTestCase
{
    private string $testOutputDir;
    private string $testContentDir;
    private string $testTemplateDir;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid('', true) . '_' . getmypid();
        $this->testOutputDir = sys_get_temp_dir() . '/staticforge_ufd_output_' . $suffix;
        $this->testContentDir = sys_get_temp_dir() . '/staticforge_ufd_content_' . $suffix;
        $this->testTemplateDir = sys_get_temp_dir() . '/staticforge_ufd_templates_' . $suffix;

        mkdir($this->testOutputDir, 0755, true);
        mkdir($this->testContentDir, 0755, true);
        mkdir($this->testTemplateDir . '/sample', 0755, true);

        $_ENV['SOURCE_DIR'] = $this->testContentDir;
        $_ENV['OUTPUT_DIR'] = $this->testOutputDir;
        $_ENV['PUBLIC_DIR'] = $this->testOutputDir;
        $_ENV['TEMPLATE_DIR'] = $this->testTemplateDir;
        $_ENV['SITE_NAME'] = 'Unquoted Date Test Site';
        $_ENV['SITE_BASE_URL'] = 'https://example.com/';

        file_put_contents(
            $this->testTemplateDir . '/sample/base.html.twig',
            "<!DOCTYPE html>\n<html><head><title>{{ title }}</title></head>\n"
            . "<body><h1>{{ title }}</h1><p class=\"date\">{{ date }}</p>\n"
            . "<main>{{ content | raw }}</main></body></html>\n"
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->testOutputDir);
        $this->removeDirectory($this->testContentDir);
        $this->removeDirectory($this->testTemplateDir);
    }

    private function configureContainer(Container $container): void
    {
        $vars = [
            'SOURCE_DIR' => $this->testContentDir,
            'OUTPUT_DIR' => $this->testOutputDir,
            'PUBLIC_DIR' => $this->testOutputDir,
            'TEMPLATE_DIR' => $this->testTemplateDir,
            'TEMPLATE' => 'sample',
        ];

        foreach ($vars as $key => $value) {
            if (!$container->hasVariable($key)) {
                $container->setVariable($key, $value);
            } else {
                $container->updateVariable($key, $value);
            }
        }
    }

    private function readFile(string $path): string
    {
        $content = file_get_contents($path);
        $this->assertNotFalse($content, "Failed to read file: {$path}");
        return $content;
    }

    public function testBuildSucceedsWithUnquotedFrontmatterDate(): void
    {
        // Deliberately unquoted -- this is the whole point of the test.
        $post = <<<'MD'
---
title: "Unquoted Date Post"
category: "Technology"
description: "A post whose date is not quoted"
date: 2024-03-05
---
Body of the post.
MD;
        file_put_contents($this->testContentDir . '/unquoted.md', $post);

        $container = $this->createContainer(__DIR__ . '/../.env.testing');
        $this->configureContainer($container);

        $application = new Application($container);
        $this->assertTrue(
            $application->generate(),
            'Build must not fail on an unquoted frontmatter date'
        );

        $page = $this->readFile($this->testOutputDir . '/technology/unquoted.html');
        $this->assertStringContainsString(
            '2024-03-05',
            $page,
            'Rendered page must show the authored date, not a Unix timestamp'
        );
        $this->assertStringNotContainsString('1709596800', $page);

        $sitemap = $this->testOutputDir . "/sitemap.xml";
        $this->assertFileExists($sitemap);
        $this->assertStringContainsString(
            "<lastmod>2024-03-05</lastmod>",
            $this->readFile($sitemap),
            "Sitemap lastmod must come from the authored date"
        );

        $rss = $this->testOutputDir . "/technology/rss.xml";
        $this->assertFileExists($rss);
        $this->assertStringContainsString(
            "05 Mar 2024",
            $this->readFile($rss),
            "RSS pubDate must come from the authored date"
        );
    }

    public function testUnquotedDateMatchesQuotedDateEndToEnd(): void
    {
        $unquoted = "---\ntitle: \"Unquoted\"\ncategory: \"Tech\"\ndate: 2024-03-05\n---\nBody.\n";
        $quoted = "---\ntitle: \"Quoted\"\ncategory: \"Tech\"\ndate: \"2024-03-05\"\n---\nBody.\n";

        file_put_contents($this->testContentDir . '/unquoted.md', $unquoted);
        file_put_contents($this->testContentDir . '/quoted.md', $quoted);

        $container = $this->createContainer(__DIR__ . '/../.env.testing');
        $this->configureContainer($container);

        $this->assertTrue((new Application($container))->generate());

        $a = $this->readFile($this->testOutputDir . '/tech/unquoted.html');
        $b = $this->readFile($this->testOutputDir . '/tech/quoted.html');

        $extract = static function (string $html): string {
            preg_match('/<p class="date">(.*?)<\/p>/', $html, $m);
            return $m[1] ?? '';
        };

        $this->assertSame('2024-03-05', $extract($a));
        $this->assertSame(
            $extract($b),
            $extract($a),
            'Quoting the date in frontmatter must not change the rendered output'
        );
    }
}
