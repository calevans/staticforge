<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration\Sitemap;

use EICC\StaticForge\Features\SiteBuilder\Commands\RenderSiteCommand;
use EICC\StaticForge\Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * A type: category definition carrying noindex / sitemap:false / robots:no drops the
 * generated category index page from sitemap.xml while the category's articles stay.
 */
class CategoryCascadeTest extends IntegrationTestCase
{
    private string $outputDir;
    private string $contentDir;
    private string $templateDir;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid('', true);
        $this->outputDir = sys_get_temp_dir() . '/staticforge_casc_out_' . $suffix;
        $this->contentDir = sys_get_temp_dir() . '/staticforge_casc_content_' . $suffix;
        $this->templateDir = sys_get_temp_dir() . '/staticforge_casc_tpl_' . $suffix;

        mkdir($this->outputDir, 0755, true);
        mkdir($this->contentDir, 0755, true);
        mkdir($this->templateDir . '/sample', 0755, true);

        $_ENV['SOURCE_DIR'] = $this->contentDir;
        $_ENV['OUTPUT_DIR'] = $this->outputDir;
        $_ENV['TEMPLATE_DIR'] = $this->templateDir;

        file_put_contents(
            $this->templateDir . '/sample/base.html.twig',
            '<!DOCTYPE html><html><head><title>{{ title | default("T") }}</title></head>'
            . '<body><main>{{ content | raw }}</main></body></html>'
        );
        file_put_contents(
            $this->templateDir . '/sample/category-index.html.twig',
            '<!DOCTYPE html><html><head><title>{{ title | default("C") }}</title></head><body>'
            . '{% for file in category_files %}<a href="{{ file.url }}">{{ file.title }}</a>{% endfor %}'
            . '</body></html>'
        );

        file_put_contents(
            $this->contentDir . '/a.html',
            "<!--\n---\ntitle: \"A Article\"\ncategory: \"news\"\n---\n-->\n<p>Article A</p>"
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputDir);
        $this->removeDirectory($this->contentDir);
        $this->removeDirectory($this->templateDir);
        parent::tearDown();
    }

    private function render(string $extraFrontmatter): void
    {
        file_put_contents(
            $this->contentDir . '/news.md',
            "---\ntitle: News\ntype: category\ntemplate: category-index\n{$extraFrontmatter}\n---\n"
        );

        $container = $this->createContainer(__DIR__ . '/../../.env.testing');
        $container->updateVariable('SOURCE_DIR', $this->contentDir);
        $container->updateVariable('OUTPUT_DIR', $this->outputDir);
        $container->updateVariable('TEMPLATE_DIR', $this->templateDir);

        $application = new Application();
        $application->addCommand(new RenderSiteCommand($container));
        $command = $application->find('site:render');
        $tester = new CommandTester($command);

        $this->assertSame(0, $tester->execute(['command' => $command->getName()]));
    }

    private function sitemap(): string
    {
        $xml = file_get_contents($this->outputDir . '/sitemap.xml');
        $this->assertNotFalse($xml);

        return $xml;
    }

    public function testCategoryIndexIsInSitemapWhenDefinitionHasNoExclusion(): void
    {
        $this->render('');

        $this->assertFileExists($this->outputDir . '/news/index.html');
        $this->assertStringContainsString('<loc>https://test.example.com/news/</loc>', $this->sitemap());
        $this->assertStringContainsString('<loc>https://test.example.com/news/a.html</loc>', $this->sitemap());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function exclusionProvider(): array
    {
        return [
            'noindex true' => ['noindex: true'],
            'sitemap false' => ['sitemap: false'],
            'robots no' => ['robots: no'],
        ];
    }

    #[DataProvider('exclusionProvider')]
    public function testCategoryDefinitionExclusionRemovesIndexPageButKeepsArticles(string $frontmatter): void
    {
        $this->render($frontmatter);

        $this->assertFileExists($this->outputDir . '/news/index.html');
        $sitemap = $this->sitemap();
        $this->assertStringNotContainsString('<loc>https://test.example.com/news/</loc>', $sitemap);
        $this->assertStringContainsString('<loc>https://test.example.com/news/a.html</loc>', $sitemap);
    }

    public function testNoindexOnCategoryDefinitionNeverProducesDisallow(): void
    {
        $this->render('noindex: true');

        $robots = file_get_contents($this->outputDir . '/robots.txt');
        $this->assertNotFalse($robots);
        $this->assertStringNotContainsString('Disallow: /news', $robots);
    }
}
