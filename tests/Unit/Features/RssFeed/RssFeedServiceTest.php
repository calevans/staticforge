<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\RssFeed;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\RssFeed\Services\RssFeedService;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\OutputWriter;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use ReflectionMethod;

class RssFeedServiceTest extends UnitTestCase
{
    private RssFeedService $service;
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();
        $logger = $this->createMock(Log::class);
        $this->eventManager = $this->createMock(EventManager::class);
        $this->service = new RssFeedService(
            $logger,
            $this->eventManager,
            $this->container->get(OutputWriter::class),
            $this->container
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function makeEvent(
        ?string $outputPath,
        string $filePath,
        string $renderedContent,
        array $metadata = []
    ): RenderEvent {
        return new RenderEvent(
            name: 'POST_RENDER',
            filePath: $filePath,
            fileUrl: '',
            metadata: $metadata,
            renderedContent: $renderedContent,
            outputPath: $outputPath,
        );
    }

    public function testSanitizeCategoryName(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'sanitizeCategoryName');

        $this->assertEquals('tech', $method->invoke($this->service, 'Tech'));
        $this->assertEquals('web-development', $method->invoke($this->service, 'Web Development'));
        $this->assertEquals('c', $method->invoke($this->service, 'C#'));
        $this->assertEquals('category', $method->invoke($this->service, ''));
    }

    public function testExtractDescriptionFromMetadata(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'extractDescription');

        $metadata = ['description' => 'Metadata description'];
        $html = '<p>Content description</p>';

