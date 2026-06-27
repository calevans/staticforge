<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\RssFeed;

use EICC\StaticForge\Features\RssFeed\Services\RssFeedService;
use EICC\StaticForge\Core\EventManager;
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
        $this->service = new RssFeedService($logger, $this->eventManager);
    }

    public function testSanitizeCategoryName(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'sanitizeCategoryName');
        $method->setAccessible(true);

        $this->assertEquals('tech', $method->invoke($this->service, 'Tech'));
        $this->assertEquals('web-development', $method->invoke($this->service, 'Web Development'));
        $this->assertEquals('c', $method->invoke($this->service, 'C#'));
        $this->assertEquals('category', $method->invoke($this->service, ''));
    }

    public function testExtractDescriptionFromMetadata(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'extractDescription');
        $method->setAccessible(true);

        $metadata = ['description' => 'Metadata description'];
        $html = '<p>Content description</p>';

        $this->assertEquals('Metadata description', $method->invoke($this->service, $html, $metadata));
    }

    public function testExtractDescriptionFromContent(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'extractDescription');
        $method->setAccessible(true);

        $metadata = [];
        $html = '<p>Content description</p>';

        $this->assertEquals('Content description', $method->invoke($this->service, $html, $metadata));
    }

    public function testExtractDescriptionTruncatesLongContent(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'extractDescription');
        $method->setAccessible(true);

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
        $method->setAccessible(true);

        $metadata = ['published_date' => '2023-01-01'];
        $this->assertEquals('2023-01-01', $method->invoke($this->service, $metadata, ''));
    }

    public function testGetFileDateFromDate(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'getFileDate');
        $method->setAccessible(true);

        $metadata = ['date' => '2023-02-01'];
        $this->assertEquals('2023-02-01', $method->invoke($this->service, $metadata, ''));
    }

    public function testGetFileUrl(): void
    {
        $method = new ReflectionMethod(RssFeedService::class, 'getFileUrl');
        $method->setAccessible(true);

        $outputDir = '/var/www/html/output';
        $outputPath = '/var/www/html/output/blog/post.html';

        $this->assertEquals('/blog/post.html', $method->invoke($this->service, $outputPath, $outputDir));
    }

    public function testCollectCategoryFilesSkipsWithoutCategory(): void
    {
        $parameters = ['metadata' => ['title' => 'No category']];

        $result = $this->service->collectCategoryFiles($this->container, $parameters);

        $this->assertEquals($parameters, $result);
    }

    public function testCollectCategoryFilesSkipsWithoutOutputOrFilePath(): void
    {
        $parameters = ['metadata' => ['category' => 'Tech', 'title' => 'Missing paths']];

        $result = $this->service->collectCategoryFiles($this->container, $parameters);

        $this->assertEquals($parameters, $result);
    }

    public function testCollectCategoryFilesCollectsValidFile(): void
    {
        $outputDir = sys_get_temp_dir() . '/staticforge_rss_unit_' . uniqid();
        mkdir($outputDir, 0755, true);
        $this->setContainerVariable('OUTPUT_DIR', $outputDir);

        $parameters = [
            'metadata' => ['category' => 'Tech', 'title' => 'Article 1'],
            'output_path' => $outputDir . '/tech/article1.html',
            'file_path' => '/source/article1.md',
            'rendered_content' => '<p>Some content</p>'
        ];

        $result = $this->service->collectCategoryFiles($this->container, $parameters);

        $this->assertEquals($parameters, $result);

        $this->removeDirectory($outputDir);
    }

    public function testGenerateRssFeedsSkipsWhenNoCategoryFilesCollected(): void
    {
        $result = $this->service->generateRssFeeds($this->container, []);

        $this->assertEquals([], $result);
    }

    public function testGenerateRssFeedsThrowsWhenOutputDirMissing(): void
    {
        $logger = $this->createMock(Log::class);
        $eventManager = $this->createMock(EventManager::class);
        $service = new RssFeedService($logger, $eventManager);

        $container = new \EICC\Utils\Container();

        $parameters = [
            'metadata' => ['category' => 'Tech', 'title' => 'Article 1'],
            'output_path' => '/tmp/does-not-matter/tech/article1.html',
            'file_path' => '/source/article1.md',
            'rendered_content' => '<p>Some content</p>'
        ];

        // collectCategoryFiles bails out silently because OUTPUT_DIR isn't set,
        // so nothing is collected and generateRssFeeds short-circuits before
        // reaching the OUTPUT_DIR check. Set OUTPUT_DIR only for collection,
        // then verify the explicit guard in generateRssFeeds with a container
        // that never had OUTPUT_DIR set but has collected files via a second
        // service instance that did have it set.
        $collectingContainer = new \EICC\Utils\Container();
        $outputDir = sys_get_temp_dir() . '/staticforge_rss_unit_' . uniqid();
        mkdir($outputDir, 0755, true);
        $collectingContainer->setVariable('OUTPUT_DIR', $outputDir);
        $parameters['output_path'] = $outputDir . '/tech/article1.html';
        $service->collectCategoryFiles($collectingContainer, $parameters);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OUTPUT_DIR not set in container');

        // generateRssFeeds is called with a container missing OUTPUT_DIR;
        // the service already has categoryFiles collected internally.
        $service->generateRssFeeds($container, []);

        $this->removeDirectory($outputDir);
    }

    public function testGenerateRssFeedsThrowsWhenSiteBaseUrlMissing(): void
    {
        $outputDir = sys_get_temp_dir() . '/staticforge_rss_unit_' . uniqid();
        mkdir($outputDir, 0755, true);

        $logger = $this->createMock(Log::class);
        $eventManager = $this->createMock(EventManager::class);
        $service = new RssFeedService($logger, $eventManager);

        $container = new \EICC\Utils\Container();
        $container->setVariable('OUTPUT_DIR', $outputDir);

        $parameters = [
            'metadata' => ['category' => 'Tech', 'title' => 'Article 1'],
            'output_path' => $outputDir . '/tech/article1.html',
            'file_path' => '/source/article1.md',
            'rendered_content' => '<p>Some content</p>'
        ];
        $service->collectCategoryFiles($container, $parameters);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SITE_BASE_URL not set in container');

        $service->generateRssFeeds($container, []);

        $this->removeDirectory($outputDir);
    }

    public function testGenerateRssFeedsWritesRssFileForCategory(): void
    {
        $outputDir = sys_get_temp_dir() . '/staticforge_rss_unit_' . uniqid();
        mkdir($outputDir, 0755, true);

        $logger = $this->createMock(Log::class);
        $eventManager = $this->createMock(EventManager::class);
        $eventManager->method('fire')->willReturnArgument(1);
        $service = new RssFeedService($logger, $eventManager);

        $container = new \EICC\Utils\Container();
        $container->setVariable('OUTPUT_DIR', $outputDir);
        $container->setVariable('SITE_BASE_URL', 'https://example.com');
        $container->setVariable('site_config', ['site' => ['name' => 'My Site']]);
        $container->setVariable('discovered_files', []);

        $parameters = [
            'metadata' => ['category' => 'Tech', 'title' => 'Article 1', 'date' => '2024-01-01'],
            'output_path' => $outputDir . '/tech/article1.html',
            'file_path' => '/source/article1.md',
            'rendered_content' => '<p>Some content</p>'
        ];
        $service->collectCategoryFiles($container, $parameters);

        $service->generateRssFeeds($container, []);

        $rssPath = $outputDir . '/tech/rss.xml';
        $this->assertFileExists($rssPath);

        $xml = file_get_contents($rssPath);
        $this->assertNotFalse($xml);
        $this->assertStringContainsString('My Site - Tech', $xml);
        $this->assertStringContainsString('Article 1', $xml);

        $this->removeDirectory($outputDir);
    }
}
