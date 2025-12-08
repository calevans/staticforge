<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\MenuBuilder\Services;

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
     * Build menu structure from discovered files and static config
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function buildMenus(Container $container, array $parameters): array
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
        $eventResult = $this->eventManager->fire('COLLECT_MENU_ITEMS', ['menu_data' => $menuData]);
        $menuData = $eventResult['menu_data'] ?? $menuData;

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

        // Add to parameters for return to event system
        if (!isset($parameters['features'])) {
            $parameters['features'] = [];
        }

        $parameters['features']['MenuBuilder'] = [
            'files' => $sortedMenuData,
            'html' => $menuHtml
        ];

        return $parameters;
    }
}
