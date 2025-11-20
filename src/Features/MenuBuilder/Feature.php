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
        $this->processStaticMenus($container);

        // Scan files and build menu structure
        $menuData = $this->scanFilesForMenus();

        $this->logger->log('INFO', 'MenuBuilder: Found ' . count($menuData) . ' menus with data: ' . json_encode(array_keys($menuData)));

        // Generate HTML from menu data
        $menuHtml = $this->buildMenuHtml($menuData);

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
        $sortedMenuData = $this->sortMenuData($menuData);

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

    /**
     * @return array<int, array<int, array{title: string, url: string, file: string, position: string}>>
     */
    private function scanFilesForMenus(): array
    {
        $menuData = [];
        $discoveredFiles = $this->container->getVariable('discovered_files') ?? [];

        foreach ($discoveredFiles as $fileData) {
            $this->processFileForMenu($fileData, $menuData);
        }

        return $menuData;
    }

    /**
     * Process a single file to extract menu entries
     *
     * @param array{path: string, url: string, metadata: array<string, mixed>} $fileData File data from discovery
     * @param array<int, array<int, array{title: string, url: string, file: string, position: string}>>
     *        $menuData Menu data structure passed by reference
     */
    private function processFileForMenu(array $fileData, array &$menuData): void
    {
        $metadata = $fileData['metadata'];

        if (isset($metadata['menu'])) {
            $menuPositions = $this->parseMenuValue($metadata['menu']);

            foreach ($menuPositions as $position) {
                $this->addMenuEntry($position, $fileData, $metadata['category'] ?? null, $menuData);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @deprecated Metadata now extracted in FileDiscovery during discovery phase
     * @return array<string, mixed>
     */
    private function extractMetadataFromFile(string $content, string $filePath): array
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        if ($extension === 'md') {
            return $this->extractMetadataFromMarkdown($content);
        } elseif ($extension === 'html') {
            return $this->extractMetadataFromHtml($content);
        }

        return [];
    }

    /**
     * @deprecated Metadata now extracted in FileDiscovery during discovery phase
     * @return array<string, mixed>
     */
    private function extractMetadataFromMarkdown(string $content): array
    {
        // Extract INI frontmatter
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
            return [];
        }

        $frontmatter = $matches[1];
        $metadata = [];

        // Look for menu: or menu = entry (support both : and = syntax)
        if (preg_match('/^menu\s*[=:]\s*(.+)$/m', $frontmatter, $menuMatches)) {
            $metadata['menu'] = trim($menuMatches[1]);
        }

        // Look for category: or category = entry
        if (preg_match('/^category\s*[=:]\s*(.+)$/m', $frontmatter, $categoryMatches)) {
            $metadata['category'] = trim(trim($categoryMatches[1]), '"\'');
        }

        return $metadata;
    }

    /**
     * @deprecated Metadata now extracted in FileDiscovery during discovery phase
     * @return array<string, mixed>
     */
    private function extractMetadataFromHtml(string $content): array
    {
        // Look for <!-- INI ... --> block
        if (!preg_match('/<!--\s*INI\s*\n(.*?)\n-->/s', $content, $matches)) {
            return [];
        }

        $iniContent = $matches[1];
        $metadata = [];

        // Look for menu = or menu: entry (support both = and : syntax)
        if (preg_match('/^menu\s*[=:]\s*(.+)$/m', $iniContent, $menuMatches)) {
            $metadata['menu'] = trim($menuMatches[1]);
        }

        // Look for category = or category: entry
        if (preg_match('/^category\s*[=:]\s*(.+)$/m', $iniContent, $categoryMatches)) {
            $metadata['category'] = trim(trim($categoryMatches[1]), '"\'');
        }

        return $metadata;
    }

    /**
     * Parse menu value into array of positions
     * Supports: "1.2", "1.2, 2.3", "[1.2, 2.3]", etc.
     *
     * @return array<int, string>
     */
    private function parseMenuValue(string $rawValue): array
    {
        // Strip brackets and trim
        $rawValue = trim(trim($rawValue), '[]');

        // Empty value? Return empty array
        if (empty($rawValue)) {
            return [];
        }

        // Split on commas
        $items = explode(',', $rawValue);

        // Clean and filter each item (trim whitespace and quotes)
        $items = array_filter(array_map(function ($item) {
            return trim(trim($item), '"\'');
        }, $items));

        // Re-index array (in case filtering removed items)
        return array_values($items);
    }

    /**
     * Add a menu entry to the menu data structure
     *
     * @param string $menuPosition Position string (e.g., "1", "1.2", "1.2.3")
     * @param array{path: string, url: string, metadata: array<string, mixed>} $fileData File data from discovery
     * @param string|null $category Optional category for URL generation
     * @param array<int, array<int, array{title: string, url: string, file: string, position: string}>>
     *        $menuData Menu data array passed by reference
     */
    private function addMenuEntry(string $menuPosition, array $fileData, ?string $category, array &$menuData): void
    {
        // Parse menu position (e.g., "1", "1.2", "1.2.3")
        $parts = explode('.', $menuPosition);

        // Only support up to 3 levels
        if (count($parts) > 3) {
            return;
        }

        // Get title from metadata or extract from file
        $title = $fileData['metadata']['title'] ?? $this->extractTitleFromFile($fileData['path']);

        // Use pre-generated URL from discovery
        $url = $fileData['url'];

        $menuEntry = [
            'title' => $title,
            'url' => $url,
            'file' => $fileData['path'],
            'position' => $menuPosition
        ];

        // Store in nested array structure based on position parts
        $menu = (int)$parts[0];

        if (!isset($menuData[$menu])) {
            $menuData[$menu] = [];
        }

        if (count($parts) === 1) {
            // Top level menu item - store with 'direct' key to distinguish from dropdown items
            // Don't use numeric keys as 0 is reserved for dropdown titles
            if (!isset($menuData[$menu]['direct'])) {
                $menuData[$menu]['direct'] = [];
            }
            // Use string keys to avoid conflict with position 0
            $menuData[$menu]['direct'][] = $menuEntry;
        } elseif (count($parts) === 2) {
            // Second level menu item (menu: X.Y)
            $position = (int)$parts[1];

            if (!isset($menuData[$menu][$position])) {
                $menuData[$menu][$position] = [];
            }

            // Preserve any existing child items (3-level menu items like X.Y.Z)
            // If there are already numeric keys (children), keep them
            $existingChildren = [];
            if (is_array($menuData[$menu][$position])) {
                foreach ($menuData[$menu][$position] as $key => $value) {
                    if (is_numeric($key)) {
                        $existingChildren[$key] = $value;
                    }
                }
            }

            // Store this item with its metadata
            $menuData[$menu][$position] = $menuEntry;

            // Restore any children
            foreach ($existingChildren as $key => $value) {
                $menuData[$menu][$position][$key] = $value;
            }
        } elseif (count($parts) === 3) {
            // Third level menu item
            $subMenu = (int)$parts[1];
            $position = (int)$parts[2];

            if (!isset($menuData[$menu][$subMenu])) {
                $menuData[$menu][$subMenu] = [];
            }

            // If parent item doesn't have title/url/file/position keys, it's a container
            // Otherwise we need to preserve the parent item data
            if (is_array($menuData[$menu][$subMenu]) && !isset($menuData[$menu][$subMenu]['title'])) {
                // Already a container, just add child
                $menuData[$menu][$subMenu][$position] = $menuEntry;
            } else {
                // Parent item exists as a single entry, keep it and add child
                $parentData = $menuData[$menu][$subMenu];
                $menuData[$menu][$subMenu] = $parentData;
                $menuData[$menu][$subMenu][$position] = $menuEntry;
            }
        }
    }

    private function extractTitleFromFile(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return ucfirst(pathinfo($filePath, PATHINFO_FILENAME));
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        if ($extension === 'md') {
            // Try to get title from frontmatter
            if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
                $frontmatter = $matches[1];
                if (preg_match('/^title:\s*(.+)$/m', $frontmatter, $titleMatches)) {
                    return trim($titleMatches[1]);
                }
            }

            // Fall back to first H1
            if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
                return trim($matches[1]);
            }
        } elseif ($extension === 'html') {
            // Try to get title from INI block
            if (preg_match('/<!--\s*INI\s*\n(.*?)\n-->/s', $content, $matches)) {
                $iniContent = $matches[1];
                if (preg_match('/^title:\s*(.+)$/m', $iniContent, $titleMatches)) {
                    return trim($titleMatches[1]);
                }
            }

            // Fall back to <title> tag or first <h1>
            if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $content, $matches)) {
                return trim($matches[1]);
            }
            if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $content, $matches)) {
                return trim(strip_tags($matches[1]));
            }
        }

        // Final fallback to filename
        return ucfirst(pathinfo($filePath, PATHINFO_FILENAME));
    }

    private function generateUrlFromPath(string $filePath, ?string $category = null): string
    {
        // Get just the filename (flatten subdirectories from content/)
        $filename = basename($filePath);
        $filename = str_replace(['.md', '.html'], ['.html', '.html'], $filename);

        // If there's a category, add it as a subdirectory
        if ($category) {
            $categorySlug = strtolower(str_replace([' ', '_'], '-', $category));
            $url = '/' . $categorySlug . '/' . $filename;
        } else {
            $url = '/' . $filename;
        }

        // Clean up the URL
        $url = preg_replace('/\/index\.html$/', '/', $url);

        return $url;
    }

    /**
     * @param array<int, array<int, array{title: string, url: string, file: string, position: string}>> $menuData
     * @return array<int, string>
     */
    private function buildMenuHtml(array $menuData): array
    {
        $menuHtml = [];

        foreach ($menuData as $menuNumber => $menuItems) {
            $menuHtml[$menuNumber] = $this->generateMenuHtml($menuItems, $menuNumber);
        }

        return $menuHtml;
    }

    /**
     * @param array<int, array{title: string, url: string, file: string, position: string}> $menuItems
     */
    private function generateMenuHtml(array $menuItems, int $menuNumber = 0): string
    {
        $menuClass = $menuNumber > 0 ? "menu menu-{$menuNumber}" : "menu";
        $html = '<ul class="' . $menuClass . '">' . "\n";

        // Collect all direct items and positioned items at this level
        $allItems = [];

        // Get direct items (menu: X format) - these have no explicit position
        if (isset($menuItems['direct'])) {
            foreach ($menuItems['direct'] as $item) {
                $allItems[] = [
                    'item' => $item,
                    'position' => 999, // Put at end if no other positioning
                    'type' => 'direct'
                ];
            }
            unset($menuItems['direct']);
        }

        // Check for positioned single items (menu: X.Y where we want them as direct items, not dropdown)
        // These should be treated as regular menu items with explicit positions
        ksort($menuItems);

        // Check if this is a dropdown structure (has 0 key with submenu items)
        $hasDropdownTitle = isset($menuItems[0]);
        $hasSubmenuItems = false;
        foreach ($menuItems as $key => $item) {
            if (is_numeric($key) && $key > 0) {
                $hasSubmenuItems = true;
                break;
            }
        }

        // If we have positioned items that aren't a dropdown structure, treat them as direct items
        if (!$hasDropdownTitle || !$hasSubmenuItems) {
            // These are direct positioned items (like menu: 1.1)
            foreach ($menuItems as $key => $item) {
                if (is_numeric($key)) {
                    // Item is now stored directly, not in array
                    $allItems[] = [
                        'item' => $item,
                        'position' => $key,
                        'type' => 'positioned'
                    ];
                }
            }

            // Sort all items by position using version comparison
            /** @phpstan-ignore-next-line argument.unresolvableType (usort callback types are resolvable) */
            usort($allItems, function (array $a, array $b): int {
                return $this->compareMenuPositions($a['position'], $b['position']);
            });

            // Render all items
            foreach ($allItems as $entry) {
                $item = $entry['item'];
                $itemClass = $menuNumber > 0 ? "menu-{$menuNumber}" : "";
                $liClass = $itemClass ? ' class="' . $itemClass . '"' : '';
                $html .= '  <li' . $liClass . '><a href="' . htmlspecialchars($item['url']) . '">' .
                         htmlspecialchars($item['title']) . '</a></li>' . "\n";
            }

            $html .= '</ul>' . "\n";
            return $html;
        }

        // At this point, both $hasDropdownTitle and $hasSubmenuItems must be true
        // (otherwise we would have returned above)

        // This is a dropdown structure - a menu with submenus
        $dropdownTitle = 'Menu';
        $submenuItems = [];
        $submenuPosition = 0;

        foreach ($menuItems as $key => $item) {
            if ($key === 0 && isset($item['title'])) {
                // This is the dropdown title (position x.0)
                $dropdownTitle = $item['title'];
                $submenuPosition = $key;
            } elseif ($key > 0 && isset($item['title'])) {
                // These are submenu items (position x.1, x.2, etc.)
                $submenuItems[$key] = $item;
            }
        }

            $dropdownClass = $menuNumber > 0 ? "dropdown menu-{$menuNumber}-{$submenuPosition}" : "dropdown";
            $html .= '  <li class="' . $dropdownClass . '">' . "\n";
            $html .= '    <span class="dropdown-title">' . htmlspecialchars($dropdownTitle) . '</span>' . "\n";

            $submenuClass = $menuNumber > 0 ? "dropdown-menu menu-{$menuNumber}-submenu" : "dropdown-menu";
            $html .= '    <ul class="' . $submenuClass . '">' . "\n";

        foreach ($submenuItems as $position => $submenuItem) {
            $itemClass = $menuNumber > 0 ? "menu-{$menuNumber}-{$position}" : "";
            $liClass = $itemClass ? ' class="' . $itemClass . '"' : '';
            $html .= '      <li' . $liClass . '><a href="' . htmlspecialchars($submenuItem['url']) . '">' .
                     htmlspecialchars($submenuItem['title']) . '</a></li>' . "\n";
        }

            $html .= '    </ul>' . "\n";
            $html .= '  </li>' . "\n";

        $html .= '</ul>' . "\n";

        return $html;
    }

    /**
     * Sort menu data by position for proper ordering in templates
     *
     * @param array<int, array<int|string, mixed>> $menuData
     * @return array<int, array<int|string, mixed>>
     */
    private function sortMenuData(array $menuData): array
    {
        $sorted = [];

        foreach ($menuData as $menuNumber => $menuItems) {
            if (!isset($sorted[$menuNumber])) {
                $sorted[$menuNumber] = [];
            }

            // Separate direct items from positioned items
            $direct = [];
            $positioned = [];

            foreach ($menuItems as $key => $item) {
                if ($key === 'direct') {
                    $direct = $item;
                } elseif (is_numeric($key)) {
                    $positioned[(int)$key] = $item;
                }
            }

            // Sort positioned items by key
            ksort($positioned);

            // Add direct items first, then positioned items
            if (!empty($direct)) {
                $sorted[$menuNumber]['direct'] = $direct;
            }

            foreach ($positioned as $key => $item) {
                $sorted[$menuNumber][$key] = $item;
            }
        }

        return $sorted;
    }

    /**
     * Process static menus from siteconfig.yaml
     *
     * Reads menu definitions from site configuration and generates HTML
     * for named menus (e.g., 'top', 'footer'). These are stored in the
     * container as menu_{name} variables.
     */
    private function processStaticMenus(Container $container): void
    {
        $siteConfig = $container->getVariable('site_config');

        // Check if we have menu configuration
        if (!is_array($siteConfig) || !isset($siteConfig['menu']) || !is_array($siteConfig['menu'])) {
            return;
        }

        $menus = $siteConfig['menu'];

        foreach ($menus as $menuName => $menuItems) {
            if (!is_array($menuItems)) {
                $this->logger->log('WARNING', "Menu '{$menuName}' in siteconfig.yaml is not an array, skipping");
                continue;
            }

            // Convert simple key/value pairs to menu item structure
            // Using 'direct' key format expected by generateMenuHtml()
            $items = ['direct' => []];
            foreach ($menuItems as $title => $url) {
                $items['direct'][] = [
                    'title' => (string)$title,
                    // Strip leading slash for relative URL compatibility
                    'url' => ltrim((string)$url, '/'),
                    'file' => '', // Static menu items have no associated file
                    'position' => '' // Position is determined by YAML order
                ];
            }

            // Generate HTML using existing menu HTML generator
            $html = $this->generateMenuHtml($items, 0);

            // Store in container as menu_{name}
            $varName = "menu_{$menuName}";
            if ($container->hasVariable($varName)) {
                $container->updateVariable($varName, $html);
            } else {
                $container->setVariable($varName, $html);
            }

            $this->logger->log('INFO', "MenuBuilder: Generated static menu '{$menuName}' with " . count($items['direct']) . ' items');
        }
    }

    /**
     * Compare two menu position strings using version-style comparison
     *
     * This ensures that "1.2" comes before "1.10" (not lexicographic sorting)
     *
     * @param string|int $a First position (e.g., "1.2", "1.10", 999)
     * @param string|int $b Second position (e.g., "1.3", "1.2")
     * @return int -1 if a < b, 0 if equal, 1 if a > b
     */
    private function compareMenuPositions($a, $b): int
    {
        // Handle numeric positions (used for items without explicit position)
        if (is_numeric($a) && is_numeric($b)) {
            return $a <=> $b;
        }

        // Convert to string if needed
        $a = (string)$a;
        $b = (string)$b;

        // If either is empty, handle edge case
        if ($a === '' && $b === '') {
            return 0;
        }
        if ($a === '') {
            return -1;
        }
        if ($b === '') {
            return 1;
        }

        // Split by dots and compare each part numerically
        $aParts = explode('.', $a);
        $bParts = explode('.', $b);

        $maxParts = max(count($aParts), count($bParts));

        for ($i = 0; $i < $maxParts; $i++) {
            $aVal = isset($aParts[$i]) ? (int)$aParts[$i] : 0;
            $bVal = isset($bParts[$i]) ? (int)$bParts[$i] : 0;

            if ($aVal !== $bVal) {
                return $aVal <=> $bVal;
            }
        }

        return 0;
    }
}
