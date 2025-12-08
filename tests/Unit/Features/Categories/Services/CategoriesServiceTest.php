<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Categories\Services;

use EICC\StaticForge\Features\Categories\Services\CategoriesService;
use EICC\Utils\Container;
use EICC\Utils\Log;
use PHPUnit\Framework\TestCase;

class CategoriesServiceTest extends TestCase
{
    private CategoriesService $service;
    private Log $logger;
    private Container $container;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(Log::class);
        $this->container = $this->createMock(Container::class);
        $this->service = new CategoriesService($this->logger);
    }

    public function testSanitizeCategoryName(): void
    {
        $this->assertEquals('tech', $this->service->sanitizeCategoryName('Tech'));
        $this->assertEquals('web-dev', $this->service->sanitizeCategoryName('Web Dev'));
        // The current implementation strips special chars, so C# becomes c
        $this->assertEquals('c', $this->service->sanitizeCategoryName('C#'));
        $this->assertEquals('multiple-spaces', $this->service->sanitizeCategoryName('Multiple   Spaces'));
    }

    public function testCategorizeOutputPath(): void
    {
        // Normal case
        $this->assertEquals(
            '/var/www/public/tech/index.html',
            $this->service->categorizeOutputPath('/var/www/public/index.html', 'Tech')
        );

        // Already in path (prevention of double nesting)
        $this->assertEquals(
            '/var/www/public/tech/index.html',
            $this->service->categorizeOutputPath('/var/www/public/tech/index.html', 'Tech')
        );
    }

    public function testProcessCategoryTemplates(): void
    {
        $discoveredFiles = [
            // Category definition
            [
                'path' => 'content/categories/tech.md',
                'metadata' => [
                    'type' => 'category',
                    'template' => 'tech-layout'
                ]
            ],
            // File in category
            [
                'path' => 'content/posts/article1.md',
                'metadata' => [
                    'category' => 'Tech',
                    'template' => 'base' // Should be overridden
                ]
            ],
            // File in category with explicit template
            [
                'path' => 'content/posts/article2.md',
                'metadata' => [
                    'category' => 'Tech',
                    'template' => 'custom' // Should NOT be overridden
                ]
            ],
            // File without category
            [
                'path' => 'content/about.md',
                'metadata' => [
                    'template' => 'base'
                ]
            ]
        ];

        $this->container->method('getVariable')
            ->with('discovered_files')
            ->willReturn($discoveredFiles);

        $this->container->method('hasVariable')
            ->with('category_templates')
            ->willReturn(false);

        // Expect updateVariable to be called for files
        $this->container->expects($this->once())
            ->method('updateVariable')
            ->with('discovered_files', $this->anything());

        // Expect setVariable to be called for templates (since hasVariable is false)
        $this->container->expects($this->once())
            ->method('setVariable')
            ->with('category_templates', $this->anything());

        $this->service->processCategoryTemplates($this->container);
    }
}
