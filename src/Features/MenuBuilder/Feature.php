<?php

namespace EICC\StaticForge\Features\MenuBuilder;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'MenuBuilder';
    private Log $logger;
    private MenuScanner $menuScanner;
    private MenuHtmlGenerator $htmlGenerator;
    private StaticMenuProcessor $staticMenuProcessor;
    private MenuStructureBuilder $structureBuilder;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 100]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);

        // Get logger from container
        $this->logger = $container->get('logger');

        // Register services
        $this->structureBuilder = new MenuStructureBuilder();
        $this->htmlGenerator = new MenuHtmlGenerator();
        $this->menuScanner = new MenuScanner($this->structureBuilder);
        $this->staticMenuProcessor = new StaticMenuProcessor($this->htmlGenerator, $this->logger);

        // Register in container for potential external use/testing
        $container->add(MenuStructureBuilder::class, $this->structureBuilder);
        $container->add(MenuHtmlGenerator::class, $this->htmlGenerator);
        $container->add(MenuScanner::class, $this->menuScanner);
        $container->add(StaticMenuProcessor::class, $this->staticMenuProcessor);

        $this->logger->log('INFO', 'MenuBuilder Feature registered');
    }

    /**
     * Handle POST_GLOB event - build menu structure from discovered files
     *
     * Called dynamically by EventManager when POST_GLOB event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostGlob(Container $container, array $parameters): array
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

        $parameters['features'][$this->getName()] = [
            'files' => $sortedMenuData,
            'html' => $menuHtml
        ];

        return $parameters;
    }








}
