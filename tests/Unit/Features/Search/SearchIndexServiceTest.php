<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Search;

use EICC\StaticForge\Features\Search\Services\SearchIndexService;
use EICC\Utils\Container;
use EICC\Utils\Log;
use PHPUnit\Framework\TestCase;

class SearchIndexServiceTest extends TestCase
{
    private SearchIndexService $service;
    private Container $container;
    private Log $logger;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(Log::class);
        $this->container = $this->createMock(Container::class);
        $this->service = new SearchIndexService($this->logger);

        $this->tempDir = sys_get_temp_dir() . '/staticforge_search_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->recursiveRemove($this->tempDir);
        }
    }

    private function recursiveRemove(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRemove("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function testCollectPageAddsDocument(): void
    {
        $this->container->method('getVariable')
            ->willReturnMap([
                ['site_config', []],
                ['OUTPUT_DIR', $this->tempDir],
                ['SITE_BASE_URL', 'https://example.com']
            ]);

        $parameters = [
            'metadata' => ['title' => 'Test Page', 'tags' => ['foo', 'bar']],
            'output_path' => $this->tempDir . '/test.html',
            'rendered_content' => '<h1>Test Page</h1><p>This is content.</p>'
        ];

        $this->service->collectPage($this->container, $parameters);
        $this->service->buildIndex($this->container, []);

        $this->assertFileExists($this->tempDir . '/search.json');
        $json = json_decode(file_get_contents($this->tempDir . '/search.json'), true);

        $this->assertCount(1, $json);
        $this->assertEquals('Test Page', $json[0]['title']);
        $this->assertEquals('This is content.', $json[0]['text']);
        $this->assertEquals('https://example.com/test.html', $json[0]['url']);
        $this->assertEquals('foo bar', $json[0]['tags']);
    }

    public function testSkipsPageWithSearchIndexFalse(): void
    {
        $this->container->method('getVariable')
            ->willReturnMap([
                ['site_config', []],
                ['OUTPUT_DIR', $this->tempDir],
                ['SITE_BASE_URL', 'https://example.com']
            ]);

        $parameters = [
            'metadata' => ['title' => 'Hidden Page', 'search_index' => false],
            'output_path' => $this->tempDir . '/hidden.html',
            'rendered_content' => 'Content'
        ];

        $this->service->collectPage($this->container, $parameters);
        $this->service->buildIndex($this->container, []);

        $this->assertFileExists($this->tempDir . '/search.json');
        $json = json_decode(file_get_contents($this->tempDir . '/search.json'), true);

        $this->assertCount(0, $json);
    }

    public function testSkipsExcludedPaths(): void
    {
        $this->container->method('getVariable')
            ->willReturnMap([
                ['site_config', ['search' => ['exclude_paths' => ['/tags/']]]],
                ['OUTPUT_DIR', $this->tempDir],
                ['SITE_BASE_URL', 'https://example.com']
            ]);

        $parameters = [
            'metadata' => ['title' => 'Tag Page'],
            'output_path' => $this->tempDir . '/tags/foo.html',
            'rendered_content' => 'Content'
        ];

        $this->service->collectPage($this->container, $parameters);
        $this->service->buildIndex($this->container, []);

        $json = json_decode(file_get_contents($this->tempDir . '/search.json'), true);
        $this->assertCount(0, $json);
    }
}
