<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration\SiteFeed;

use DOMDocument;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\SiteBuilder\Commands\RenderSiteCommand;
use EICC\StaticForge\Tests\Integration\IntegrationTestCase;
use EICC\Utils\Container;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Builds a throwaway site through `site:render` with a chosen `feed:` config.
 * Timezone is pinned to UTC so every date in a feed is deterministic.
 */
abstract class SiteFeedTestCase extends IntegrationTestCase
{
    protected const BASE_URL = 'https://feed.example.com/';

    protected string $outputDir;
    protected string $contentDir;
    protected string $templateDir;
    protected Container $container;
    private string $previousTimezone;
    private string $logFile = '';
    private int $logOffset = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        $suffix = uniqid('', true) . '_' . getmypid();
        $this->outputDir = sys_get_temp_dir() . '/sf_sitefeed_out_' . $suffix;
        $this->contentDir = sys_get_temp_dir() . '/sf_sitefeed_content_' . $suffix;
        $this->templateDir = sys_get_temp_dir() . '/sf_sitefeed_tpl_' . $suffix;
        mkdir($this->outputDir, 0755, true);
        mkdir($this->contentDir, 0755, true);
        mkdir($this->templateDir . '/sample', 0755, true);

        file_put_contents(
            $this->templateDir . '/sample/base.html.twig',
            "<!DOCTYPE html>\n<html>\n<head><title>{{ title | default('Untitled') }}</title></head>\n"
            . "<body>\n<h1>{{ title }}</h1>\n<main>{{ content | raw }}</main>\n"
            . "<p id=\"fl\">{{ feed_links is defined ? 'links' : 'nolinks' }}"
            . "{% for l in feed_links | default([]) %}[{{ l.type }}|{{ l.href }}]{% endfor %}</p>\n</body>\n</html>\n"
        );
        file_put_contents(
            $this->templateDir . '/sample/category-index.html.twig',
            "<!DOCTYPE html>\n<html><head><title>{{ title | default('Category') }}</title></head>\n"
            . "<body>{% for file in category_files %}<a href=\"{{ file.url }}\">{{ file.title }}</a>{% endfor %}"
            . "</body></html>\n"
        );
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone);
        $this->removeDirectory($this->outputDir);
        $this->removeDirectory($this->contentDir);
        $this->removeDirectory($this->templateDir);
        parent::tearDown();
    }

    /**
     * Writes a markdown page. Frontmatter values are JSON-encoded, which is valid YAML,
     * so "false" (string) and false (bool) stay distinct.
     *
     * @param array<string, mixed> $frontmatter
     */
    protected function page(string $name, array $frontmatter, string $body = "Body text.\n"): void
    {
        $fm = '';
        foreach ($frontmatter as $key => $value) {
            $fm .= $key . ': ' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }
        $this->raw($name, "---\n" . $fm . "---\n" . $body);
    }

    protected function category(string $name, bool $podcast = false): void
    {
        $fm = ['title' => ucfirst($name), 'type' => 'category'];
        if ($podcast) {
            $fm['podcast'] = true;
        }
        $this->page($name . '.md', $fm, '');
    }

    protected function raw(string $name, string $content): void
    {
        $path = $this->contentDir . '/' . $name;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $content);
    }

    /**
     * @param array<string, mixed>|null $feed Value of the `feed:` key, or null to omit it
     * @param array<string, mixed> $extraConfig More site_config keys
     * @param callable(EventManager): void|null $beforeRun Register listeners here
     */
    protected function render(
        ?array $feed,
        array $extraConfig = [],
        ?callable $beforeRun = null,
        string $baseUrl = self::BASE_URL,
        string $template = 'sample',
        ?string $templateDir = null
    ): void {
        $container = $this->createContainer(__DIR__ . '/../../.env.testing');
        $this->logFile = (string) $_ENV['LOG_FILE'];
        $this->logOffset = is_file($this->logFile) ? (int) filesize($this->logFile) : 0;

        $vars = [
            'SOURCE_DIR' => $this->contentDir,
            'OUTPUT_DIR' => $this->outputDir,
            'PUBLIC_DIR' => $this->outputDir,
            'TEMPLATE_DIR' => $templateDir ?? $this->templateDir,
            'TEMPLATE' => $template,
            'SITE_BASE_URL' => $baseUrl,
        ];
        foreach ($vars as $key => $value) {
            $this->setVar($container, $key, $value);
        }

        // Feature packages from vendor/ rewrite page HTML; keep the fixtures independent of them.
        $config = array_merge([
            'site' => ['name' => 'Feed Site'],
            'disabled_features' => ['AnswerEngineOptimization', 'SocialMetadata', 'GoogleAnalytics', 'ChapterNav'],
        ], $extraConfig);
        if ($feed !== null) {
            $config['feed'] = $feed;
        }
        $this->setVar($container, 'site_config', $config);

        if ($beforeRun !== null) {
            $beforeRun($container->get(EventManager::class));
        }

        $application = new Application();
        $application->addCommand(new RenderSiteCommand($container));
        $command = $application->find('site:render');
        $tester = new CommandTester($command);
        $status = $tester->execute(['command' => $command->getName()]);
        $this->assertSame(0, $status, 'site:render must succeed: ' . $tester->getDisplay());
        $this->container = $container;
    }

    /**
     * Log lines written since the last render() started.
     */
    protected function newLog(): string
    {
        clearstatcache();
        if ($this->logFile === '' || !is_file($this->logFile)) {
            return '';
        }
        $log = file_get_contents($this->logFile, false, null, $this->logOffset);

        return $log === false ? '' : $log;
    }

    protected function read(string $relative): string
    {
        $this->assertFileExists($this->outputDir . '/' . $relative);
        $content = file_get_contents($this->outputDir . '/' . $relative);
        $this->assertNotFalse($content);

        return $content;
    }

    protected function has(string $relative): bool
    {
        return file_exists($this->outputDir . '/' . $relative);
    }

    protected function xml(string $relative): DOMDocument
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $source = $this->read($relative);
        if ($source === '') {
            $this->fail('feed is empty');
        }
        $ok = $dom->loadXML($source);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $this->assertTrue($ok, $relative . ' must be well-formed XML: ' . ($errors[0]->message ?? ''));

        return $dom;
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(string $relative = 'feed.json'): array
    {
        $decoded = json_decode($this->read($relative), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return list<string>
     */
    protected function jsonTitles(string $relative = 'feed.json'): array
    {
        return array_values(array_map(static fn (array $i): string => $i['title'], $this->json($relative)['items']));
    }

    /**
     * @return list<string>
     */
    protected function rssTitles(string $relative = 'feed.xml'): array
    {
        $titles = [];
        foreach ($this->xml($relative)->getElementsByTagName('item') as $item) {
            $titles[] = $item->getElementsByTagName('title')->item(0)->textContent ?? '';
        }

        return $titles;
    }

    /**
     * @return list<string>
     */
    protected function atomTitles(string $relative = 'feed.atom'): array
    {
        $titles = [];
        foreach ($this->xml($relative)->getElementsByTagName('entry') as $entry) {
            $titles[] = $entry->getElementsByTagName('title')->item(0)->textContent ?? '';
        }

        return $titles;
    }

    /**
     * Every file under the output dir, relative path => sha1.
     *
     * @return array<string, string>
     */
    protected function outputManifest(): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $this->outputDir,
            \FilesystemIterator::SKIP_DOTS
        ));
        foreach ($it as $file) {
            if ($file->isFile()) {
                $files[substr($file->getPathname(), strlen($this->outputDir) + 1)] = (string) sha1_file($file->getPathname());
            }
        }
        ksort($files);

        return $files;
    }

    /**
     * What the test template reports about feed_links on a page: null when the variable is
     * not defined at all, otherwise the list of "type|href" pairs.
     *
     * @return list<string>|null
     */
    protected function advertisedLinks(string $pageHtml = "a.html"): ?array
    {
        $html = $this->read($pageHtml);
        if (preg_match("~<p id=\"fl\">(nolinks|links)(.*?)</p>~s", $html, $m) !== 1) {
            $this->fail('page must contain the feed_links probe');
        }
        if ($m[1] === "nolinks") {
            return null;
        }
        preg_match_all("~\[([^\]]*)\]~", $m[2], $pairs);

        return $pairs[1];
    }

    private function setVar(Container $container, string $key, mixed $value): void
    {
        if ($container->hasVariable($key)) {
            $container->updateVariable($key, $value);
        } else {
            $container->setVariable($key, $value);
        }
    }
}
