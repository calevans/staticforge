<?php

namespace EICC\StaticForge\Features\MenuBuilder;

class MenuStructureBuilder
{
    /**
     * Parse menu value into array of positions
     * Supports: "1.2", "1.2, 2.3", "[1.2, 2.3]", etc.
     *
     * @return array<int, string>
     */
    public function parseMenuValue(string $rawValue): array
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
     * @param string $title Title for the menu entry
     * @param array<int, array<int, array{title: string, url: string, file: string, position: string}>>
     *        $menuData Menu data array passed by reference
     */
    public function addMenuEntry(string $menuPosition, array $fileData, string $title, array &$menuData): void
    {
        // Parse menu position (e.g., "1", "1.2", "1.2.3")
        $parts = explode('.', $menuPosition);

        // Only support up to 3 levels
        if (count($parts) > 3) {
            return;
        }

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

    /**
     * Sort menu data by position for proper ordering in templates
     *
     * @param array<int, array<int|string, mixed>> $menuData
     * @return array<int, array<int|string, mixed>>
     */
    public function sortMenuData(array $menuData): array
    {
        $sorted = [];

        foreach ($menuData as $menuNumber => $menuItems) {
            $sorted[$menuNumber] = $this->sortRecursive($menuItems);
        }

        return $sorted;
    }

    /**
     * Recursively sort menu items by numeric key
     *
     * @param array<mixed> $items
     * @return array<mixed>
     */
    private function sortRecursive(array $items): array
    {
        // Separate numeric keys (children/items) from string keys (properties)
        $numericItems = [];
        $stringItems = [];

        foreach ($items as $key => $value) {
            if (is_int($key)) {
                // Recursively sort children
                if (is_array($value)) {
                    $numericItems[$key] = $this->sortRecursive($value);
                } else {
                    $numericItems[$key] = $value;
                }
            } else {
                $stringItems[$key] = $value;
            }
        }

        // Sort numeric keys
        ksort($numericItems);

        // Combine: string keys first (properties), then numeric keys (children)
        return $stringItems + $numericItems;
    }
}
