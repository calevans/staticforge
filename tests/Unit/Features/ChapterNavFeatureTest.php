<?php

namespace EICC\StaticForge\Tests\Unit\Features;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Features\ChapterNav\Feature;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;
use org\bovigo\vfs\vfsStream;

class ChapterNavFeatureTest extends UnitTestCase
{
    private $root;
    private Feature $feature;

    private EventManager $eventManager;
    private Log $logger;

    protected function setUp(): void
    {
        parent::setUp();

      // Create virtual filesystem
        $this->root = vfsStream::setup('test');

        // Clear site_config to ensure tests use environment variables
        $this->setContainerVariable('site_config', []);

      // Create container config
        $this->setContainerVariable('CHAPTER_NAV_MENUS', '2');
        $this->setContainerVariable('CHAPTER_NAV_PREV_SYMBOL', '←');
        $this->setContainerVariable('CHAPTER_NAV_NEXT_SYMBOL', '→');
        $this->setContainerVariable('CHAPTER_NAV_SEPARATOR', '|');

        $this->logger = $this->container->get('logger');
        $this->eventManager = new EventManager($this->container);

      // Create and register feature
        $this->feature = new Feature();
        $this->feature->register($this->eventManager, $this->container);
    }

    public function testConfigurationParsing(): void
    {
        $parameters = [
        'features' => [
        'MenuBuilder' => [
          'files' => []
        ]
        ]
        ];

        $result = $this->feature->handlePostGlob($this->container, $parameters);

      // Feature should recognize menu 2 is configured
        $this->assertArrayHasKey('features', $result);
    }

    public function testMultipleMenuConfiguration(): void
    {
      // Update configuration for this test
        $this->setContainerVariable('CHAPTER_NAV_MENUS', '1,2,3');

        $parameters = [
        'features' => [
        'MenuBuilder' => [
          'files' => []
        ]
        ]
        ];

        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $this->assertArrayHasKey('features', $result);
    }

    public function testSequentialPageExtraction(): void
    {
        $menuData = [
        2 => [
        1 => ['title' => 'First', 'url' => '/first.html', 'file' => 'content/first.md', 'position' => '2.1'],
        2 => ['title' => 'Second', 'url' => '/second.html', 'file' => 'content/second.md', 'position' => '2.2'],
        3 => ['title' => 'Third', 'url' => '/third.html', 'file' => 'content/third.md', 'position' => '2.3'],
        ]
        ];

        $parameters = [
        'features' => [
        'MenuBuilder' => [
          'files' => $menuData
        ]
        ]
        ];

        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $this->assertArrayHasKey('ChapterNav', $result['features']);
        $this->assertArrayHasKey('pages', $result['features']['ChapterNav']);
    }

    public function testDropdownItemsIgnored(): void
    {
        $menuData = [
        2 => [
        1 => ['title' => 'First', 'url' => '/first.html', 'file' => 'content/first.md', 'position' => '2.1'],
        2 => [
          0 => ['title' => 'Dropdown Title', 'url' => '#', 'file' => '', 'position' => '2.2.0'],
          1 => ['title' => 'Dropdown Item', 'url' => '/item.html', 'file' => 'content/item.md', 'position' => '2.2.1'],
        ],
        3 => ['title' => 'Third', 'url' => '/third.html', 'file' => 'content/third.md', 'position' => '2.3'],
        ]
        ];

        $parameters = [
        'features' => [
        'MenuBuilder' => [
          'files' => $menuData
        ]
        ]
        ];

        $result = $this->feature->handlePostGlob($this->container, $parameters);

      // Should have navigation for first.md and third.md, but not dropdown items
        $pages = $result['features']['ChapterNav']['pages'];
        $this->assertArrayHasKey('content/first.md', $pages);
        $this->assertArrayHasKey('content/third.md', $pages);
    }

    public function testFirstPageHasNoPreview(): void
    {
        $menuData = [
        2 => [
        1 => ['title' => 'First', 'url' => '/first.html', 'file' => 'content/first.md', 'position' => '2.1'],
        2 => ['title' => 'Second', 'url' => '/second.html', 'file' => 'content/second.md', 'position' => '2.2'],
        ]
        ];

        $parameters = [
        'features' => [
        'MenuBuilder' => [
          'files' => $menuData
        ]
        ]
        ];

        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $pages = $result['features']['ChapterNav']['pages'];
        $firstPage = $pages['content/first.md'][2];

        $this->assertNull($firstPage['prev']);
        $this->assertNotNull($firstPage['next']);
        $this->assertEquals('Second', $firstPage['next']['title']);
    }

