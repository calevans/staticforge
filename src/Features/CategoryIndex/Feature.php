<?php

namespace EICC\StaticForge\Features\CategoryIndex;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Application;
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

    private ImageProcessor $imageProcessor;
    private MenuIntegrator $menuIntegrator;
    private CategoryManager $categoryManager;
    private CategoryPageGenerator $categoryPageGenerator;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
    'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 200],
    'PRE_RENDER' => ['method' => 'handlePreRender', 'priority' => 150],  // Before other features
    'POST_RENDER' => ['method' => 'collectCategoryFiles', 'priority' => 50],
    'POST_LOOP' => ['method' => 'processDeferredCategoryFiles', 'priority' => 100]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);

        // Get logger from container
        $this->logger = $container->get('logger');

        // Initialize sub-components
        $this->imageProcessor = new ImageProcessor($this->logger);
        $this->menuIntegrator = new MenuIntegrator($this->logger);
        $this->categoryManager = new CategoryManager($this->logger, $this->imageProcessor);
        $this->categoryPageGenerator = new CategoryPageGenerator($this->logger, $this->categoryManager);

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
        $this->categoryManager->scanCategoryFiles($container);

        // Get existing menu data from MenuBuilder
        $features = $parameters['features'] ?? [];
        $menuData = $features['MenuBuilder']['files'] ?? [];

        // Inject category menu entries
        foreach ($this->categoryManager->getCategoryMetadata() as $categorySlug => $metadata) {
            if (isset($metadata['menu'])) {
                $this->menuIntegrator->addCategoryToMenu(
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
            $features['MenuBuilder']['html'] = $this->menuIntegrator->rebuildMenuHtml($menuData);
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
        $metadata = $this->categoryManager->getCategoryMetadata();

        if (isset($metadata[$categorySlug])) {
            $this->categoryPageGenerator->deferCategoryFile($filePath, $metadata[$categorySlug], $container);

            // Skip rendering this file in the main loop
            $parameters['skip_file'] = true;
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
        $this->categoryManager->collectCategoryFile($container, $parameters);
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
        $this->categoryPageGenerator->processDeferredCategoryFiles($container);
        return $parameters;
    }
}
