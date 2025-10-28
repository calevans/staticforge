<?php

namespace EICC\StaticForge\Features\CategoryIndex;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Category Index Feature - generates index.html pages for each category
 * Listens to POST_LOOP event to create category index pages with pagination
 */
class Feature extends BaseFeature implements FeatureInterface
{
  protected string $name = 'CategoryIndex';
  protected $logger;
  private array $categoryFiles = [];

  protected array $eventListeners = [
    'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 200],
    'POST_RENDER' => ['method' => 'collectCategoryFiles', 'priority' => 50],
    'POST_LOOP' => ['method' => 'generateCategoryIndexes', 'priority' => 100]
  ];

  private array $categoryMetadata = []; // Stores metadata from .ini files

  public function register(EventManager $eventManager, Container $container): void
  {
    parent::register($eventManager, $container);

    // Get logger from container
    $this->logger = $container->getVariable('logger');

    $this->logger->log('INFO', 'CategoryIndex Feature registered');
  }

  /**
   * Handle POST_GLOB event - scan .ini files and inject category menu entries
   */
  public function handlePostGlob(Container $container, array $parameters): array
  {
    // Scan for category .ini files
    $this->scanCategoryIniFiles($container);

    // Get existing menu data from MenuBuilder
    $features = $parameters['features'] ?? [];
    $menuData = $features['MenuBuilder']['files'] ?? [];

    // Inject category menu entries
    foreach ($this->categoryMetadata as $categorySlug => $metadata) {
      if (isset($metadata['menu'])) {
        $this->addCategoryToMenu(
          $metadata['menu'],
          $categorySlug,
          $metadata['title'] ?? ucfirst($categorySlug),
          $menuData
        );
      }
    }

    // Update menu data in parameters if we added anything
    if (isset($features['MenuBuilder'])) {
      $features['MenuBuilder']['files'] = $menuData;

      // Regenerate menu HTML with category entries included
      $features['MenuBuilder']['html'] = $this->rebuildMenuHtml($menuData);

      $parameters['features'] = $features;
    }

    return $parameters;
  }  /**
   * Collect files that have categories during POST_RENDER
   */
  public function collectCategoryFiles(Container $container, array $parameters): array
  {
    $metadata = $parameters['metadata'] ?? [];
    $category = $metadata['category'] ?? null;

    if ($category) {
      $outputPath = $parameters['output_path'] ?? null;
      $title = $metadata['title'] ?? 'Untitled';

      if ($outputPath) {
        // Sanitize category name to match filesystem
        $sanitizedCategory = $this->sanitizeCategoryName($category);

        if (!isset($this->categoryFiles[$sanitizedCategory])) {
          $this->categoryFiles[$sanitizedCategory] = [
            'display_name' => $category,
            'files' => []
          ];
        }

        $this->categoryFiles[$sanitizedCategory]['files'][] = [
          'title' => $title,
          'url' => $this->convertPathToUrl($outputPath, $container),
          'metadata' => $metadata
        ];
      }
    }

    return $parameters;
  }

  /**
   * Generate index.html pages for each category after all files processed
   */
  public function generateCategoryIndexes(Container $container, array $parameters): array
  {
    if (empty($this->categoryFiles)) {
      $this->logger->log('INFO', 'No categories found, skipping index generation');
      return $parameters;
    }

    $outputDir = $container->getVariable('OUTPUT_DIR') ?? 'public';
    $perPage = (int)($container->getVariable('CATEGORY_PAGINATION') ?? 10);

    foreach ($this->categoryFiles as $categorySlug => $categoryData) {
      $this->generateCategoryIndex($categorySlug, $categoryData, $outputDir, $perPage);
    }

    return $parameters;
  }