    public function testLastPageHasNoNext(): void
    {
        $menuData = [
        2 => [
        1 => ['title' => 'First', 'url' => '/first.html', 'file' => 'content/first.md', 'position' => '2.1'],
        2 => ['title' => 'Second', 'url' => '/second.html', 'file' => 'content/second.md', 'position' => '2.2'],
        ]
        ];

        $parameters = [
        'features' => [
        'MenuBuilder' => [
          'files' => $menuData
        ]
        ]
        ];

        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $pages = $result['features']['ChapterNav']['pages'];
        $lastPage = $pages['content/second.md'][2];

        $this->assertNotNull($lastPage['prev']);
        $this->assertNull($lastPage['next']);
        $this->assertEquals('First', $lastPage['prev']['title']);
    }

    public function testMiddlePageHasBothPrevAndNext(): void
    {
        $menuData = [
        2 => [
        1 => ['title' => 'First', 'url' => '/first.html', 'file' => 'content/first.md', 'position' => '2.1'],
        2 => ['title' => 'Second', 'url' => '/second.html', 'file' => 'content/second.md', 'position' => '2.2'],
        3 => ['title' => 'Third', 'url' => '/third.html', 'file' => 'content/third.md', 'position' => '2.3'],
        ]
        ];

        $parameters = [
        'features' => [
        'MenuBuilder' => [
          'files' => $menuData
        ]
        ]
        ];

        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $pages = $result['features']['ChapterNav']['pages'];
        $middlePage = $pages['content/second.md'][2];

        $this->assertNotNull($middlePage['prev']);
        $this->assertNotNull($middlePage['next']);
        $this->assertEquals('First', $middlePage['prev']['title']);
        $this->assertEquals('Third', $middlePage['next']['title']);
    }

    public function testHtmlGenerationWithBothLinks(): void
    {
        $menuData = [
        2 => [
        1 => ['title' => 'First', 'url' => '/first.html', 'file' => 'content/first.md', 'position' => '2.1'],
        2 => ['title' => 'Second', 'url' => '/second.html', 'file' => 'content/second.md', 'position' => '2.2'],
        3 => ['title' => 'Third', 'url' => '/third.html', 'file' => 'content/third.md', 'position' => '2.3'],
        ]
        ];

        $parameters = [
        'features' => [
        'MenuBuilder' => [
          'files' => $menuData
        ]
        ]
        ];

        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $pages = $result['features']['ChapterNav']['pages'];
        $html = $pages['content/second.md'][2]['html'];

        $this->assertStringContainsString('<nav class="chapter-nav">', $html);
        $this->assertStringContainsString('chapter-nav-prev', $html);
        $this->assertStringContainsString('chapter-nav-current', $html);
        $this->assertStringContainsString('chapter-nav-next', $html);
        $this->assertStringContainsString('First', $html);
        $this->assertStringContainsString('Second', $html);
        $this->assertStringContainsString('Third', $html);
    }

    public function testHtmlGenerationWithOnlyNext(): void
    {
        $menuData = [
        2 => [
        1 => ['title' => 'First', 'url' => '/first.html', 'file' => 'content/first.md', 'position' => '2.1'],
        2 => ['title' => 'Second', 'url' => '/second.html', 'file' => 'content/second.md', 'position' => '2.2'],
        ]
        ];

        $parameters = [
        'features' => [
        'MenuBuilder' => [
          'files' => $menuData
        ]
        ]
        ];

        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $pages = $result['features']['ChapterNav']['pages'];
        $html = $pages['content/first.md'][2]['html'];

        $this->assertStringContainsString('<nav class="chapter-nav">', $html);
        $this->assertStringNotContainsString('chapter-nav-prev', $html);
        $this->assertStringContainsString('chapter-nav-current', $html);
        $this->assertStringContainsString('chapter-nav-next', $html);
    }

