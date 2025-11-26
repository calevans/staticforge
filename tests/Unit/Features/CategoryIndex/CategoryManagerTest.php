<?php

namespace EICC\StaticForge\Tests\Unit\Features\CategoryIndex;

use EICC\StaticForge\Features\CategoryIndex\CategoryManager;
use EICC\StaticForge\Features\CategoryIndex\ImageProcessor;
use EICC\Utils\Log;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class CategoryManagerTest extends UnitTestCase
{
    private CategoryManager $manager;
    private Log $logger;
    private ImageProcessor $imageProcessor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(Log::class);
        $this->imageProcessor = $this->createMock(ImageProcessor::class);
        $this->manager = new CategoryManager($this->logger, $this->imageProcessor);
    }

    public function testScanCategoryFiles(): void
    {
        $discoveredFiles = [
            [
                'path' => '/path/to/tech.md',
                'metadata' => ['type' => 'category', 'title' => 'Technology']
            ],
            [
                'path' => '/path/to/other.md',
                'metadata' => ['title' => 'Other']
            ]
        ];

        $this->setContainerVariable('discovered_files', $discoveredFiles);

        $this->manager->scanCategoryFiles($this->container);
        $metadata = $this->manager->getCategoryMetadata();

        $this->assertArrayHasKey('tech', $metadata);
        $this->assertEquals('Technology', $metadata['tech']['title']);
        $this->assertArrayNotHasKey('other', $metadata);
    }

    public function testCollectCategoryFile(): void
    {
        $this->imageProcessor->method('extractHeroImageFromHtml')
            ->willReturn('/images/hero.jpg');

        $parameters = [
            'metadata' => ['title' => 'Post 1', 'category' => 'Tech'],
            'output_path' => '/output/tech/post1.html',
            'file_path' => '/source/post1.md',
            'rendered_content' => '<html>...</html>'
        ];

        $this->manager->collectCategoryFile($this->container, $parameters);

        $files = $this->manager->getCategoryFiles('tech');

        $this->assertNotEmpty($files['files']);
        $this->assertEquals('Post 1', $files['files'][0]['title']);
        $this->assertEquals('/tech/post1.html', $files['files'][0]['url']);
        $this->assertEquals('/images/hero.jpg', $files['files'][0]['image']);
    }

    public function testSanitizeCategoryName(): void
    {
        $this->assertEquals('tech-news', $this->manager->sanitizeCategoryName('Tech News!'));
        $this->assertEquals('c', $this->manager->sanitizeCategoryName('C++'));
        $this->assertEquals('php', $this->manager->sanitizeCategoryName('PHP'));
    }
}
