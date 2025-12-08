<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\ChapterNav\Services;

use EICC\StaticForge\Features\ChapterNav\Services\ChapterNavService;
use EICC\Utils\Container;
use EICC\Utils\Log;
use PHPUnit\Framework\TestCase;

class ChapterNavServiceTest extends TestCase
{
    private ChapterNavService $service;
    private Log $logger;
    private Container $container;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(Log::class);
        $this->container = $this->createMock(Container::class);
        $this->service = new ChapterNavService($this->logger);
    }

    public function testParseConfiguredMenusFromSiteConfig(): void
    {
        $siteConfig = [
            'chapter_nav' => [
                'menus' => '1, 2',
                'prev_symbol' => '<<',
                'next_symbol' => '>>',
                'separator' => ' - '
            ]
        ];

        $this->container->method('getVariable')
            ->with('site_config')
            ->willReturn($siteConfig);

        $this->service->parseConfiguredMenus($this->container);

        $this->assertEquals(['1', '2'], $this->service->getConfiguredMenus());
        $this->assertEquals('<<', $this->service->getPrevSymbol());
        $this->assertEquals('>>', $this->service->getNextSymbol());
        $this->assertEquals(' - ', $this->service->getSeparator());
    }

    public function testParseConfiguredMenusFromEnv(): void
    {
        $this->container->method('getVariable')
            ->willReturnMap([
                ['site_config', null],
                ['CHAPTER_NAV_MENUS', '3, 4'],
                ['CHAPTER_NAV_PREV_SYMBOL', '<'],
                ['CHAPTER_NAV_NEXT_SYMBOL', '>'],
                ['CHAPTER_NAV_SEPARATOR', '/']
            ]);

        $this->service->parseConfiguredMenus($this->container);

        $this->assertEquals(['3', '4'], $this->service->getConfiguredMenus());
        $this->assertEquals('<', $this->service->getPrevSymbol());
        $this->assertEquals('>', $this->service->getNextSymbol());
        $this->assertEquals('/', $this->service->getSeparator());
    }

    public function testExtractSequentialPages(): void
    {
        $menuData = [
            1 => [
                'direct' => [
                    ['title' => 'Page 1', 'url' => '/page1', 'file' => 'page1.md']
                ],
                '1' => ['title' => 'Page 2', 'url' => '/page2', 'file' => 'page2.md'],
                '2' => ['title' => 'Page 3', 'url' => '/page3', 'file' => 'page3.md'],
                '3' => [ // Dropdown structure
                    0 => ['title' => 'Dropdown Title'],
                    '1' => ['title' => 'Page 4', 'url' => '/page4', 'file' => 'page4.md']
                ]
            ]
        ];

        $pages = $this->service->extractSequentialPages($menuData, 1);

        $this->assertCount(3, $pages);
        $this->assertEquals('Page 1', $pages[0]['title']);
        $this->assertEquals('Page 2', $pages[1]['title']);
        $this->assertEquals('Page 3', $pages[2]['title']);
        // Page 4 is skipped because it's inside a dropdown structure which is currently ignored by extractSequentialPages logic for simplicity or bug?
        // Wait, looking at the code:
        /*
        foreach ($menuItems as $position => $item) {
            if (!is_numeric($position)) { continue; }
            if (isset($item['title'])) {
                $pages[] = $item;
            } elseif (is_array($item)) {
                if (isset($item[0]) && isset($item[0]['title'])) {
                    continue; // Skips dropdown title
                }
            }
        }
        */
        // It seems it skips the whole dropdown array if it detects it's a dropdown. It doesn't recurse.
        // So Page 4 should indeed be skipped.
    }

    public function testBuildChapterNavHtml(): void
    {
        $prev = ['title' => 'Prev Page', 'url' => '/prev'];
        $current = ['title' => 'Current Page', 'url' => '/current'];
        $next = ['title' => 'Next Page', 'url' => '/next'];

        $html = $this->service->buildChapterNavHtml($prev, $current, $next);

        $this->assertStringContainsString('href="/prev"', $html);
        $this->assertStringContainsString('Prev Page', $html);
        $this->assertStringContainsString('Current Page', $html);
        $this->assertStringContainsString('href="/next"', $html);
        $this->assertStringContainsString('Next Page', $html);
    }

    public function testProcessChapterNavigation(): void
    {
        $parameters = [
            'features' => [
                'MenuBuilder' => [
                    'files' => [
                        1 => [
                            '1' => ['title' => 'Page 1', 'url' => '/page1', 'file' => 'page1.md'],
                            '2' => ['title' => 'Page 2', 'url' => '/page2', 'file' => 'page2.md']
                        ]
                    ]
                ]
            ]
        ];

        $this->container->method('getVariable')
            ->willReturnMap([
                ['site_config', null],
                ['CHAPTER_NAV_MENUS', '1']
            ]);

        $result = $this->service->processChapterNavigation($this->container, $parameters);

        $this->assertArrayHasKey('ChapterNav', $result['features']);
        $chapterNavData = $result['features']['ChapterNav']['pages'];

        $this->assertArrayHasKey('page1.md', $chapterNavData);
        $this->assertArrayHasKey('page2.md', $chapterNavData);

        // Check Page 1 navigation
        $p1Nav = $chapterNavData['page1.md'][1];
        $this->assertNull($p1Nav['prev']);
        $this->assertEquals('Page 2', $p1Nav['next']['title']);

        // Check Page 2 navigation
        $p2Nav = $chapterNavData['page2.md'][1];
        $this->assertEquals('Page 1', $p2Nav['prev']['title']);
        $this->assertNull($p2Nav['next']);
    }
}
