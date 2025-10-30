<?php

namespace EICC\StaticForge\Features\ChapterNav;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'ChapterNav';
    protected array $configuredMenus = [];
    protected string $prevSymbol = '←';
    protected string $nextSymbol = '→';
    protected string $separator = '|';
    private array $chapterNavData = [];
    private $logger;

    protected array $eventListeners = [
    'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 150]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);
        $this->logger = $container->getVariable('logger');
    }

    public function handlePostGlob(Container $container, array $parameters): array
    {
      // Read configuration from environment
        $this->parseConfiguredMenus();

        if (empty($this->configuredMenus)) {
            return $parameters;
        }

      // Get menu data from MenuBuilder
        $menuBuilderData = $parameters['features']['MenuBuilder'] ?? [];
        $menuFiles = $menuBuilderData['files'] ?? [];

        if (empty($menuFiles)) {
            return $parameters;
        }

      // Process each configured menu
        foreach ($this->configuredMenus as $menuNumber) {
            $sequentialPages = $this->extractSequentialPages($menuFiles, $menuNumber);

            for ($i = 0; $i < count($sequentialPages); $i++) {
                $current = $sequentialPages[$i];
                $prev = $i > 0 ? $sequentialPages[$i - 1] : null;
                $next = $i < count($sequentialPages) - 1 ? $sequentialPages[$i + 1] : null;

              // Store navigation data keyed by source file
                if (!isset($this->chapterNavData[$current['file']])) {
                    $this->chapterNavData[$current['file']] = [];
                }

                $this->chapterNavData[$current['file']][$menuNumber] = [
                'prev' => $prev,
                'current' => $current,
                'next' => $next,
                'html' => $this->buildChapterNavHtml($prev, $current, $next)
                ];
            }
        }

        $this->logger->log('INFO', sprintf(
            'ChapterNav: Built navigation for %d files across %d menus',
            count($this->chapterNavData),
            count($this->configuredMenus)
        ));

      // Add to parameters for template access
        if (!isset($parameters['features'])) {
            $parameters['features'] = [];
        }

        $parameters['features'][$this->getName()] = [
        'pages' => $this->chapterNavData
        ];

        return $parameters;
    }

  /**
   * Parse configured menus from environment
   */
    protected function parseConfiguredMenus(): void
    {
        $menusConfig = $this->container->getVariable('CHAPTER_NAV_MENUS') ?? '';
        $this->configuredMenus = array_filter(array_map('trim', explode(',', $menusConfig)));

      // Read symbols from environment
        $this->prevSymbol = $this->container->getVariable('CHAPTER_NAV_PREV_SYMBOL') ?? '←';
        $this->nextSymbol = $this->container->getVariable('CHAPTER_NAV_NEXT_SYMBOL') ?? '→';
        $this->separator = $this->container->getVariable('CHAPTER_NAV_SEPARATOR') ?? '|';
    }

  /**
   * Extract sequential pages from menu data, ignoring dropdown items
   */
    protected function extractSequentialPages(array $menuData, int $menuNumber): array
    {
        if (!isset($menuData[$menuNumber])) {
            return [];
        }

        $menuItems = $menuData[$menuNumber];
        $pages = [];

      // Handle 'direct' items (menu = X format with no specific position)
        if (isset($menuItems['direct'])) {
            foreach ($menuItems['direct'] as $item) {
                $pages[] = $item;
            }
            unset($menuItems['direct']);
        }

      // Process positioned items (menu = X.Y format)
      // Ignore third-level positions (menu = X.Y.Z are dropdown items)
        ksort($menuItems);

        foreach ($menuItems as $position => $item) {
            if (!is_numeric($position)) {
                continue;
            }

          // Check if this is a single item or a dropdown structure
            if (isset($item['title'])) {
              // Single item - add it
                $pages[] = $item;
            } elseif (is_array($item)) {
              // Might be a dropdown structure, check for position 0 (dropdown title)
                if (isset($item[0]) && isset($item[0]['title'])) {
                  // This is a dropdown title, skip it (we don't navigate to dropdown titles)
                    continue;
                }
            }
        }

        return $pages;
    }

  /**
   * Build HTML for chapter navigation
   */
    protected function buildChapterNavHtml(?array $prev, array $current, ?array $next): string
    {
        $html = '<nav class="chapter-nav">' . "\n";

      // Previous link
        if ($prev !== null) {
            $html .= '  <a href="' . htmlspecialchars($prev['url']) . '" class="chapter-nav-prev">';
            $html .= htmlspecialchars($this->prevSymbol) . ' ' . htmlspecialchars($prev['title']);
            $html .= '</a>' . "\n";
        }

      // Current page (not a link)
        $html .= '  <span class="chapter-nav-current">' . htmlspecialchars($current['title']) . '</span>' . "\n";

      // Next link
        if ($next !== null) {
            $html .= '  <a href="' . htmlspecialchars($next['url']) . '" class="chapter-nav-next">';
            $html .= htmlspecialchars($next['title']) . ' ' . htmlspecialchars($this->nextSymbol);
            $html .= '</a>' . "\n";
        }

        $html .= '</nav>' . "\n";

        return $html;
    }
}
