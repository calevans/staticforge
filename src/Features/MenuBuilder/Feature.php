<?php

namespace EICC\StaticForge\Features\MenuBuilder;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'MenuBuilder';
    private array $menuData = [];

    protected array $eventListeners = [
        'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 100]
    ];

    public function handlePostGlob(Container $container, array $parameters): array
    {
        // Scan files and build menu structure
        $menuData = $this->scanFilesForMenus();

        // Generate HTML from menu data
        $menuHtml = $this->buildMenuHtml($menuData);

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

    private function scanFilesForMenus(): array
    {
        $menuData = [];
        $discoveredFiles = $this->container->getVariable('discovered_files') ?? [];

        foreach ($discoveredFiles as $file) {
            $this->processFileForMenu($file, $menuData);
        }

        return $menuData;
    }

    private function processFileForMenu(string $filePath, array &$menuData): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);
        $metadata = $this->extractMetadataFromFile($content, $filePath);

        if (isset($metadata['menu'])) {
            $this->addMenuEntry($metadata['menu'], $filePath, $metadata['category'] ?? null, $menuData);
        }
    }

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

    private function extractMetadataFromMarkdown(string $content): array
    {
        // Extract YAML frontmatter
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
            return [];
        }

        $frontmatter = $matches[1];
        $metadata = [];

        // Look for menu: entry
        if (preg_match('/^menu:\s*(.+)$/m', $frontmatter, $menuMatches)) {
            $metadata['menu'] = trim($menuMatches[1]);
        }

        // Look for category: entry
        if (preg_match('/^category:\s*(.+)$/m', $frontmatter, $categoryMatches)) {
            $metadata['category'] = trim($categoryMatches[1]);
        }

        return $metadata;
    }

    private function extractMetadataFromHtml(string $content): array
    {
        // Look for <!-- INI ... --> block
        if (!preg_match('/<!--\s*INI\s*\n(.*?)\n-->/s', $content, $matches)) {
            return [];
        }

        $iniContent = $matches[1];
        $metadata = [];

        // Look for menu: entry
        if (preg_match('/^menu:\s*(.+)$/m', $iniContent, $menuMatches)) {
            $metadata['menu'] = trim($menuMatches[1]);
        }

        // Look for category: entry
        if (preg_match('/^category:\s*(.+)$/m', $iniContent, $categoryMatches)) {
            $metadata['category'] = trim($categoryMatches[1]);
        }

        return $metadata;
    }

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

    private function buildMenuHtml(array $menuData): array
    {
        $menuHtml = [];

        foreach ($menuData as $menuNumber => $menuItems) {
            $menuHtml[$menuNumber] = $this->generateMenuHtml($menuItems, $menuNumber);
        }

        return $menuHtml;
    }

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
            usort($allItems, function($a, $b) {
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

        // This is a dropdown structure
        if ($hasDropdownTitle && $hasSubmenuItems) {
            // This is a menu with submenus
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
        } else {
            // This is a simple menu with direct items
            foreach ($menuItems as $key => $item) {
                if (isset($item['title'])) {
                    $itemClass = $menuNumber > 0 ? "menu-{$menuNumber}" : "";
                    $liClass = $itemClass ? ' class="' . $itemClass . '"' : '';
                    $html .= '  <li' . $liClass . '><a href="' . htmlspecialchars($item['url']) . '">' .
                             htmlspecialchars($item['title']) . '</a></li>' . "\n";
                }
            }
        }

        $html .= '</ul>' . "\n";

        return $html;
    }
}
