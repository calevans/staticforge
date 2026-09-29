<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration\NotFound;

use EICC\StaticForge\Features\SiteBuilder\Commands\RenderSiteCommand;
use EICC\StaticForge\Tests\Integration\IntegrationTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Builds a real site containing the shipped content/404.md with the shipped staticforce
 * 404 template and proves the page is emitted but kept out of sitemap, search and feeds.
 */
class NotFoundPageBuildTest extends IntegrationTestCase
{
    private string $outputDir;
    private string $contentDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputDir = sys_get_temp_dir() . '/staticforge_404_output_' . uniqid();
        $this->contentDir = sys_get_temp_dir() . '/staticforge_404_content_' . uniqid();
        mkdir($this->outputDir, 0755, true);
        mkdir($this->contentDir, 0755, true);

        $shipped = file_get_contents(dirname(__DIR__, 3) . '/content/404.md');
        $this->assertNotFalse($shipped);
        file_put_contents($this->contentDir . '/404.md', $shipped);

        file_put_contents($this->contentDir . '/news.md', "---\ntitle: News\ntype: category\n---\n\n");
        file_put_contents(
            $this->contentDir . '/story.md',
            "---\ntitle: \"A Real Story\"\ncategory: news\n---\n\nStory body.\n"
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputDir);
        $this->removeDirectory($this->contentDir);

        parent::tearDown();
    }

    private function build(): void
    {
        $container = $this->createContainer(__DIR__ . '/../../.env.testing');
        $container->updateVariable('SOURCE_DIR', $this->contentDir);
        $container->updateVariable('OUTPUT_DIR', $this->outputDir);
        $container->updateVariable('TEMPLATE_DIR', dirname(__DIR__, 3) . '/templates');
        $container->updateVariable('TEMPLATE', 'staticforce');

        $application = new Application();
        $application->addCommand(new RenderSiteCommand($container));
        $command = $application->find('site:render');

        $this->assertSame(0, (new CommandTester($command))->execute(['command' => $command->getName()]));
    }

    private function read(string $relative): string
    {
        $this->assertFileExists($this->outputDir . '/' . $relative);
        $content = file_get_contents($this->outputDir . '/' . $relative);
        $this->assertNotFalse($content);

        return $content;
    }

    public function testBuildEmitsNoindexed404HtmlAtOutputRoot(): void
    {
        $this->build();

        $html = $this->read('404.html');
        $this->assertSame(1, preg_match_all('/<h1>\s*Page not found\s*<\/h1>/', $html));
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
    }

    public function testSitemapExcludesThe404PageButKeepsRealPages(): void
    {
        $this->build();

        $sitemap = $this->read('sitemap.xml');
        $this->assertStringNotContainsString('404', $sitemap);
        $this->assertStringContainsString('story.html', $sitemap);
    }

    public function testSearchIndexExcludesThe404PageButKeepsRealPages(): void
    {
        $this->build();

        $search = $this->read('search.json');
        $this->assertStringNotContainsString('404', $search);
        $this->assertStringNotContainsString('Page not found', $search);
        $this->assertStringContainsString('A Real Story', $search);
    }

    public function testFeedsAreUnaffectedBy404Page(): void
    {
        $this->build();

        $rss = $this->read('news/rss.xml');
        $this->assertStringContainsString('A Real Story', $rss);
        $this->assertStringNotContainsString('404', $rss);
        $this->assertStringNotContainsString('Page not found', $rss);
    }
}