        $this->assertEquals('Metadata description', $method->invoke($this->service, $html, $metadata));
    }

    public function testExtractDescriptionFromContent(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'extractDescription');

        $metadata = [];
        $html = '<p>Content description</p>';

        $this->assertEquals('Content description', $method->invoke($this->service, $html, $metadata));
    }

    public function testExtractDescriptionTruncatesLongContent(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'extractDescription');

        $metadata = [];
        $longContent = str_repeat('word ', 50); // > 200 chars
        $html = "<p>$longContent</p>";

        $description = $method->invoke($this->service, $html, $metadata);

        $this->assertLessThanOrEqual(203, strlen($description)); // 200 + '...'
        $this->assertStringEndsWith('...', $description);
    }

    public function testGetFileDateFromPublishedDate(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'getFileDate');

        $metadata = ['published_date' => '2023-01-01'];
        $this->assertEquals('2023-01-01', $method->invoke($this->service, $metadata, ''));
    }

    public function testGetFileDateFromDate(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'getFileDate');

        $metadata = ['date' => '2023-02-01'];
        $this->assertEquals('2023-02-01', $method->invoke($this->service, $metadata, ''));
    }

    public function testGetFileUrl(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'getFileUrl');

        $outputDir = '/var/www/html/output';
        $outputPath = '/var/www/html/output/blog/post.html';

        $this->assertEquals('/blog/post.html', $method->invoke($this->service, $outputPath, $outputDir));
    }

    public function testCollectCategoryFilesSkipsWithoutCategory(): void
    {
        $event = $this->makeEvent(null, '', '', ['title' => 'No category']);

        $this->expectNotToPerformAssertions();
        $this->service->collectCategoryFiles($event);
    }

    public function testCollectCategoryFilesSkipsWithoutOutputOrFilePath(): void
    {
        $event = $this->makeEvent(null, '', '', ['category' => 'Tech', 'title' => 'Missing paths']);

        $this->expectNotToPerformAssertions();
        $this->service->collectCategoryFiles($event);
    }

    public function testCollectCategoryFilesCollectsValidFile(): void
    {
        $outputDir = sys_get_temp_dir() . '/staticforge_rss_unit_' . uniqid();
        mkdir($outputDir, 0755, true);
        $this->setContainerVariable('OUTPUT_DIR', $outputDir);

        $event = $this->makeEvent(
            $outputDir . '/tech/article1.html',
            '/source/article1.md',
            '<p>Some content</p>',
            ['category' => 'Tech', 'title' => 'Article 1'],
        );

        $this->expectNotToPerformAssertions();
        $this->service->collectCategoryFiles($event);

        $this->removeDirectory($outputDir);
    }

    public function testGenerateRssFeedsSkipsWhenNoCategoryFilesCollected(): void
    {
        $this->expectNotToPerformAssertions();
        $this->service->generateRssFeeds();
    }

    public function testGenerateRssFeedsThrowsWhenOutputDirMissing(): void
    {
        // Both collectCategoryFiles() and generateRssFeeds() guard on the same
        // OUTPUT_DIR variable, so proving generateRssFeeds()'s own guard fires
        // requires categoryFiles to already be populated by the time OUTPUT_DIR
        // goes missing. A mock container that returns a real dir on the first
        // read (during collection) and null afterward (during generation)
        // reproduces that sequencing without needing two container instances.
        $outputDir = sys_get_temp_dir() . '/staticforge_rss_unit_' . uniqid();
        mkdir($outputDir, 0755, true);

        $logger = $this->createMock(Log::class);
        $eventManager = $this->createMock(EventManager::class);
        $container = $this->createMock(\EICC\Utils\Container::class);
        $callCount = 0;
        $container->method('getVariable')->willReturnCallback(function (string $key) use (&$callCount, $outputDir) {
            if ($key !== 'OUTPUT_DIR') {
                return null;
            }
            $callCount++;
            return $callCount === 1 ? $outputDir : null;
        });

        $service = new RssFeedService($logger, $eventManager, new OutputWriter($container, $logger), $container);

        $event = $this->makeEvent(
            $outputDir . '/tech/article1.html',
            '/source/article1.md',
            '<p>Some content</p>',
            ['category' => 'Tech', 'title' => 'Article 1'],
        );
        $service->collectCategoryFiles($event);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OUTPUT_DIR not set in container');

        $service->generateRssFeeds();

        $this->removeDirectory($outputDir);
    }

    public function testGenerateRssFeedsThrowsWhenSiteBaseUrlMissing(): void
    {
        $outputDir = sys_get_temp_dir() . '/staticforge_rss_unit_' . uniqid();
        mkdir($outputDir, 0755, true);

        $logger = $this->createMock(Log::class);
        $eventManager = $this->createMock(EventManager::class);
        $container = new \EICC\Utils\Container();
        $container->setVariable('OUTPUT_DIR', $outputDir);
        $service = new RssFeedService($logger, $eventManager, new OutputWriter($container, $logger), $container);

        $event = $this->makeEvent(
            $outputDir . '/tech/article1.html',
            '/source/article1.md',
            '<p>Some content</p>',
            ['category' => 'Tech', 'title' => 'Article 1'],
        );
        $service->collectCategoryFiles($event);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SITE_BASE_URL not set in container');

        $service->generateRssFeeds();

        $this->removeDirectory($outputDir);
    }

    public function testGetFileDateFallsBackWhenDateIsUnparseable(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'getFileDate');

        $tempFile = sys_get_temp_dir() . '/staticforge_rss_unparseable_' . uniqid() . '.md';
        touch($tempFile);

        $metadata = ['date' => 'not-a-real-date'];
        $result = $method->invoke($this->service, $metadata, $tempFile);

        // An unparseable date must not silently become the 1970 epoch.
        $this->assertNotSame('not-a-real-date', $result);
        $this->assertNotFalse(strtotime($result));
        $this->assertNotSame('1970-01-01', $result);

        unlink($tempFile);
    }

    public function testGetFileDateFallsBackToTodayWhenDateUnparseableAndFileMissing(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'getFileDate');

        $metadata = ['published_date' => 'not-a-real-date'];
        $result = $method->invoke($this->service, $metadata, '/nonexistent/path/file.md');

        $this->assertSame(date('Y-m-d'), $result);
    }

    public function testAbsolutizeUrlsMakesRootRelativeSrcAndHrefAbsolute(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'absolutizeUrls');

        $html = '<img src="/assets/pic.png"><a href="/blog/post.html">link</a>';
        $result = $method->invoke($this->service, $html, 'https://example.com/');

        $this->assertSame(
            '<img src="https://example.com/assets/pic.png"><a href="https://example.com/blog/post.html">link</a>',
            $result
        );
    }

    public function testAbsolutizeUrlsHandlesSrcsetCandidatesAndDollarInBaseUrl(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'absolutizeUrls');

        $html = '<source srcset="/img/a-400.webp 400w, /img/a-800.webp 800w, //cdn.x/a.webp 1200w">';
        $result = $method->invoke($this->service, $html, 'https://example.com/$1/');

        $this->assertSame(
            '<source srcset="https://example.com/$1/img/a-400.webp 400w, '
            . 'https://example.com/$1/img/a-800.webp 800w, //cdn.x/a.webp 1200w">',
            $result
        );
    }

    public function testAbsolutizeUrlsLeavesProtocolRelativeUrlsUntouched(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'absolutizeUrls');

        $html = '<img src="//cdn.example.com/pic.png">';
        $result = $method->invoke($this->service, $html, 'https://example.com/');

        $this->assertSame('<img src="//cdn.example.com/pic.png">', $result);
    }

    public function testAbsolutizeUrlsLeavesFullyQualifiedUrlsUntouched(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'absolutizeUrls');

        $html = '<a href="https://other-site.com/page.html">link</a>';
        $result = $method->invoke($this->service, $html, 'https://example.com/');

        $this->assertSame('<a href="https://other-site.com/page.html">link</a>', $result);
    }

    public function testGenerateRssFeedsContentEncodedContainsOnlyMarkedContentNotTemplateNav(): void
    {
        $outputDir = sys_get_temp_dir() . '/staticforge_rss_unit_' . uniqid();
        mkdir($outputDir, 0755, true);

        $logger = $this->createMock(Log::class);
        $eventManager = $this->createMock(EventManager::class);
        $eventManager->method('fire')->willReturnArgument(1);
        $container = new \EICC\Utils\Container();
        $container->setVariable('OUTPUT_DIR', $outputDir);
        $service = new RssFeedService($logger, $eventManager, new OutputWriter($container, $logger), $container);

        $container->setVariable('SITE_BASE_URL', 'https://example.com');
        $container->setVariable('site_config', ['site' => ['name' => 'My Site']]);
        $container->setVariable('discovered_files', []);

        $fullPageHtml = '<html><body><nav>Home | About | Contact</nav>'
            . '<!--sf:content--><p>Only the article body.</p><!--/sf:content-->'
            . '<footer>Site footer</footer></body></html>';

        $event = $this->makeEvent(
            $outputDir . '/tech/article1.html',
            '/source/article1.md',
            $fullPageHtml,
            ['category' => 'Tech', 'title' => 'Article 1', 'date' => '2024-01-01'],
        );
        $service->collectCategoryFiles($event);
        $service->generateRssFeeds();

        $xml = file_get_contents($outputDir . '/tech/rss.xml');
        $this->assertNotFalse($xml);
        $this->assertStringContainsString('Only the article body.', $xml);
        $this->assertStringNotContainsString('Home | About | Contact', $xml);
        $this->assertStringNotContainsString('Site footer', $xml);

        $this->removeDirectory($outputDir);
    }

    public function testGenerateRssFeedsWritesRssFileForCategory(): void
    {
        $outputDir = sys_get_temp_dir() . '/staticforge_rss_unit_' . uniqid();
        mkdir($outputDir, 0755, true);

        $logger = $this->createMock(Log::class);
        $eventManager = $this->createMock(EventManager::class);
        $eventManager->method('fire')->willReturnArgument(1);
        $container = new \EICC\Utils\Container();
        $container->setVariable('OUTPUT_DIR', $outputDir);
        $service = new RssFeedService($logger, $eventManager, new OutputWriter($container, $logger), $container);

        $container->setVariable('SITE_BASE_URL', 'https://example.com');
        $container->setVariable('site_config', ['site' => ['name' => 'My Site']]);
        $container->setVariable('discovered_files', []);

        $event = $this->makeEvent(
            $outputDir . '/tech/article1.html',
            '/source/article1.md',
            '<p>Some content</p>',
            ['category' => 'Tech', 'title' => 'Article 1', 'date' => '2024-01-01'],
        );
        $service->collectCategoryFiles($event);

        $service->generateRssFeeds();

        $rssPath = $outputDir . '/tech/rss.xml';
        $this->assertFileExists($rssPath);

        $xml = file_get_contents($rssPath);
        $this->assertNotFalse($xml);
        $this->assertStringContainsString('My Site - Tech', $xml);
        $this->assertStringContainsString('Article 1', $xml);

        $this->removeDirectory($outputDir);
    }
}
