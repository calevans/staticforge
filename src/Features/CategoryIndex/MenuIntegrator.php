<?php

namespace EICC\StaticForge\Features\CategoryIndex;

use EICC\Utils\Log;

class MenuIntegrator
{
    private Log $logger;

    public function __construct(Log $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Add a category to the menu data structure
     *
     * @param string $menuPosition Menu position (e.g., "1.2")
     * @param string $categorySlug Category URL slug
     * @param string $title Display title
     * @param array<int, mixed> $menuData Menu structure passed by reference
     */
    public function addCategoryToMenu(
        string $menuPosition,
        string $categorySlug,
        string $title,
        array &$menuData
    ): void {
        // Parse menu position
        $parts = explode('.', $menuPosition);

        if (count($parts) > 3) {
            return; // Only support up to 3 levels
        }

        // Generate URL for category index
        $url = '/' . $categorySlug . '/';

        $menuEntry = [
            'title' => $title,
            'url' => $url,
            'file' => 'category:' . $categorySlug,
            'position' => $menuPosition
        ];

        $menu = (int)$parts[0];

        if (!isset($menuData[$menu])) {
            $menuData[$menu] = [];
        }

        if (count($parts) === 1) {
            // Top level menu item
            if (!isset($menuData[$menu]['direct'])) {
                $menuData[$menu]['direct'] = [];
            }
            $menuData[$menu]['direct'][] = $menuEntry;
        } elseif (count($parts) === 2) {
            // Second level menu item
            $position = (int)$parts[1];
            $menuData[$menu][$position] = $menuEntry;
        } elseif (count($parts) === 3) {
            // Third level menu item
            $level2 = (int)$parts[1];
            $level3 = (int)$parts[2];
            if (!isset($menuData[$menu][$level2])) {
                $menuData[$menu][$level2] = [];
            }
            $menuData[$menu][$level2][$level3] = $menuEntry;
        }

        $this->logger->log('INFO', "Added category '{$title}' to menu at position {$menuPosition}");
    }

    /**
     * Rebuild menu HTML from menu data (borrowed from MenuBuilder logic)
     *
     * @param array<int, mixed> $menuData Menu data structure
     * @return array<int, string> Generated HTML for each menu
     */
    public function rebuildMenuHtml(array $menuData): array
    {
        $menuHtml = [];

        foreach ($menuData as $menuNumber => $menuItems) {
            if (empty($menuItems)) {
                continue;
            }

            $html = $this->generateMenuHtml($menuNumber, $menuItems);
            $menuHtml[$menuNumber] = $html;
        }

        return $menuHtml;
    }

    /**
     * Generate HTML for a single menu (simplified version of MenuBuilder logic)
     *
     * @param int $menuNumber Menu number identifier
     * @param array<int|string, mixed> $menuItems Menu items data structure
     * @return string Generated HTML
     */
    private function generateMenuHtml(int $menuNumber, array $menuItems): string
    {
        $menuClass = $menuNumber > 0 ? "menu menu-{$menuNumber}" : "menu";
        $html = '<ul class="' . $menuClass . '">' . "\n";

        // Check if this is a dropdown menu (has numeric keys other than 'direct')
        $hasDropdown = false;
        foreach (array_keys($menuItems) as $key) {
            if (is_int($key) && $key === 0) {
                $hasDropdown = true;
                break;
            }
        }

        if ($hasDropdown) {
            // This is a dropdown menu - render with submenu structure
            $dropdownTitle = $menuItems[0]['title'] ?? 'Menu';
            $html .= '  <li class="dropdown">' . "\n";
            $html .= '    <span class="dropdown-title">' . htmlspecialchars($dropdownTitle) . '</span>' . "\n";
            $html .= '    <ul class="dropdown-menu">' . "\n";

            foreach ($menuItems as $position => $item) {
                if ($position === 0) {
                    continue; // Skip title
                }

                // Only render items that have both title and url
                if (isset($item['title'], $item['url'])) {
                    $itemClass = $menuNumber > 0 ? "menu-{$menuNumber}-{$position}" : "";
                    $liClass = $itemClass ? ' class="' . $itemClass . '"' : '';
                    $html .= '      <li' . $liClass . '><a href="' . htmlspecialchars($item['url']) . '">' .
                        htmlspecialchars($item['title']) . '</a></li>' . "\n";
                }
            }

            $html .= '    </ul>' . "\n";
            $html .= '  </li>' . "\n";
        } else {
            // Simple menu - render direct items and positioned items together
            $allItems = [];

            // Get direct items (menu: X)
            if (isset($menuItems['direct'])) {
                foreach ($menuItems['direct'] as $item) {
                    $allItems[999 + count($allItems)] = $item; // Use high numbers to sort after positioned
                }
            }

            // Get positioned items (menu: X.Y)
            foreach ($menuItems as $position => $item) {
                if ($position !== 'direct' && is_int($position)) {
                    $allItems[$position] = $item;
                }
            }

            // Sort by position
            ksort($allItems);

            // Render items
            foreach ($allItems as $item) {
                if (isset($item['title'], $item['url'])) {
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
