<?php

namespace EICC\StaticForge\Features\MenuBuilder;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'MenuBuilder';

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 100]
    ];

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
        // Scan files and build menu structure
        $menuData = $this->scanFilesForMenus();

        // Generate HTML from menu data
        $menuHtml = $this->buildMenuHtml($menuData);

        // Store each menu in the container for template access
        foreach ($menuHtml as $menuNumber => $html) {
            $container->setVariable("menu{$menuNumber}", $html);
        }

        // Add to parameters for return to event system
        if (!isset($parameters['features'])) {
            $parameters['features'] = [];
        }

        $parameters['features'][$this->getName()] = [
            'files' => $menuData,
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

        foreach ($discoveredFiles as $file) {
            $this->processFileForMenu($file, $menuData);
        }

        return $menuData;
    }

    /**
     * Process a single file to extract menu entries
     *
     * @param string $filePath Path to the file to process
     * @param array<int, array<int, array{title: string, url: string, file: string, position: string}>> $menuData Menu data structure passed by reference
     * @param-out array<int, mixed> $menuData Modified menu structure with nested arrays
     */
    private function processFileForMenu(string $filePath, array &$menuData): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return;
        }

        $metadata = $this->extractMetadataFromFile($content, $filePath);

        if (isset($metadata['menu'])) {
            $menuPositions = $this->parseMenuValue($metadata['menu']);

            foreach ($menuPositions as $position) {
                $this->addMenuEntry($position, $filePath, $metadata['category'] ?? null, $menuData);
            }
        }
    }

    /**
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
     * @return array<string, mixed>
     */
    private function extractMetadataFromMarkdown(string $content): array
    {
        // Extract YAML/INI frontmatter
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
            return [];
        }

        $frontmatter = $matches[1];
        $metadata = [];

        // Look for menu: or menu = entry (support both YAML and INI format)
        if (preg_match('/^menu\s*[=:]\s*(.+)$/m', $frontmatter, $menuMatches)) {
            $metadata['menu'] = trim($menuMatches[1]);
        }

        // Look for category: or category = entry
        if (preg_match('/^category\s*[=:]\s*(.+)$/m', $frontmatter, $categoryMatches)) {
            $metadata['category'] = trim($categoryMatches[1]);
        }

        return $metadata;
    }

    /**
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

        // Look for menu = or menu: entry (support both INI and YAML format)
        if (preg_match('/^menu\s*[=:]\s*(.+)$/m', $iniContent, $menuMatches)) {
            $metadata['menu'] = trim($menuMatches[1]);
        }

        // Look for category = or category: entry
        if (preg_match('/^category\s*[=:]\s*(.+)$/m', $iniContent, $categoryMatches)) {
            $metadata['category'] = trim($categoryMatches[1]);
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
     * @param string $filePath Path to the content file
     * @param string|null $category Optional category for URL generation
     * @param array<int, array<int, array{title: string, url: string, file: string, position: string}>> $menuData Menu data array passed by reference
     * @param-out array<int, mixed> $menuData Modified menu structure with complex nested arrays
     */
    private function addMenuEntry(string $menuPosition, string $filePath, ?string $category, array &$menuData): void
    {
        // Parse menu position (e.g., "1", "1.2", "1.2.3")
        $parts = explode('.', $menuPosition);

        // Only support up to 3 levels
        if (count($parts) > 3) {
            return;
        }

        // Get file metadata
        $title = $this->extractTitleFromFile($filePath);
        $url = $this->generateUrlFromPath($filePath, $category);

        $menuEntry = [
            'title' => $title,
            'url' => $url,
            'file' => $filePath,
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

            // Store as single item (not array of items) since positions are explicit
            // This avoids using [] which could create index 0
            $menuData[$menu][$position] = $menuEntry;
        } elseif (count($parts) === 3) {
            // Third level menu item
            $subMenu = (int)$parts[1];
            $position = (int)$parts[2];

            if (!isset($menuData[$menu][$subMenu])) {
                $menuData[$menu][$subMenu] = [];
            }

            $menuData[$menu][$subMenu][$position] = $menuEntry;
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
            // Try to get title from YAML frontmatter
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

            // Sort all items by position
            /** @phpstan-ignore-next-line argument.unresolvableType (usort callback types are resolvable) */
            usort($allItems, function (array $a, array $b): int {
                return $a['position'] <=> $b['position'];
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
}
