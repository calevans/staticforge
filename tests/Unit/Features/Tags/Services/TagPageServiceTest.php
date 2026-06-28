<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Tags\Services;

use EICC\StaticForge\Core\Application;
use EICC\StaticForge\Features\Tags\Services\PaginationService;
use EICC\StaticForge\Features\Tags\Services\TagPageService;
use EICC\StaticForge\Features\Tags\Services\TagsService;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use PHPUnit\Framework\MockObject\MockObject;

class TagPageServiceTest extends UnitTestCase
{
    private TagPageService $service;
    private Log&MockObject $logger;
    private TagsService&MockObject $tagsService;
    private TemplateRenderer&MockObject $templateRenderer;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/staticforge_tags_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->logger = $this->createMock(Log::class);
        $this->tagsService = $this->createMock(TagsService::class);
        $this->templateRenderer = $this->createMock(TemplateRenderer::class);
        $this->templateRenderer->method('render')->willReturn('<html></html>');

        $this->service = new TagPageService(
            $this->logger,
            $this->tagsService,
            new PaginationService(),
            $this->templateRenderer,
            10
        );

        $this->setContainerVariable('OUTPUT_DIR', $this->tempDir);
        $this->setContainerVariable('discovered_files', []);
    }

    protected function tearDown(): void
    {
        $this->recursiveRemoveDirectory($this->tempDir);
        parent::tearDown();
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * @param array<string, array<int, array{path: string, url: string, metadata: array<string, mixed>}>> $discoveredFiles
     */
    private function setDiscoveredFiles(array $discoveredFiles): void
    {
        $this->setContainerVariable('discovered_files', $discoveredFiles);
    }

    public function testNoTagsDoesNotCallApplication(): void
    {
        $this->tagsService->method('getTagIndex')->willReturn([]);

        $mockApp = $this->createMock(Application::class);
        $mockApp->expects($this->never())->method('renderSingleFile');
        $this->container->add(Application::class, $mockApp);

        $this->service->generateTagPages($this->container);
    }

    public function testSingleTagSinglePageNoPaginationLinks(): void
    {
        $this->tagsService->method('getTagIndex')->willReturn([
            'php' => ['/path/to/post1.md'],
        ]);

        $this->setDiscoveredFiles([
            [
                'path' => '/path/to/post1.md',
                'url' => '/post1/',
                'metadata' => ['title' => 'Post 1', 'date' => '2023-01-01'],
            ],
        ]);

        $expectedOutputPath = $this->tempDir . DIRECTORY_SEPARATOR . 'tags' . DIRECTORY_SEPARATOR . 'php'
            . DIRECTORY_SEPARATOR . 'index.html';

        $mockApp = $this->createMock(Application::class);
        $mockApp->expects($this->once())
            ->method('renderSingleFile')
            ->with(
                $this->equalTo('__tag__:php'),
                $this->callback(function ($context) use ($expectedOutputPath) {
                    $metadata = $context['file_metadata'];
                    return $context['output_path'] === $expectedOutputPath
                        && $context['bypass_tag_defer'] === true
                        && $metadata['tag'] === 'php'
                        && $metadata['tag_slug'] === 'php'
                        && $metadata['tag_files_count'] === 1
                        && $metadata['total_files'] === 1
                        && $metadata['current_page'] === 1
                        && $metadata['total_pages'] === 1
                        && $metadata['pagination_prev_url'] === null
                        && $metadata['pagination_next_url'] === null
                        && isset($context['rendered_content']);
                })
            );

        $this->container->add(Application::class, $mockApp);

        $this->service->generateTagPages($this->container);
    }

    public function testTagExceedingItemsPerPageProducesPaginatedPages(): void
    {
        $filePaths = [];
        $discovered = [];
        for ($i = 1; $i <= 15; $i++) {
            $path = "/path/to/post{$i}.md";
            $filePaths[] = $path;
            $discovered[] = [
                'path' => $path,
                'url' => "/post{$i}/",
                'metadata' => ['title' => "Post {$i}", 'date' => '2023-01-01'],
            ];
        }

        $this->tagsService->method('getTagIndex')->willReturn(['php' => $filePaths]);
        $this->setDiscoveredFiles($discovered);

        $tagDir = $this->tempDir . DIRECTORY_SEPARATOR . 'tags' . DIRECTORY_SEPARATOR . 'php';
        $expectedPaths = [
            1 => $tagDir . DIRECTORY_SEPARATOR . 'index.html',
            2 => $tagDir . DIRECTORY_SEPARATOR . 'page' . DIRECTORY_SEPARATOR . '2' . DIRECTORY_SEPARATOR . 'index.html',
        ];
        $expectedPrev = [1 => null, 2 => '/tags/php/'];
        $expectedNext = [1 => '/tags/php/page/2/', 2 => null];
        $expectedCounts = [1 => 10, 2 => 5];

        $calls = [];

        $mockApp = $this->createMock(Application::class);
        $mockApp->expects($this->exactly(2))
            ->method('renderSingleFile')
            ->with(
                $this->equalTo('__tag__:php'),
                $this->callback(function ($context) use (
                    &$calls,
                    $expectedPaths,
                    $expectedPrev,
                    $expectedNext,
                    $expectedCounts
                ) {
                    $metadata = $context['file_metadata'];
                    $page = $metadata['current_page'];
                    $calls[] = $page;

                    return $context['output_path'] === $expectedPaths[$page]
                        && $metadata['total_pages'] === 2
                        && $metadata['total_files'] === 15
                        && $metadata['tag_files_count'] === $expectedCounts[$page]
                        && $metadata['pagination_prev_url'] === $expectedPrev[$page]
                        && $metadata['pagination_next_url'] === $expectedNext[$page];
                })
            );

        $this->container->add(Application::class, $mockApp);

        $this->service->generateTagPages($this->container);

        $this->assertSame([1, 2], $calls);
    }

    public function testSlugSanitizationForTagsWithSpacesAndPunctuation(): void
    {
        $this->tagsService->method('getTagIndex')->willReturn([
            'web dev!' => ['/path/to/post1.md'],
        ]);

        $this->setDiscoveredFiles([
            ['path' => '/path/to/post1.md', 'url' => '/post1/', 'metadata' => ['title' => 'Post 1', 'date' => '2023-01-01']],
        ]);

        $expectedOutputPath = $this->tempDir . DIRECTORY_SEPARATOR . 'tags' . DIRECTORY_SEPARATOR . 'web-dev'
            . DIRECTORY_SEPARATOR . 'index.html';

        $mockApp = $this->createMock(Application::class);
        $mockApp->expects($this->once())
            ->method('renderSingleFile')
            ->with(
                $this->equalTo('__tag__:web-dev'),
                $this->callback(function ($context) use ($expectedOutputPath) {
                    return $context['output_path'] === $expectedOutputPath
                        && $context['file_metadata']['tag_slug'] === 'web-dev';
                })
            );

        $this->container->add(Application::class, $mockApp);

        $this->service->generateTagPages($this->container);
    }

    public function testBypassTagDeferFlagIsPassedThrough(): void
    {
        $this->tagsService->method('getTagIndex')->willReturn([
            'php' => ['/path/to/post1.md'],
        ]);

        $this->setDiscoveredFiles([
            ['path' => '/path/to/post1.md', 'url' => '/post1/', 'metadata' => ['title' => 'Post 1', 'date' => '2023-01-01']],
        ]);

        $mockApp = $this->createMock(Application::class);
        $mockApp->expects($this->once())
            ->method('renderSingleFile')
            ->with(
                $this->anything(),
                $this->callback(function ($context) {
                    return ($context['bypass_tag_defer'] ?? false) === true;
                })
            );

        $this->container->add(Application::class, $mockApp);

        $this->service->generateTagPages($this->container);
    }
}
