<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\MenuBuilder\Services;

use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\MenuBuilder\Services\MenuBuilderService;
use EICC\StaticForge\Features\MenuBuilder\Services\MenuHtmlGenerator;
use EICC\StaticForge\Features\MenuBuilder\Services\MenuScanner;
use EICC\StaticForge\Features\MenuBuilder\Services\MenuStructureBuilder;
use EICC\StaticForge\Features\MenuBuilder\Services\StaticMenuProcessor;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Container;
use EICC\Utils\Log;

class MenuBuilderServiceTest extends UnitTestCase
{
    private MenuBuilderService $service;
    private MenuScanner $menuScanner;
    private MenuHtmlGenerator $htmlGenerator;
    private StaticMenuProcessor $staticMenuProcessor;
    private MenuStructureBuilder $structureBuilder;
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->menuScanner = $this->createMock(MenuScanner::class);
        $this->htmlGenerator = $this->createMock(MenuHtmlGenerator::class);
        $this->staticMenuProcessor = $this->createMock(StaticMenuProcessor::class);
        $this->structureBuilder = $this->createMock(MenuStructureBuilder::class);
        $this->eventManager = $this->createMock(EventManager::class);

        $this->service = new MenuBuilderService(
            $this->menuScanner,
            $this->htmlGenerator,
            $this->staticMenuProcessor,
            $this->structureBuilder,
            $this->eventManager,
            $this->container->get('logger')
        );
    }

    public function testBuildMenus(): void
    {
        $discoveredFiles = ['file1' => ['path' => 'path/to/file1']];
        $menuData = [1 => []];
        $menuHtml = [1 => '<ul>...</ul>'];
        $sortedMenuData = [1 => []];

        $this->container->setVariable('discovered_files', $discoveredFiles);

        // Mock StaticMenuProcessor
        $this->staticMenuProcessor->expects($this->once())
            ->method('processStaticMenus')
            ->with($this->container);

        // Mock MenuScanner
        $this->menuScanner->expects($this->once())
            ->method('scanFilesForMenus')
            ->with($discoveredFiles)
            ->willReturn($menuData);

        // Mock EventManager
        $this->eventManager->expects($this->once())
            ->method('fire')
            ->with('COLLECT_MENU_ITEMS', ['menu_data' => $menuData])
            ->willReturn(['menu_data' => $menuData]);

        // Mock MenuHtmlGenerator
        $this->htmlGenerator->expects($this->once())
            ->method('buildMenuHtml')
            ->with($menuData)
            ->willReturn($menuHtml);

        // Mock MenuStructureBuilder
        $this->structureBuilder->expects($this->once())
            ->method('sortMenuData')
            ->with($menuData)
            ->willReturn($sortedMenuData);

        $parameters = [];
        $result = $this->service->buildMenus($this->container, $parameters);

        $this->assertArrayHasKey('features', $result);
        $this->assertArrayHasKey('MenuBuilder', $result['features']);
        $this->assertEquals($sortedMenuData, $result['features']['MenuBuilder']['files']);
        $this->assertEquals($menuHtml, $result['features']['MenuBuilder']['html']);

        // Verify container variables
        $this->assertEquals('<ul>...</ul>', $this->container->getVariable('menu1'));
    }
}
