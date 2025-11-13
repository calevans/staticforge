<?php

namespace EICC\StaticForge\Features\CategoryIndex;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\Utils\Log;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Category Index Feature - generates index.html pages for each category
 * Listens to POST_LOOP event to create category index pages with pagination
 */
class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'CategoryIndex';
    protected Log $logger;

    /**
     * Files organized by category
     * @var array<string, array{display_name?: string, files: array<int, array<string, mixed>>}>
     */
    private array $categoryFiles = [];

    /**
     * Category files to generate after main loop
     * @var array<string, array{files: array<string>, output_path: string}>
     */
    private array $deferredCategoryFiles = [];  // Track category files to process later

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
    'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 200],
    'PRE_RENDER' => ['method' => 'handlePreRender', 'priority' => 150],  // Before other features
    'POST_RENDER' => ['method' => 'collectCategoryFiles', 'priority' => 50],
    'POST_LOOP' => ['method' => 'processDeferredCategoryFiles', 'priority' => 100]
    ];

    /**
     * Stores metadata from category definition files
     * @var array<string, array<string, mixed>>
     */
    private array $categoryMetadata = []; // Stores metadata from category files

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);

      // Get logger from container
        $this->logger = $container->get('logger');

        $this->logger->log('INFO', 'CategoryIndex Feature registered');
    }

    /**
     * Handle POST_GLOB event - scan for category files and inject category menu entries
     *
     * Called dynamically by EventManager when POST_GLOB event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostGlob(Container $container, array $parameters): array
    {
        $this->logger->log('INFO', 'CategoryIndex: Scanning for category files');

      // Scan for category markdown/html files (type: category in frontmatter)
        $this->scanCategoryFiles($container);

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

      // Update menu data in parameters
        if (isset($features['MenuBuilder'])) {
            $features['MenuBuilder']['files'] = $menuData;
            $features['MenuBuilder']['html'] = $this->rebuildMenuHtml($menuData);
            $parameters['features'] = $features;
        }

        return $parameters;
    }

    /**
     * PRE_RENDER: Detect category files and defer their rendering
     *
     * Called dynamically by EventManager when PRE_RENDER event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePreRender(Container $container, array $parameters): array
    {
      // If bypass_category_defer flag is set, don't defer (used during POST_LOOP processing)
        if (!empty($parameters['bypass_category_defer'])) {
            return $parameters;
        }

        $filePath = $parameters['file_path'] ?? null;

        if (!$filePath) {
            return $parameters;
        }

      // Check if this file is in our scanned category metadata
        $categorySlug = pathinfo($filePath, PATHINFO_FILENAME);

        if (isset($this->categoryMetadata[$categorySlug])) {
          // Determine correct output path: public/{category}/index.html
            $publicDir = $this->container->getVariable('PUBLIC_DIR') ?? 'public';
            $outputPath = $publicDir . DIRECTORY_SEPARATOR . $categorySlug . DIRECTORY_SEPARATOR . 'index.html';

          // Store this file for later processing
            $this->deferredCategoryFiles[] = [
            'file_path' => $filePath,
            'metadata' => $this->categoryMetadata[$categorySlug],
            'output_path' => $outputPath
            ];

          // Skip rendering this file in the main loop
            $parameters['skip_file'] = true;

            $this->logger->log('INFO', "Deferring category file for later processing: {$filePath}");
        }

        return $parameters;
    }

    /**
     * Collect files that have categories during POST_RENDER
     *
     * Called dynamically by EventManager when POST_RENDER event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function collectCategoryFiles(Container $container, array $parameters): array
    {
        $metadata = $parameters['metadata'] ?? [];
        $category = $metadata['category'] ?? null;

        if ($category) {
            $outputPath = $parameters['output_path'] ?? null;
            $filePath = $parameters['file_path'] ?? null;
            $renderedContent = $parameters['rendered_content'] ?? '';
            $title = $metadata['title'] ?? 'Untitled';

            if ($outputPath && $filePath && $renderedContent) {
                // Sanitize category name to match filesystem
                $sanitizedCategory = $this->sanitizeCategoryName($category);

                if (!isset($this->categoryFiles[$sanitizedCategory])) {
                    $this->categoryFiles[$sanitizedCategory] = [
                    'display_name' => $category,
                    'files' => []
                    ];
                }

                // Extract hero image from rendered content (not from disk - file doesn't exist yet)
                $imageUrl = $this->extractHeroImageFromHtml($renderedContent, $filePath, $container);
                $date = $this->getFileDate($metadata, $filePath);

                $this->categoryFiles[$sanitizedCategory]['files'][] = [
                'title' => $title,
                'url' => '/' . $sanitizedCategory . '/' . basename($outputPath),
                'image' => $imageUrl,
                'date' => $date,
                'metadata' => $metadata
                ];
            }
        }

        return $parameters;
    }

    /**
     * POST_LOOP: Process deferred category files through the rendering pipeline
     *
     * Called dynamically by EventManager when POST_LOOP event fires.
     *
     * @phpstan-used Called via EventManager event dispatch
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function processDeferredCategoryFiles(Container $container, array $parameters): array
    {
        if (empty($this->deferredCategoryFiles)) {
            $this->logger->log('INFO', 'No deferred category files to process');
            return $parameters;
        }

        $this->logger->log('INFO', 'Processing ' . count($this->deferredCategoryFiles) . ' deferred category files');

        foreach ($this->deferredCategoryFiles as $categoryFile) {
            $this->processCategoryFile($categoryFile, $container);
        }

        return $parameters;
    }

  /**
   * Public wrapper for testing - generates category indexes
   * This is an alias for processDeferredCategoryFiles to maintain test compatibility
   */
    public function generateCategoryIndexes(Container $container, array $parameters): array
    {
        return $this->processDeferredCategoryFiles($container, $parameters);
    }

    /**
     * Process a single category file through the rendering pipeline
     *
     * @param array<string, mixed> $categoryFile Category file data with metadata
     * @param Container $container Dependency injection container
     */
    private function processCategoryFile(array $categoryFile, Container $container): void
    {
        $filePath = $categoryFile['file_path'];
        $metadata = $categoryFile['metadata'];

      // Determine category slug from file path (e.g., business.md -> business)
        $categorySlug = pathinfo($filePath, PATHINFO_FILENAME);

      // Get collected files for this category
        $categoryData = $this->categoryFiles[$categorySlug] ?? ['files' => []];

        $this->logger->log(
            'INFO',
            "Processing category file: {$filePath} with " . count($categoryData['files']) . " files"
        );

      // Build complete markdown content with frontmatter
      // Include the files array in metadata so Twig can access it
        $frontmatter = "---\n";
        foreach ($metadata as $key => $value) {
            if ($key !== 'type') {  // Don't include type = category in output
                $frontmatter .= "{$key} = {$value}\n";
            }
        }
        $frontmatter .= "category_files_count = " . count($categoryData['files']) . "\n";
        $frontmatter .= "---\n\n";
        $markdownContent = $frontmatter . "<!-- Category file listing will be rendered by template -->";

      // Store category_files in container features so template can access it
        $features = $container->getVariable('features') ?? [];
        $features['CategoryIndex']['category_files'] = $categoryData['files'];
        $container->updateVariable('features', $features);

      // Get Application instance from container
        $application = $container->get('application');

        try {
          // Use Application's renderSingleFile method with additional context
            $application->renderSingleFile($filePath, [
            'file_content' => $markdownContent,  // Provide the content to MarkdownRenderer
            'metadata' => array_merge($metadata, [
            'category_files' => $categoryData['files'],  // Pass files to template
            'total_files' => count($categoryData['files']),
            ]),
            'output_path' => $categoryFile['output_path'],
            'bypass_category_defer' => true  // Tell PRE_RENDER to not defer this file
            ]);
        } catch (\Exception $e) {
            $this->logger->log('ERROR', "Failed to process category file {$filePath}: " . $e->getMessage());
        }
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
   * Scan discovered files for category files (type = category in frontmatter)
   */
    private function scanCategoryFiles(Container $container): void
    {
        $discoveredFiles = $container->getVariable('discovered_files') ?? [];

        foreach ($discoveredFiles as $fileData) {
            $metadata = $fileData['metadata'];

            if (isset($metadata['type']) && $metadata['type'] === 'category') {
                $categorySlug = pathinfo($fileData['path'], PATHINFO_FILENAME);
                $this->categoryMetadata[$categorySlug] = $metadata;

                $this->logger->log('INFO', "Found category file: {$fileData['path']}");
            }
        }

        $this->logger->log('INFO', 'Found ' . count($this->categoryMetadata) . ' category files');
    }

    /**
     * Parse INI frontmatter into metadata array
     *
     * @param string $ini INI-formatted string
     * @return array<string, mixed> Parsed metadata
     */
    private function parseIniFrontmatter(string $ini): array
    {
        $metadata = [];
        $lines = explode("\n", $ini);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = array_map('trim', explode('=', $line, 2));

          // Strip quotes from value
            $value = trim($value, '"\'');

            $metadata[$key] = $value;
        }

        return $metadata;
    }

    /**
     * Add a category to the menu data structure
     *
     * @param string $menuPosition Menu position (e.g., "1.2")
     * @param string $categorySlug Category URL slug
     * @param string $title Display title
     * @param array<int, mixed> $menuData Menu structure passed by reference
     */
    private function addCategoryToMenu(
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

  /**
   * Extract hero image from rendered HTML content
   */
    private function extractHeroImageFromHtml(string $html, string $sourcePath, Container $container): string
    {
      // Extract first image tag
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $imageSrc = $matches[1];

          // Check if it's an external URL
            if (preg_match('/^https?:\/\//i', $imageSrc)) {
                // Download and cache external image
                return $this->downloadAndCacheImage($imageSrc, $sourcePath, $container);
            }

          // Convert relative URL to filesystem path
            $publicDir = $container->getVariable('PUBLIC_DIR') ?? 'public';
            $imagePath = $publicDir . $imageSrc;

            if (file_exists($imagePath)) {
              // Generate thumbnail
                return $this->generateThumbnail($imagePath, $sourcePath, $container);
            }
        }

        return $this->getPlaceholderImage($container);
    }

    /**
     * Generate thumbnail from source image
     */
    private function generateThumbnail(string $sourcePath, string $contentPath, Container $container): string
    {
        $publicDir = $container->getVariable('PUBLIC_DIR') ?? 'public';
        $thumbnailDir = $publicDir . '/images';

      // Create images directory if it doesn't exist
        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }

      // Generate thumbnail filename based on content file
        $basename = pathinfo($contentPath, PATHINFO_FILENAME);
        $thumbnailPath = $thumbnailDir . '/' . $basename . '.jpg';
        $thumbnailUrl = '/images/' . $basename . '.jpg';

      // Check if thumbnail already exists and is newer than source
        if (file_exists($thumbnailPath) && filemtime($thumbnailPath) >= filemtime($sourcePath)) {
            return $thumbnailUrl;
        }

      // Use ImageMagick to resize
        try {
            $imagick = new \Imagick($sourcePath);
            $imagick->thumbnailImage(300, 200, true); // 300x200, best fit
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(85);
            $imagick->writeImage($thumbnailPath);
            $imagick->clear();

            $this->logger->log('INFO', "Generated thumbnail: {$thumbnailPath}");
            return $thumbnailUrl;
        } catch (\Exception $e) {
            $this->logger->log('ERROR', "Failed to generate thumbnail: " . $e->getMessage());
            return $this->getPlaceholderImage($container);
        }
    }

  /**
   * Get or generate placeholder image
   */
    private function getPlaceholderImage(Container $container): string
    {
        $theme = $container->getVariable('TEMPLATE') ?? 'terminal';
        $templateDir = $container->getVariable('TEMPLATE_DIR') ?? 'templates';
        $placeholderPath = $templateDir . '/' . $theme . '/placeholder.jpg';

      // Check if placeholder exists
        if (file_exists($placeholderPath)) {
            return '/templates/' . $theme . '/placeholder.jpg';
        }

      // Generate placeholder
        try {
            $imagick = new \Imagick();
            $imagick->newImage(300, 200, new \ImagickPixel('#808080')); // Gray background
            $imagick->setImageFormat('jpeg');

          // Ensure directory exists
            $dir = dirname($placeholderPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $imagick->writeImage($placeholderPath);
            $imagick->clear();

            $this->logger->log('INFO', "Generated placeholder image: {$placeholderPath}");
            return '/templates/' . $theme . '/placeholder.jpg';
        } catch (\Exception $e) {
            $this->logger->log('ERROR', "Failed to generate placeholder: " . $e->getMessage());
            return ''; // Return empty string if all fails
        }
    }

  /**
   * Download external image and cache it locally
   */
    private function downloadAndCacheImage(string $url, string $sourcePath, Container $container): string
    {
        $publicDir = $container->getVariable('PUBLIC_DIR') ?? 'public';
        $cacheDir = $publicDir . '/images/cache';

      // Create cache directory if it doesn't exist
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

      // Generate cache filename from URL hash
        $urlHash = md5($url);
        $basename = pathinfo($sourcePath, PATHINFO_FILENAME);
        $cachedImagePath = $cacheDir . '/' . $basename . '_' . $urlHash . '.jpg';
        $cachedImageUrl = '/images/cache/' . $basename . '_' . $urlHash . '.jpg';

      // Check if cached version exists
        if (file_exists($cachedImagePath)) {
            $this->logger->log('DEBUG', "Using cached image: {$cachedImagePath}");
            return $cachedImageUrl;
        }

      // Download the image
        try {
            $this->logger->log('INFO', "Downloading external image: {$url}");

            $imageData = @file_get_contents($url);
            if ($imageData === false) {
                throw new \Exception("Failed to download image from URL");
            }

          // Save to temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'img_');
            file_put_contents($tempFile, $imageData);

          // Use ImageMagick to resize and convert to thumbnail
            $imagick = new \Imagick($tempFile);
            $imagick->thumbnailImage(300, 200, true); // 300x200, best fit
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(85);
            $imagick->writeImage($cachedImagePath);
            $imagick->clear();

          // Clean up temp file
            unlink($tempFile);

            $this->logger->log('INFO', "Cached external image: {$cachedImagePath}");
            return $cachedImageUrl;
        } catch (\Exception $e) {
            $this->logger->log('ERROR', "Failed to download/cache image from {$url}: " . $e->getMessage());
            return $this->getPlaceholderImage($container);
        }
    }

    /**
     * Get file date from metadata or filesystem
     *
     * @param array<string, mixed> $metadata File metadata
     * @param string $filePath Path to the file
     * @return string Formatted date string
     */
    private function getFileDate(array $metadata, string $filePath): string
    {
      // Check for published_date in metadata
        if (isset($metadata['published_date'])) {
            return $metadata['published_date'];
        }

      // Fall back to source file modification time
        if (file_exists($filePath)) {
            $mtime = filemtime($filePath);
            if ($mtime === false) {
                return date('Y-m-d');
            }
            return date('Y-m-d', $mtime);
        }

        return date('Y-m-d'); // Current date as last resort
    }
}
