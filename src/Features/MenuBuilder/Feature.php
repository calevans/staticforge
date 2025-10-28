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
        $menuEntry = $this->extractMenuFromFile($content, $filePath);

        if ($menuEntry !== null) {
            $this->addMenuEntry($menuEntry, $filePath, $menuData);
        }
    }

    private function extractMenuFromFile(string $content, string $filePath): ?string
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        if ($extension === 'md') {
            return $this->extractMenuFromMarkdown($content);
        } elseif ($extension === 'html') {
            return $this->extractMenuFromHtml($content);
        }

        return null;
    }

    private function extractMenuFromMarkdown(string $content): ?string
    {
        // Extract YAML frontmatter
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
            return null;
        }

        $frontmatter = $matches[1];

        // Look for menu: entry
        if (preg_match('/^menu:\s*(.+)$/m', $frontmatter, $menuMatches)) {
            return trim($menuMatches[1]);
        }

        return null;
    }

    private function extractMenuFromHtml(string $content): ?string
    {
        // Look for <!-- INI ... --> block
        if (!preg_match('/<!--\s*INI\s*\n(.*?)\n-->/s', $content, $matches)) {
            return null;
        }

        $iniContent = $matches[1];

        // Look for menu: entry
        if (preg_match('/^menu:\s*(.+)$/m', $iniContent, $menuMatches)) {
            return trim($menuMatches[1]);
        }

        return null;
    }

    private function addMenuEntry(string $menuPosition, string $filePath, array &$menuData): void
    {
        // Parse menu position (e.g., "1", "1.2", "1.2.3")
        $parts = explode('.', $menuPosition);

        // Only support up to 3 levels
        if (count($parts) > 3) {
            return;
        }

        // Get file metadata
        $title = $this->extractTitleFromFile($filePath);
        $url = $this->generateUrlFromPath($filePath);

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
            // Top level menu item
            $menuData[$menu][] = $menuEntry;
        } elseif (count($parts) === 2) {
            // Second level menu item
            $position = (int)$parts[1];
            if (!isset($menuData[$menu][$position])) {
                $menuData[$menu][$position] = [];
            }
            $menuData[$menu][$position][] = $menuEntry;
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

    private function generateUrlFromPath(string $filePath): string
    {
        // Convert file path to URL (strip content directory, use only relative path)
        $contentDir = $this->container->getVariable('CONTENT_DIR') ?? 'content';

        // File paths are relative: content/filename.md or content/subdir/filename.md
        // We want: /filename.html or /subdir/filename.html

        // Remove the content directory prefix
        if (str_starts_with($filePath, $contentDir . '/')) {
            $relativePath = substr($filePath, strlen($contentDir) + 1);
        } else {
            $relativePath = $filePath;
        }

        // Convert to URL
        $url = '/' . str_replace(['\\', '.md'], ['/', '.html'], $relativePath);

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

        ksort($menuItems); // Sort by position

        // Check if this menu has submenus (contains items with numeric keys)
        $hasSubmenus = false;
        foreach ($menuItems as $key => $item) {
            if (is_numeric($key) && $key > 0) {
                $hasSubmenus = true;
                break;
            }
        }

        if ($hasSubmenus) {
            // This is a menu with submenus
            $dropdownTitle = 'Menu';
            $submenuItems = [];
            $submenuPosition = 0;

            foreach ($menuItems as $key => $item) {
                if ($key === 0 && isset($item[0]['title'])) {
                    // This is the dropdown title (position x.0)
                    $dropdownTitle = $item[0]['title'];
                    $submenuPosition = $key;
                } elseif ($key > 0 && isset($item[0]['title'])) {
                    // These are submenu items (position x.1, x.2, etc.)
                    $submenuItems[$key] = $item[0];
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
                if (isset($item[0]['title'])) {
                    $menuItem = $item[0];
                    $itemClass = $menuNumber > 0 ? "menu-{$menuNumber}" : "";
                    $liClass = $itemClass ? ' class="' . $itemClass . '"' : '';
                    $html .= '  <li' . $liClass . '><a href="' . htmlspecialchars($menuItem['url']) . '">' .
                             htmlspecialchars($menuItem['title']) . '</a></li>' . "\n";
                } elseif (isset($item['title'])) {
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