  /**
   * Generate a single category index page
   */
  private function generateCategoryIndex(
    string $categorySlug,
    array $categoryData,
    string $outputDir,
    int $perPage
  ): void {
    $categoryDir = $outputDir . DIRECTORY_SEPARATOR . $categorySlug;
    $indexPath = $categoryDir . DIRECTORY_SEPARATOR . 'index.html';

    // Ensure category directory exists
    if (!is_dir($categoryDir)) {
      mkdir($categoryDir, 0755, true);
    }

    // Get category metadata from .ini file (if exists)
    $iniMetadata = $this->categoryMetadata[$categorySlug] ?? [];

    // Apply metadata overrides
    $title = $iniMetadata['title'] ?? $categoryData['display_name'];
    $description = $iniMetadata['description'] ?? '';
    $template = $iniMetadata['template'] ?? 'category-index.html.twig';
    $categoryPerPage = $iniMetadata['per_page'] ?? $perPage;

    // Prepare data for template
    $templateData = [
      'title' => $title,
      'category' => $title,
      'description' => $description,
      'files' => $categoryData['files'],
      'total_files' => count($categoryData['files']),
      'per_page' => $categoryPerPage,
      'site_name' => $this->container->getVariable('SITE_NAME') ?? 'My Static Site',
      'base_url' => $this->container->getVariable('SITE_BASE_URL') ?? '/'
    ];

    // Render template
    $content = $this->renderTemplate($templateData, $template);

    // Prepare INI metadata for the generated index
    $indexMetadata = [
      'title' => $title,
    ];

    // Add menu if specified in .ini file
    if (isset($iniMetadata['menu'])) {
      $indexMetadata['menu'] = $iniMetadata['menu'];
    }

    // Add other metadata
    if ($description) {
      $indexMetadata['description'] = $description;
    }

    // Write index file with INI frontmatter
    $this->writeCategoryIndexWithIni($indexPath, $content, $indexMetadata);

    $this->logger->log(
      'INFO',
      "Generated category index: {$indexPath} ({$templateData['total_files']} files)"
    );
  }

  /**
   * Render the category index template
   */
  private function renderTemplate(array $data, string $template = 'category-index.html.twig'): string
  {
    try {
      $templateDir = $this->container->getVariable('TEMPLATE_DIR') ?? 'templates';
      $theme = $this->container->getVariable('TEMPLATE') ?? 'terminal';
      $themePath = $templateDir . DIRECTORY_SEPARATOR . $theme;

      $loader = new FilesystemLoader($themePath);
      $twig = new Environment($loader, [
        'cache' => false,
        'autoescape' => 'html',
        'strict_variables' => false
      ]);

      return $twig->render($template, $data);
    } catch (\Exception $e) {
      $this->logger->log('ERROR', "Template rendering failed: " . $e->getMessage());

      // Fallback to basic HTML
      return $this->renderFallbackTemplate($data);
    }
  }

  /**
   * Fallback template if Twig fails
   */
  private function renderFallbackTemplate(array $data): string
  {
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $html .= '<title>' . htmlspecialchars($data['category']) . ' - ' .
             htmlspecialchars($data['site_name']) . '</title>';
    $html .= '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>';
    $html .= '</head><body>';
    $html .= '<h1>' . htmlspecialchars($data['category']) . '</h1>';
    $html .= '<div id="category-files" data-per-page="' . $data['per_page'] . '">';

    foreach ($data['files'] as $file) {
      $html .= '<div class="file-item">';
      $html .= '<h2><a href="' . htmlspecialchars($file['url']) . '">' .
               htmlspecialchars($file['title']) . '</a></h2>';
      $html .= '</div>';
    }

    $html .= '</div>';
    $html .= '<div id="pagination"></div>';
    $html .= $this->getPaginationScript();
    $html .= '</body></html>';

    return $html;
  }