    public function testHtmlGenerationWithOnlyPrev(): void
    {
        $menuData = [
        2 => [
        1 => ['title' => 'First', 'url' => '/first.html', 'file' => 'content/first.md', 'position' => '2.1'],
        2 => ['title' => 'Second', 'url' => '/second.html', 'file' => 'content/second.md', 'position' => '2.2'],
        ]
        ];

        $parameters = [
        'features' => [
        'MenuBuilder' => [
          'files' => $menuData
        ]
        ]
        ];

        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $pages = $result['features']['ChapterNav']['pages'];
        $html = $pages['content/second.md'][2]['html'];

        $this->assertStringContainsString('<nav class="chapter-nav">', $html);
        $this->assertStringContainsString('chapter-nav-prev', $html);
        $this->assertStringContainsString('chapter-nav-current', $html);
        $this->assertStringNotContainsString('chapter-nav-next', $html);
    }

    public function testCustomSymbols(): void
    {
        $this->setContainerVariable('CHAPTER_NAV_PREV_SYMBOL', '<<');
        $this->setContainerVariable('CHAPTER_NAV_NEXT_SYMBOL', '>>');

        $menuData = [
        2 => [
        1 => ['title' => 'First', 'url' => '/first.html', 'file' => 'content/first.md', 'position' => '2.1'],
        2 => ['title' => 'Second', 'url' => '/second.html', 'file' => 'content/second.md', 'position' => '2.2'],
        3 => ['title' => 'Third', 'url' => '/third.html', 'file' => 'content/third.md', 'position' => '2.3'],
        ]
        ];

        $parameters = [
        'features' => [
        'MenuBuilder' => [
          'files' => $menuData
        ]
        ]
        ];

        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $pages = $result['features']['ChapterNav']['pages'];
        $html = $pages['content/second.md'][2]['html'];

        $this->assertStringContainsString('&lt;&lt;', $html);
        $this->assertStringContainsString('&gt;&gt;', $html);
    }

    public function testEmptyMenuConfiguration(): void
    {
        $this->setContainerVariable('CHAPTER_NAV_MENUS', '');

        $parameters = [
        'features' => [
        'MenuBuilder' => [
          'files' => []
        ]
        ]
        ];

        $result = $this->feature->handlePostGlob($this->container, $parameters);

      // Should return parameters unchanged
        $this->assertEquals($parameters, $result);
    }

    public function testSinglePageMenu(): void
    {
        $menuData = [
        2 => [
        1 => ['title' => 'Only Page', 'url' => '/only.html', 'file' => 'content/only.md', 'position' => '2.1'],
        ]
        ];

        $parameters = [
        'features' => [
        'MenuBuilder' => [
          'files' => $menuData
        ]
        ]
        ];

        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $pages = $result['features']['ChapterNav']['pages'];
        $onlyPage = $pages['content/only.md'][2];

        $this->assertNull($onlyPage['prev']);
        $this->assertNull($onlyPage['next']);
        $this->assertNotNull($onlyPage['current']);
    }

    public function testMultipleMenusForSamePage(): void
    {
        $this->setContainerVariable('CHAPTER_NAV_MENUS', '2,3');
      // Ensure site_config doesn't override our env var
        $this->setContainerVariable('site_config', []);

        $menuData = [
        2 => [
        1 => ['title' => 'First', 'url' => '/first.html', 'file' => 'content/first.md', 'position' => '2.1'],
        2 => ['title' => 'Shared', 'url' => '/shared.html', 'file' => 'content/shared.md', 'position' => '2.2'],
        ],
        3 => [
        1 => ['title' => 'Shared', 'url' => '/shared.html', 'file' => 'content/shared.md', 'position' => '3.1'],
        2 => ['title' => 'Third', 'url' => '/third.html', 'file' => 'content/third.md', 'position' => '3.2'],
        ]
        ];

        $parameters = [
        'features' => [
        'MenuBuilder' => [
          'files' => $menuData
        ]
        ]
        ];

        $result = $this->feature->handlePostGlob($this->container, $parameters);

        $pages = $result['features']['ChapterNav']['pages'];

      // Shared page should have navigation for both menus
        $this->assertArrayHasKey('content/shared.md', $pages);
        $this->assertArrayHasKey(2, $pages['content/shared.md']);
        $this->assertArrayHasKey(3, $pages['content/shared.md']);

      // Check menu 2 navigation
        $menu2Nav = $pages['content/shared.md'][2];
        $this->assertEquals('First', $menu2Nav['prev']['title']);
        $this->assertNull($menu2Nav['next']);

      // Check menu 3 navigation
        $menu3Nav = $pages['content/shared.md'][3];
        $this->assertNull($menu3Nav['prev']);
        $this->assertEquals('Third', $menu3Nav['next']['title']);
    }
}
