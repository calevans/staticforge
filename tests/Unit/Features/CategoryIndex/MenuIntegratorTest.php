<?php

namespace EICC\StaticForge\Tests\Unit\Features\CategoryIndex;

use EICC\StaticForge\Features\CategoryIndex\MenuIntegrator;
use EICC\Utils\Log;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class MenuIntegratorTest extends UnitTestCase
{
    private MenuIntegrator $integrator;
    private Log $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(Log::class);
        $this->integrator = new MenuIntegrator($this->logger);
    }

    public function testAddCategoryToMenuTopLevel(): void
    {
        $menuData = [];
        $this->integrator->addCategoryToMenu('1', 'tech', 'Technology', $menuData);

        $this->assertArrayHasKey(1, $menuData);
        $this->assertArrayHasKey('direct', $menuData[1]);
        $this->assertEquals('Technology', $menuData[1]['direct'][0]['title']);
        $this->assertEquals('/tech/', $menuData[1]['direct'][0]['url']);
    }

    public function testAddCategoryToMenuSecondLevel(): void
    {
        $menuData = [];
        $this->integrator->addCategoryToMenu('1.2', 'tech', 'Technology', $menuData);

        $this->assertArrayHasKey(1, $menuData);
        $this->assertArrayHasKey(2, $menuData[1]);
        $this->assertEquals('Technology', $menuData[1][2]['title']);
    }

    public function testAddCategoryToMenuThirdLevel(): void
    {
        $menuData = [];
        $this->integrator->addCategoryToMenu('1.2.3', 'tech', 'Technology', $menuData);

        $this->assertArrayHasKey(1, $menuData);
        $this->assertArrayHasKey(2, $menuData[1]);
        $this->assertArrayHasKey(3, $menuData[1][2]);
        $this->assertEquals('Technology', $menuData[1][2][3]['title']);
    }

    public function testRebuildMenuHtmlSimple(): void
    {
        $menuData = [
            1 => [
                'direct' => [
                    ['title' => 'Home', 'url' => '/']
                ]
            ]
        ];

        $html = $this->integrator->rebuildMenuHtml($menuData);

        $this->assertArrayHasKey(1, $html);
        $this->assertStringContainsString('<ul class="menu menu-1">', $html[1]);
        $this->assertStringContainsString('<a href="/">Home</a>', $html[1]);
    }

    public function testRebuildMenuHtmlDropdown(): void
    {
        $menuData = [
            1 => [
                0 => ['title' => 'Main Menu'], // Dropdown title
                1 => ['title' => 'Link 1', 'url' => '/link1'],
                2 => ['title' => 'Link 2', 'url' => '/link2']
            ]
        ];

        $html = $this->integrator->rebuildMenuHtml($menuData);

        $this->assertArrayHasKey(1, $html);
        $this->assertStringContainsString('class="dropdown"', $html[1]);
        $this->assertStringContainsString('Main Menu', $html[1]);
        $this->assertStringContainsString('Link 1', $html[1]);
        $this->assertStringContainsString('Link 2', $html[1]);
    }
}
