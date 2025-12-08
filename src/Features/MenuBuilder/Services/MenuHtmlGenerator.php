<?php

namespace EICC\StaticForge\Features\MenuBuilder\Services;

class MenuHtmlGenerator
{
    /**
     * @param array<int, array<int, array{title: string, url: string, file: string, position: string}>> $menuData
     * @return array<int, string>
     */
    public function buildMenuHtml(array $menuData): array
    {
        $menuHtml = [];

        foreach ($menuData as $menuNumber => $menuItems) {
            $menuHtml[$menuNumber] = $this->generateMenuHtml($menuItems, $menuNumber);
        }

        return $menuHtml;
    }

    /**
     * @param array<int|string, mixed> $menuItems
     */
    public function generateMenuHtml(array $menuItems, int $menuNumber = 0): string
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