  /**
   * Get the JavaScript pagination code
   */
  private function getPaginationScript(): string
  {
    return <<<'JAVASCRIPT'
<script>
$(document).ready(function() {
  const container = $('#category-files');
  const items = container.find('.file-item');
  const perPage = parseInt(container.data('per-page')) || 10;
  const totalPages = Math.ceil(items.length / perPage);
  let currentPage = 1;

  function showPage(page) {
    items.hide();
    const start = (page - 1) * perPage;
    const end = start + perPage;
    items.slice(start, end).show();
    currentPage = page;
    updatePagination();
  }

  function updatePagination() {
    const pagination = $('#pagination');
    pagination.empty();

    if (totalPages <= 1) return;

    // Previous button
    if (currentPage > 1) {
      pagination.append(
        $('<button>').text('Previous').click(() => showPage(currentPage - 1))
      );
    }

    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
      const btn = $('<button>')
        .text(i)
        .addClass(i === currentPage ? 'active' : '')
        .click(() => showPage(i));
      pagination.append(btn);
    }

    // Next button
    if (currentPage < totalPages) {
      pagination.append(
        $('<button>').text('Next').click(() => showPage(currentPage + 1))
      );
    }
  }

  // Initialize
  showPage(1);
});
</script>
JAVASCRIPT;
  }

  /**
   * Convert filesystem path to URL
   */
  private function convertPathToUrl(string $path, Container $container): string
  {
    $outputDir = $container->getVariable('OUTPUT_DIR') ?? 'public';

    // Remove output directory prefix
    if (str_starts_with($path, $outputDir)) {
      $relativePath = substr($path, strlen($outputDir));
    } else {
      $relativePath = $path;
    }

    // Convert to URL format
    $url = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

    // Ensure leading slash
    if (!str_starts_with($url, '/')) {
      $url = '/' . $url;
    }

    return $url;
  }

  /**
   * Sanitize category name for use in filesystem paths
   */
  private function sanitizeCategoryName(string $category): string
  {
    $sanitized = strtolower($category);
    $sanitized = preg_replace('/[^a-z0-9]+/', '-', $sanitized);
    $sanitized = trim($sanitized, '-');
    return $sanitized;
  }

  /**
   * Scan content directory for category .ini files
   */
  private function scanCategoryIniFiles(Container $container): void
  {
    $contentDir = $container->getVariable('SOURCE_DIR') ?? 'content';

    if (!is_dir($contentDir)) {
      return;
    }

    // Recursively find all .ini files
    $iniFiles = $this->findIniFiles($contentDir);

    foreach ($iniFiles as $iniFile) {
      $this->loadCategoryIniFile($iniFile);
    }

    $this->logger->log('INFO', 'Loaded ' . count($this->categoryMetadata) . ' category .ini files');
  }

  /**
   * Recursively find all .ini files in directory
   */
  private function findIniFiles(string $dir): array
  {
    $iniFiles = [];

    $items = scandir($dir);
    foreach ($items as $item) {
      if ($item === '.' || $item === '..') {
        continue;
      }

      $path = $dir . DIRECTORY_SEPARATOR . $item;

      if (is_dir($path)) {
        $iniFiles = array_merge($iniFiles, $this->findIniFiles($path));
      } elseif (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'ini') {
        $iniFiles[] = $path;
      }
    }

    return $iniFiles;
  }

  /**
   * Load and parse a category .ini file
   */
  private function loadCategoryIniFile(string $filePath): void
  {
    $content = file_get_contents($filePath);
    if ($content === false) {
      return;
    }

    // Parse INI format
    $metadata = parse_ini_string($content);
    if ($metadata === false) {
      $this->logger->log('WARNING', "Failed to parse INI file: {$filePath}");
      return;
    }

    // Check if this is a category config file
    if (!isset($metadata['type']) || $metadata['type'] !== 'category') {
      return;
    }

    // Extract category name from filename (e.g., business.ini -> business)
    $filename = pathinfo($filePath, PATHINFO_FILENAME);
    $categorySlug = $this->sanitizeCategoryName($filename);

    // Store metadata (only optional fields)
    $this->categoryMetadata[$categorySlug] = [
      'menu' => $metadata['menu'] ?? null,
      'title' => $metadata['title'] ?? null,
      'description' => $metadata['description'] ?? null,
      'template' => $metadata['template'] ?? null,
      'per_page' => isset($metadata['per_page']) ? (int)$metadata['per_page'] : null,
      'sort_by' => $metadata['sort_by'] ?? null,
      'sort_order' => $metadata['sort_order'] ?? null,
      'robots.txt' => $metadata['robots.txt'] ?? null,
    ];

    // Remove null values
    $this->categoryMetadata[$categorySlug] = array_filter(
      $this->categoryMetadata[$categorySlug],
      fn($value) => $value !== null
    );

    $this->logger->log('INFO', "Loaded category config: {$filePath} for category '{$categorySlug}'");
  }

  /**
   * Write INI frontmatter to index file
   */
  private function writeCategoryIndexWithIni(string $indexPath, string $htmlContent, array $metadata): void
  {
    $iniBlock = "<!-- INI\n";

    // Add metadata to INI block
    foreach ($metadata as $key => $value) {
      if ($value !== null && $value !== '') {
        $iniBlock .= "{$key}: {$value}\n";
      }
    }

    $iniBlock .= "-->\n";

    // Combine INI block with HTML content
    $fullContent = $iniBlock . $htmlContent;

    file_put_contents($indexPath, $fullContent);
  }

  /**
   * Add a category to the menu data structure
   */
  private function addCategoryToMenu(string $menuPosition, string $categorySlug, string $title, array &$menuData): void
  {
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
   */
  private function rebuildMenuHtml(array $menuData): array
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
        if ($position === 0) continue; // Skip title

        $itemClass = $menuNumber > 0 ? "menu-{$menuNumber}-{$position}" : "";
        $liClass = $itemClass ? ' class="' . $itemClass . '"' : '';
        $html .= '      <li' . $liClass . '><a href="' . htmlspecialchars($item['url']) . '">' .
                 htmlspecialchars($item['title']) . '</a></li>' . "\n";
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

