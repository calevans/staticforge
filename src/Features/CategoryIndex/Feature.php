<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\CategoryIndex;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\CategoryIndex\Services\CategoryPageService;
use EICC\StaticForge\Features\CategoryIndex\Services\CategoryService;
use EICC\StaticForge\Features\CategoryIndex\Services\ImageService;
use EICC\StaticForge\Features\CategoryIndex\Services\MenuService;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Category Index Feature - generates index.html pages for each category
 * Listens to POST_LOOP event to create category index pages with pagination
 */
class Feature extends BaseFeature implements FeatureInterface
{
    protected string $name = 'CategoryIndex';
    protected Log $logger;

    private CategoryService $categoryService;
    private CategoryPageService $pageService;
    private MenuService $menuService;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 200],
        'PRE_RENDER' => ['method' => 'handlePreRender', 'priority' => 150],
        'POST_RENDER' => ['method' => 'collectCategoryFiles', 'priority' => 50],
        'POST_LOOP' => ['method' => 'processDeferredCategoryFiles', 'priority' => 100]
    ];

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);

        $this->logger = $container->get('logger');

        $imageService = new ImageService($this->logger);
        $this->categoryService = new CategoryService($this->logger, $imageService);
        $this->pageService = new CategoryPageService($this->logger, $this->categoryService);
        $this->menuService = new MenuService($this->logger);

        $this->logger->log('INFO', 'CategoryIndex Feature registered');
    }

    public function handlePostGlob(Container $container, array $parameters): array
    {
        $this->logger->log('INFO', 'CategoryIndex: Scanning for category files');

        $this->categoryService->scanCategories($container);

        $features = $parameters['features'] ?? [];
        $categories = $this->categoryService->getCategories();
        
        $parameters['features'] = $this->menuService->injectCategories($categories, $features, $container);

        return $parameters;
    }

    public function handlePreRender(Container $container, array $parameters): array
    {
        if (!empty($parameters['bypass_category_defer'])) {
            return $parameters;
        }

        $filePath = $parameters['file_path'] ?? null;
        if (!$filePath) {
            return $parameters;
        }

        $slug = pathinfo($filePath, PATHINFO_FILENAME);
        $category = $this->categoryService->getCategory($slug);

        if ($category) {
            $this->pageService->deferFile($filePath, $category->metadata, $container);
            $parameters['skip_file'] = true;
        }

        return $parameters;
    }

    public function collectCategoryFiles(Container $container, array $parameters): array
    {
        $this->categoryService->collectFile($container, $parameters);
        return $parameters;
    }

    public function processDeferredCategoryFiles(Container $container, array $parameters): array
    {
        $this->pageService->processDeferredFiles($container);
        return $parameters;
    }
}
