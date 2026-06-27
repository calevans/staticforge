<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\MenuBuilder\Services;

use EICC\StaticForge\Features\MenuBuilder\Services\MenuHtmlGenerator;
use PHPUnit\Framework\TestCase;

class MenuHtmlGeneratorTest extends TestCase
{
    private MenuHtmlGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new MenuHtmlGenerator();
    }

    public function testGenerateMenuHtmlEmptyReturnsEmptyList(): void
    {
        $html = $this->generator->generateMenuHtml([]);

        $this->assertEquals("<ul class=\"menu\">\n</ul>\n", $html);
    }

    public function testGenerateMenuHtmlDirectItems(): void
    {
        $menuItems = [
            'direct' => [
                ['title' => 'Home', 'url' => '/'],
                ['title' => 'About', 'url' => '/about/'],
            ],
        ];

        $html = $this->generator->generateMenuHtml($menuItems);

        $this->assertStringContainsString('<a href="/">Home</a>', $html);
        $this->assertStringContainsString('<a href="/about/">About</a>', $html);
    }

    public function testGenerateMenuHtmlEscapesSpecialCharacters(): void
    {
        $menuItems = [
            'direct' => [
                ['title' => '<script>alert(1)</script>', 'url' => '/x?a=1&b=2'],
            ],
        ];

        $html = $this->generator->generateMenuHtml($menuItems);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&amp;b=2', $html);
    }

    public function testGenerateMenuHtmlLegacyDropdownStructure(): void
    {
        $menuItems = [
            0 => ['title' => 'Products'],
            1 => ['title' => 'Widgets', 'url' => '/widgets/'],
            2 => ['title' => 'Gadgets', 'url' => '/gadgets/'],
        ];

        $html = $this->generator->generateMenuHtml($menuItems, 1);

        $this->assertStringContainsString('dropdown-title', $html);
        $this->assertStringContainsString('Products', $html);
        $this->assertStringContainsString('<a href="/widgets/">Widgets</a>', $html);
        $this->assertStringContainsString('<a href="/gadgets/">Gadgets</a>', $html);
    }

    public function testGenerateMenuHtmlNestedChildren(): void
    {
        $menuItems = [
            1 => [
                'title' => 'Parent',
                'url' => '/parent/',
                0 => ['title' => 'Child', 'url' => '/parent/child/'],
            ],
        ];

        $html = $this->generator->generateMenuHtml($menuItems);

        $this->assertStringContainsString('has-children', $html);
        $this->assertStringContainsString('<a href="/parent/">Parent</a>', $html);
        $this->assertStringContainsString('<a href="/parent/child/">Child</a>', $html);
    }

    public function testGenerateMenuHtmlSkipsItemWithoutTitle(): void
    {
        $menuItems = [
            'direct' => [
                ['url' => '/no-title/'],
            ],
        ];

        $html = $this->generator->generateMenuHtml($menuItems);

        $this->assertStringNotContainsString('<a href="/no-title/">', $html);
    }

    public function testGenerateMenuHtmlUsesMenuNumberInClasses(): void
    {
        $menuItems = [
            'direct' => [
                ['title' => 'Item', 'url' => '/item/'],
            ],
        ];

        $html = $this->generator->generateMenuHtml($menuItems, 2);

        $this->assertStringContainsString('class="menu menu-2"', $html);
    }

    public function testBuildMenuHtmlBuildsMultipleMenus(): void
    {
        $menuData = [
            1 => ['direct' => [['title' => 'A', 'url' => '/a/']]],
            2 => ['direct' => [['title' => 'B', 'url' => '/b/']]],
        ];

        $result = $this->generator->buildMenuHtml($menuData);

        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertStringContainsString('A', $result[1]);
        $this->assertStringContainsString('B', $result[2]);
    }
}
