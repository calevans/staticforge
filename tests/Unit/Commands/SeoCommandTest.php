<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\Audit\SeoCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SeoCommandTest extends UnitTestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputDir = sys_get_temp_dir() . '/staticforge_seo_test_' . uniqid();
        mkdir($this->outputDir);
        $this->setContainerVariable('OUTPUT_DIR', $this->outputDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputDir);
        parent::tearDown();
    }

    private function makeCommandTester(): CommandTester
    {
        $application = new Application();
        $application->add(new SeoCommand($this->container));
        $command = $application->find('audit:seo');

        return new CommandTester($command);
    }

    private function writeHtmlFile(string $name, string $content): void
    {
        file_put_contents($this->outputDir . '/' . $name, $content);
    }

    public function testFailsWhenOutputDirectoryMissing(): void
    {
        $this->removeDirectory($this->outputDir);

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Output directory not found', $tester->getDisplay());
    }

    public function testPassesWithCompleteSeoMetadata(): void
    {
        $this->writeHtmlFile('index.html', <<<HTML
<html>
<head>
    <title>A Perfectly Good Page Title</title>
    <meta name="description" content="This is a sufficiently long meta description for SEO purposes, well past fifty characters.">
    <link rel="canonical" href="https://example.com/">
</head>
<body>Content</body>
</html>
HTML);
        file_put_contents($this->outputDir . '/sitemap.xml', '<urlset></urlset>');
        file_put_contents($this->outputDir . '/robots.txt', 'User-agent: *');

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
    }

    public function testReportsEmptyFileAsError(): void
    {
        $this->writeHtmlFile('empty.html', '');

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('File is empty', $tester->getDisplay());
    }

    public function testReportsMissingTitleTag(): void
    {
        $this->writeHtmlFile('no-title.html', '<html><head></head><body>Content</body></html>');

        $tester = $this->makeCommandTester();
        $tester->execute([]);

        $this->assertStringContainsString('Missing <title> tag', $tester->getDisplay());
    }

    public function testReportsTitleTooShort(): void
    {
        $this->writeHtmlFile('short-title.html', '<html><head><title>Hi</title></head><body>x</body></html>');

        $tester = $this->makeCommandTester();
        $tester->execute(['--min-title' => 10]);

        $this->assertStringContainsString('Title too short', $tester->getDisplay());
    }

    public function testReportsTitleTooLong(): void
    {
        $longTitle = str_repeat('A', 100);
        $this->writeHtmlFile('long-title.html', "<html><head><title>{$longTitle}</title></head><body>x</body></html>");

        $tester = $this->makeCommandTester();
        $tester->execute(['--max-title' => 60]);

        $this->assertStringContainsString('Title too long', $tester->getDisplay());
    }

    public function testReportsMissingMetaDescription(): void
    {
        $this->writeHtmlFile('no-desc.html', '<html><head><title>Some Decent Title</title></head><body>x</body></html>');

        $tester = $this->makeCommandTester();
        $tester->execute([]);

        $this->assertStringContainsString('Missing <meta name="description"> tag', $tester->getDisplay());
    }

    public function testReportsEmptyMetaDescriptionContent(): void
    {
        $this->writeHtmlFile('empty-desc.html', <<<HTML
<html><head><title>Some Decent Title</title><meta name="description" content=""></head><body>x</body></html>
HTML);

        $tester = $this->makeCommandTester();
        $tester->execute([]);

        $this->assertStringContainsString('meta description content is empty', $tester->getDisplay());
    }

    public function testReportsMissingCanonicalLink(): void
    {
        $this->writeHtmlFile('no-canonical.html', <<<HTML
<html><head><title>Some Decent Title</title><meta name="description" content="A description long enough to pass the minimum length check easily."></head><body>x</body></html>
HTML);

        $tester = $this->makeCommandTester();
        $tester->execute([]);

        $this->assertStringContainsString('Missing <link rel="canonical"> tag', $tester->getDisplay());
    }

    public function testWarnsOnDuplicateTitlesAcrossFiles(): void
    {
        $html = <<<HTML
<html><head><title>Duplicate Title Here</title><meta name="description" content="A description long enough to pass the minimum length check easily."><link rel="canonical" href="https://example.com/x"></head><body>x</body></html>
HTML;
        $this->writeHtmlFile('page1.html', $html);
        $this->writeHtmlFile('page2.html', $html);

        $tester = $this->makeCommandTester();
        $tester->execute([]);

        $this->assertStringContainsString('Duplicate Title', $tester->getDisplay());
    }

    public function testWarnsWhenSitemapMissing(): void
    {
        $this->writeHtmlFile('index.html', <<<HTML
<html><head><title>Some Decent Title</title><meta name="description" content="A description long enough to pass the minimum length check easily."><link rel="canonical" href="https://example.com/"></head><body>x</body></html>
HTML);

        $tester = $this->makeCommandTester();
        $tester->execute([]);

        $this->assertStringContainsString('sitemap.xml not found', $tester->getDisplay());
    }
}
