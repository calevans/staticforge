<?php

namespace EICC\StaticForge\Tests\Unit\Features\CategoryIndex\Services;

use EICC\StaticForge\Features\CategoryIndex\Services\CategoryService;
use EICC\StaticForge\Features\CategoryIndex\Services\ImageService;
use EICC\Utils\Log;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class CategoryServiceTest extends UnitTestCase
{
    private CategoryService $service;
    private Log $logger;
    private ImageService $imageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(Log::class);
        $this->imageService = $this->createMock(ImageService::class);
        $this->service = new CategoryService($this->logger, $this->imageService);
    }

    public function testScanCategories(): void
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

        $this->service->scanCategories($this->container);
        $categories = $this->service->getCategories();

        $this->assertArrayHasKey('tech', $categories);
        $this->assertEquals('Technology', $categories['tech']->title);
        $this->assertArrayNotHasKey('other', $categories);
    }

    public function testCollectFile(): void
    {
        $this->imageService->method('extractHeroImage')
            ->willReturn('/images/hero.jpg');

        $this->setContainerVariable('OUTPUT_DIR', '/output');

        $parameters = [
            'metadata' => ['title' => 'Post 1', 'category' => 'Tech'],
            'output_path' => '/output/tech/post1.html',
            'file_path' => '/source/post1.md',
            'rendered_content' => '<html>...</html>'
        ];

        $this->service->collectFile($this->container, $parameters);

        $category = $this->service->getCategory('tech');

        $this->assertNotNull($category);
        $this->assertNotEmpty($category->files);
        $this->assertEquals('Post 1', $category->files[0]->title);
        // The URL logic in service is simplified to /slug/filename
        // In test input output_path is /output/tech/post1.html, basename is post1.html
        // Service logic: '/' . $slug . '/' . basename($outputPath) -> /tech/post1.html
        $this->assertEquals('/tech/post1.html', $category->files[0]->url);
        $this->assertEquals('/images/hero.jpg', $category->files[0]->image);
    }
}
