<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Sitemap;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\OutputWriter;
use EICC\StaticForge\Features\Sitemap\Services\SitemapService;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use PHPUnit\Framework\Attributes\DataProvider;

class SitemapExclusionTest extends UnitTestCase
{
    private SitemapService $service;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/staticforge_sitemap_excl_' . uniqid('', true);
        mkdir($this->tempDir, 0755, true);

        $this->setContainerVariable('OUTPUT_DIR', $this->tempDir);
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');

        $this->service = new SitemapService(
            $this->createMock(Log::class),
            $this->container->get(OutputWriter::class),
            $this->container
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function collect(string $relativePath, array $metadata = [], string $filePath = ''): void
    {
        $this->service->collectUrl(new RenderEvent(
            name: 'POST_RENDER',
            filePath: $filePath,
            fileUrl: '',
            metadata: $metadata,
            outputPath: $this->tempDir . '/' . $relativePath,
        ));
    }

    private function sitemap(): string
    {
        $this->service->generateSitemap();
        $path = $this->tempDir . '/sitemap.xml';
        if (!is_file($path)) {
            return '';
        }
        $content = file_get_contents($path);
        $this->assertNotFalse($content);

        return $content;
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function excludedMetadataProvider(): array
    {
        return [
            'sitemap bool false' => [['sitemap' => false]],
            'sitemap string false' => [['sitemap' => 'false']],
            'noindex bool true' => [['noindex' => true]],
            'noindex string true' => [['noindex' => 'true']],
            'robots string no' => [['robots' => 'no']],
            'robots bool false' => [['robots' => false]],
            'robots padded upper NO' => [['robots' => ' NO ']],
            'sitemap true but noindex true' => [['sitemap' => true, 'noindex' => true]],
            'sitemap true but robots no' => [['sitemap' => true, 'robots' => 'no']],
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    #[DataProvider('excludedMetadataProvider')]
    public function testPageIsOmittedWhenMetadataExcludesIt(array $metadata): void
    {
        $this->collect('page.html', $metadata);

        $this->assertSame('', $this->sitemap());
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function includedMetadataProvider(): array
    {
        return [
            'no metadata' => [[]],
            'search_index false' => [['search_index' => false]],
            'search_index string false' => [['search_index' => 'false']],
            'sitemap true' => [['sitemap' => true]],
            'noindex false' => [['noindex' => false]],
            'noindex string false' => [['noindex' => 'false']],
            'robots yes' => [['robots' => 'yes']],
            'robots empty string' => [['robots' => '']],
            'robots null' => [['robots' => null]],
            'sitemap garbage is not false' => [['sitemap' => 'garbage']],
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    #[DataProvider('includedMetadataProvider')]
    public function testPageIsKeptWhenMetadataDoesNotExcludeIt(array $metadata): void
    {
        $this->collect('page.html', $metadata);

        $this->assertStringContainsString('<loc>https://example.com/page.html</loc>', $this->sitemap());
    }

    public function testNotFoundPageIsOmittedWithoutFrontmatter(): void
    {
        $this->collect('404.html');
        $this->collect('kept.html');

        $xml = $this->sitemap();
        $this->assertStringNotContainsString('404.html', $xml);
        $this->assertStringContainsString('kept.html', $xml);
    }

    public function testNestedFileNamed404IsNotTreatedAsTheNotFoundPage(): void
    {
        $this->collect('docs/404.html');

        $this->assertStringContainsString('<loc>https://example.com/docs/404.html</loc>', $this->sitemap());
    }

    public function testEmptySitemapIsNotWrittenAtAll(): void
    {
        $this->collect('a.html', ['noindex' => true]);

        $this->service->generateSitemap();

        $this->assertFileDoesNotExist($this->tempDir . '/sitemap.xml');
    }

    public function testLastmodPrefersUpdatedOverDate(): void
    {
        $this->collect('p.html', ['updated' => '2024-05-05', 'date' => '2023-01-01']);

        $this->assertStringContainsString('<lastmod>2024-05-05</lastmod>', $this->sitemap());
    }

    public function testLastmodFallsBackToDateWhenUpdatedAbsent(): void
    {
        $this->collect('p.html', ['date' => '2023-01-01']);

        $this->assertStringContainsString('<lastmod>2023-01-01</lastmod>', $this->sitemap());
    }

    public function testLastmodFallsThroughUnparseableUpdatedToDate(): void
    {
        $this->collect('p.html', ['updated' => 'not a date', 'date' => '2023-02-02']);

        $this->assertStringContainsString('<lastmod>2023-02-02</lastmod>', $this->sitemap());
    }

    public function testLastmodFallsThroughUnparseableDatesToFileMtime(): void
    {
        $source = $this->tempDir . '/source.md';
        file_put_contents($source, 'x');
        touch($source, (int)strtotime('2021-03-04 12:00:00'));

        $this->collect('p.html', ['updated' => 'nope', 'date' => 'also nope'], $source);

        $this->assertStringContainsString('<lastmod>2021-03-04</lastmod>', $this->sitemap());
    }

    public function testLastmodUsesMtimeWhenNoDatesGiven(): void
    {
        $source = $this->tempDir . '/source.md';
        file_put_contents($source, 'x');
        touch($source, (int)strtotime('2020-06-07 12:00:00'));

        $this->collect('p.html', [], $source);

        $this->assertStringContainsString('<lastmod>2020-06-07</lastmod>', $this->sitemap());
    }

    public function testLastmodDefaultsToTodayWhenNothingUsable(): void
    {
        $this->collect('p.html', ['date' => 'garbage'], $this->tempDir . '/missing.md');

        $this->assertStringContainsString('<lastmod>' . date('Y-m-d') . '</lastmod>', $this->sitemap());
    }

    public function testLastmodAcceptsDateTimeObjects(): void
    {
        $this->collect('p.html', ['updated' => new \DateTimeImmutable('2022-09-09')]);

        $this->assertStringContainsString('<lastmod>2022-09-09</lastmod>', $this->sitemap());
    }

    public function testLastmodIgnoresNonScalarUpdated(): void
    {
        $this->collect('p.html', ['updated' => ['x'], 'date' => '2023-03-03']);

        $this->assertStringContainsString('<lastmod>2023-03-03</lastmod>', $this->sitemap());
    }
}
