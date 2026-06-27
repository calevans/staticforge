<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Search;

use EICC\StaticForge\Features\Search\Services\SearchIndexService;
use EICC\Utils\Container;
use EICC\Utils\Log;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SearchIndexServiceTest extends TestCase
{
    private SearchIndexService $service;
    private Container&MockObject $container;
    private Log&MockObject $logger;
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
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRemove("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readSearchIndex(): array
    {
        $contents = file_get_contents($this->tempDir . '/search.json');
        $this->assertNotFalse($contents, 'Expected search.json to be readable');

        $json = json_decode($contents, true);
        $this->assertIsArray($json);

        return $json;
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
        $json = $this->readSearchIndex();

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
        $json = $this->readSearchIndex();

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

        $json = $this->readSearchIndex();
        $this->assertCount(0, $json);
    }

    public function testSkipsExcludeContentInPaths(): void
    {
        $this->container->method('getVariable')
            ->willReturnMap([
                ['site_config', ['search' => ['exclude_content_in' => ['/drafts/']]]],
                ['OUTPUT_DIR', $this->tempDir],
                ['SITE_BASE_URL', 'https://example.com']
            ]);

        $parameters = [
            'metadata' => ['title' => 'Draft Page'],
            'output_path' => $this->tempDir . '/drafts/foo.html',
            'rendered_content' => 'Content'
        ];

        $this->service->collectPage($this->container, $parameters);
        $this->service->buildIndex($this->container, []);

        $json = $this->readSearchIndex();
        $this->assertCount(0, $json);
    }

    public function testCollectPageSkipsWhenOutputPathIsNull(): void
    {
        $this->container->method('getVariable')
            ->willReturnMap([
                ['site_config', []],
                ['OUTPUT_DIR', $this->tempDir],
                ['SITE_BASE_URL', 'https://example.com']
            ]);

        $parameters = [
            'metadata' => ['title' => 'No Output'],
            'output_path' => null,
            'rendered_content' => 'Content'
        ];

        $this->service->collectPage($this->container, $parameters);
        $this->service->buildIndex($this->container, []);

        $json = $this->readSearchIndex();
        $this->assertCount(0, $json);
    }

    public function testCollectPageHandlesStringTags(): void
    {
        $this->container->method('getVariable')
            ->willReturnMap([
                ['site_config', []],
                ['OUTPUT_DIR', $this->tempDir],
                ['SITE_BASE_URL', 'https://example.com']
            ]);

        $parameters = [
            'metadata' => ['title' => 'CSV Tags', 'tags' => 'foo, bar, baz'],
            'output_path' => $this->tempDir . '/csv.html',
            'rendered_content' => '<p>Some content here.</p>'
        ];

        $this->service->collectPage($this->container, $parameters);
        $this->service->buildIndex($this->container, []);

        $json = $this->readSearchIndex();
        $this->assertCount(1, $json);
        $this->assertSame('foo bar baz', $json[0]['tags']);
    }

    public function testCollectPageSkipsEmptySections(): void
    {
        $this->container->method('getVariable')
            ->willReturnMap([
                ['site_config', []],
                ['OUTPUT_DIR', $this->tempDir],
                ['SITE_BASE_URL', 'https://example.com']
            ]);

        $parameters = [
            'metadata' => ['title' => 'Headings Only'],
            'output_path' => $this->tempDir . '/headings.html',
            'rendered_content' => '<h2 id="empty-one"></h2><h2 id="empty-two"></h2>'
        ];

        $this->service->collectPage($this->container, $parameters);
        $this->service->buildIndex($this->container, []);

        $json = $this->readSearchIndex();
        $this->assertCount(0, $json);
    }

    public function testCollectPageHandlesEmptyHtmlContent(): void
    {
        $this->container->method('getVariable')
            ->willReturnMap([
                ['site_config', []],
                ['OUTPUT_DIR', $this->tempDir],
                ['SITE_BASE_URL', 'https://example.com']
            ]);

        $parameters = [
            'metadata' => ['title' => 'Empty'],
            'output_path' => $this->tempDir . '/empty.html',
            'rendered_content' => ''
        ];

        $this->service->collectPage($this->container, $parameters);
        $this->service->buildIndex($this->container, []);

        $json = $this->readSearchIndex();
        $this->assertCount(0, $json);
    }

    public function testBuildIndexLogsErrorWhenOutputDirNotSet(): void
    {
        $this->container->method('getVariable')->willReturn(null);

        $this->logger->expects($this->once())
            ->method('log')
            ->with('ERROR', $this->stringContains('OUTPUT_DIR not set'));

        $result = $this->service->buildIndex($this->container, ['foo' => 'bar']);

        $this->assertSame(['foo' => 'bar'], $result);
    }

    public function testCollectPageThrowsWhenSiteBaseUrlNotSet(): void
    {
        $this->container->method('getVariable')
            ->willReturnMap([
                ['site_config', []],
                ['OUTPUT_DIR', $this->tempDir],
                ['SITE_BASE_URL', null],
            ]);

        $parameters = [
            'metadata' => ['title' => 'Test'],
            'output_path' => $this->tempDir . '/test.html',
            'rendered_content' => '<p>Content</p>',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SITE_BASE_URL not set in container');

        $this->service->collectPage($this->container, $parameters);
    }
}
