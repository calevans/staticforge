<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\MenuBuilder\Services;

use EICC\StaticForge\Core\Events\CollectMenuItemsEvent;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;

class MenuBuilderService
{
    private MenuScanner $menuScanner;
    private MenuHtmlGenerator $htmlGenerator;
    private StaticMenuProcessor $staticMenuProcessor;
    private MenuStructureBuilder $structureBuilder;
    private EventManager $eventManager;
    private Log $logger;

    public function __construct(
        MenuScanner $menuScanner,
        MenuHtmlGenerator $htmlGenerator,
        StaticMenuProcessor $staticMenuProcessor,
        MenuStructureBuilder $structureBuilder,
        EventManager $eventManager,
        Log $logger
    ) {
        $this->menuScanner = $menuScanner;
        $this->htmlGenerator = $htmlGenerator;
        $this->staticMenuProcessor = $staticMenuProcessor;
        $this->structureBuilder = $structureBuilder;
        $this->eventManager = $eventManager;
        $this->logger = $logger;
    }

    /**
     * Build menu structure from discovered files and static config. Writes
     * directly to $container (menu{N} template variables, plus the
     * 'features' entry templates read as features.MenuBuilder.html.{N} /
     * features.MenuBuilder.files[{N}]) since typed events no longer carry
     * an array that EventManager centrally merges back into the container.
     */
    public function buildMenus(Container $container): void
    {
        // Process static menus from siteconfig.yaml first
        $this->staticMenuProcessor->processStaticMenus($container);

        // Scan files and build menu structure
        $discoveredFiles = $container->getVariable('discovered_files') ?? [];
        $menuData = $this->menuScanner->scanFilesForMenus($discoveredFiles);

        $this->logger->log(
            'INFO',
            'MenuBuilder: Found ' . count($menuData)
            . ' menus with data: '
            . json_encode(array_keys($menuData))
        );

        // Allow other features to inject menu items
        $collectEvent = new CollectMenuItemsEvent('COLLECT_MENU_ITEMS', $menuData);
        $this->eventManager->fire('COLLECT_MENU_ITEMS', $collectEvent);
        $menuData = $collectEvent->menuData;

        // Generate HTML from menu data
        $menuHtml = $this->htmlGenerator->buildMenuHtml($menuData);

        // Store each menu in the container for template access
        foreach ($menuHtml as $menuNumber => $html) {
            $varName = "menu{$menuNumber}";
            if ($container->hasVariable($varName)) {
                $container->updateVariable($varName, $html);
            } else {
                $container->setVariable($varName, $html);
            }
        }

        // Sort menu data by position for template iteration
        $sortedMenuData = $this->structureBuilder->sortMenuData($menuData);

        $features = $container->getVariable('features') ?? [];
        $features['MenuBuilder'] = [
            'files' => $sortedMenuData,
            'html' => $menuHtml
        ];

        if ($container->hasVariable('features')) {
            $container->updateVariable('features', $features);
        } else {
            $container->setVariable('features', $features);
        }
    }
}
