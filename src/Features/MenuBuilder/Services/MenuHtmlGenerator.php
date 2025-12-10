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

        // 1. Handle 'direct' items (Top level items without position)
        if (isset($menuItems['direct'])) {
            foreach ($menuItems['direct'] as $item) {
                $html .= $this->renderItem($item, $menuNumber);
            }
            unset($menuItems['direct']);
        }

        // Sort by key (position)
        ksort($menuItems);

        // 2. Check for Legacy Dropdown (Key 0 exists)
        // This preserves the specific markup for "Dropdowns" (menu: X.0)
        if (isset($menuItems[0])) {
            $dropdownTitle = $menuItems[0]['title'] ?? 'Menu';
            $submenuPosition = 0;

            $dropdownClass = $menuNumber > 0 ? "dropdown menu-{$menuNumber}-{$submenuPosition}" : "dropdown";
            $html .= '  <li class="' . $dropdownClass . '">' . "\n";
            $html .= '    <span class="dropdown-title">' . htmlspecialchars($dropdownTitle) . '</span>' . "\n";

            $submenuClass = $menuNumber > 0 ? "dropdown-menu menu-{$menuNumber}-submenu" : "dropdown-menu";
            $html .= '    <ul class="' . $submenuClass . '">' . "\n";

            foreach ($menuItems as $key => $item) {
                if ($key === 0) continue; // Skip title
                // Render children as simple links
                $itemClass = $menuNumber > 0 ? "menu-{$menuNumber}-{$key}" : "";
                $liClass = $itemClass ? ' class="' . $itemClass . '"' : '';
                $html .= '      <li' . $liClass . '><a href="' . htmlspecialchars($item['url']) . '">' .
                         htmlspecialchars($item['title']) . '</a></li>' . "\n";
            }

            $html .= '    </ul>' . "\n";
            $html .= '  </li>' . "\n";
        } else {
            // 3. Standard/Recursive Logic (No Key 0)
            // This handles flat lists AND nested trees (menu: X.Y -> X.Y.Z)
            foreach ($menuItems as $key => $item) {
                if (is_numeric($key)) {
                    $html .= $this->renderItem($item, $menuNumber, $key);
                }
            }
        }

        $html .= '</ul>' . "\n";
        return $html;
    }

    /**
     * Recursively render a menu item and its children
     *
     * @param array<string, mixed> $item
     * @param int $menuNumber
     * @param int|string|null $position
     * @return string
     */
    private function renderItem(array $item, int $menuNumber, $position = null): string
    {
        // Check for children (numeric keys inside the item array)
        $children = [];
        foreach ($item as $k => $v) {
            if (is_numeric($k) && is_array($v)) {
                $children[$k] = $v;
            }
        }
        ksort($children);

        $hasChildren = !empty($children);

        // Build classes
        $classes = [];
        if ($menuNumber > 0 && $position !== null) {
            $classes[] = "menu-{$menuNumber}-{$position}";
        }
        if ($hasChildren) {
            $classes[] = "has-children";
        }

        $liClass = !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '';

        $html = '  <li' . $liClass . '>';

        // Render Link
        $title = $item['title'] ?? '';
        $url = $item['url'] ?? '#';

        if ($title) {
            $html .= '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($title) . '</a>';
        }

        // Render Children
        if ($hasChildren) {
            $submenuClass = $menuNumber > 0 ? "submenu menu-{$menuNumber}-submenu" : "submenu";
            $html .= "\n    <ul class=\"{$submenuClass}\">\n";
            foreach ($children as $childPos => $child) {
                $html .= $this->renderItem($child, $menuNumber, $childPos);
            }
            $html .= "    </ul>\n  ";
        }

        $html .= '</li>' . "\n";

        return $html;
    }


}
