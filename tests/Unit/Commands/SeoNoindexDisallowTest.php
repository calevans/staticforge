<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\Audit\SeoCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SeoNoindexDisallowTest extends UnitTestCase
{
    private const WARNING = 'Disallow hides the noindex from crawlers';

    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputDir = sys_get_temp_dir() . '/staticforge_seo_noindex_' . uniqid('', true);
        mkdir($this->outputDir . '/docs', 0755, true);
        $this->setContainerVariable('OUTPUT_DIR', $this->outputDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputDir);
        parent::tearDown();
    }

    private function page(string $name, bool $noindex): void
    {
        $robots = $noindex ? '<meta name="robots" content="noindex, follow">' : '';
        file_put_contents($this->outputDir . '/' . $name, <<<HTML
<html><head><title>A Perfectly Good Page Title</title>{$robots}
<meta name="description" content="This is a sufficiently long meta description for SEO purposes, well past fifty characters.">
<link rel="canonical" href="https://example.com/"></head><body>x</body></html>
HTML);
    }

    private function audit(): string
    {
        $application = new Application();
        $application->addCommand(new SeoCommand($this->container));
        $tester = new CommandTester($application->find('audit:seo'));
        $tester->execute([]);

        return $tester->getDisplay();
    }

    public function testWarnsWhenNoindexPageIsDisallowedInRobotsTxt(): void
    {
        $this->page('docs/secret.html', true);
        file_put_contents($this->outputDir . '/robots.txt', "User-agent: *\nDisallow: /docs/\n");

        $this->assertStringContainsString(self::WARNING, $this->audit());
    }

    public function testNoWarningWhenNoindexPageIsNotDisallowed(): void
    {
        $this->page('docs/secret.html', true);
        file_put_contents($this->outputDir . '/robots.txt', "User-agent: *\nDisallow: /private/\n");

        $this->assertStringNotContainsString(self::WARNING, $this->audit());
    }

    public function testNoWarningWhenDisallowedPageHasNoNoindex(): void
    {
        $this->page('docs/open.html', false);
        file_put_contents($this->outputDir . '/robots.txt', "User-agent: *\nDisallow: /docs/\n");

        $this->assertStringNotContainsString(self::WARNING, $this->audit());
    }

    public function testNoWarningWhenRobotsTxtMissing(): void
    {
        $this->page('docs/secret.html', true);

        $this->assertStringNotContainsString(self::WARNING, $this->audit());
    }

    public function testEmptyDisallowDoesNotMatchEveryPage(): void
    {
        $this->page('docs/secret.html', true);
        file_put_contents($this->outputDir . '/robots.txt', "User-agent: *\nDisallow:\n");

        $this->assertStringNotContainsString(self::WARNING, $this->audit());
    }

    public function testDisallowForOtherUserAgentIsIgnored(): void
    {
        $this->page('docs/secret.html', true);
        file_put_contents($this->outputDir . '/robots.txt', "User-agent: Bingbot\nDisallow: /docs/\n");

        $this->assertStringNotContainsString(self::WARNING, $this->audit());
    }
}
