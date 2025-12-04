<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\CategoryIndex\Services;

use EICC\StaticForge\Features\CategoryIndex\Models\Category;
use EICC\Utils\Container;
use EICC\Utils\Log;

class MenuService
{
    private Log $logger;

    public function __construct(Log $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param Category[] $categories
     * @param array<int, mixed> $menuData
     * @return array<int, mixed> Updated menu data
     */
    public function addCategoriesToMenu(array $categories, array $menuData): array
    {
        foreach ($categories as $category) {
            if ($category->menuPosition) {
                $this->addToMenu(
                    $category->menuPosition,
                    $category->slug,
                    $category->title,
                    $menuData
                );
            }
        }

        return $menuData;
    }

    private function addToMenu(string $position, string $slug, string $title, array &$menuData): void
    {
        $parts = explode('.', $position);
        if (count($parts) > 3) {
            return;
        }

        $entry = [
            'title' => $title,
            'url' => '/' . $slug . '/',
            'file' => 'category:' . $slug,
            'position' => $position
        ];

        $menu = (int)$parts[0];
        if (!isset($menuData[$menu])) {
            $menuData[$menu] = [];
        }

        if (count($parts) === 1) {
            $menuData[$menu]['direct'][] = $entry;
        } elseif (count($parts) === 2) {
            $menuData[$menu][(int)$parts[1]] = $entry;
        } elseif (count($parts) === 3) {
            $menuData[$menu][(int)$parts[1]][(int)$parts[2]] = $entry;
        }

        $this->logger->log('INFO', "Added category '{$title}' to menu {$position}");
    }
}
