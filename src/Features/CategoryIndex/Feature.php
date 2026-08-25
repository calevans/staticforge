<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\CategoryIndex;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\Events\CollectMenuItemsEvent;
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\CategoryIndex\Services\CategoryPageService;
use EICC\StaticForge\Features\CategoryIndex\Services\CategoryService;
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
    private Container $applicationContainer;

    public function __construct(
        Log $logger,
        CategoryService $categoryService,
        CategoryPageService $pageService,
        MenuService $menuService,
        Container $applicationContainer
    ) {
        $this->logger = $logger;
        $this->categoryService = $categoryService;
        $this->pageService = $pageService;
        $this->menuService = $menuService;
        $this->applicationContainer = $applicationContainer;
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger->log('INFO', 'CategoryIndex Feature registered');
    }

    #[EventListener('POST_GLOB', priority: 50)]
    public function handlePostGlob(Event $event): void
    {
        $this->logger->log('INFO', 'CategoryIndex: Scanning for category files');

        $this->categoryService->scanCategories();
    }

    #[EventListener('COLLECT_MENU_ITEMS', priority: 100)]
    public function handleCollectMenuItems(CollectMenuItemsEvent $event): void
    {
        $categories = $this->categoryService->getCategories();

        $event->menuData = $this->menuService->addCategoriesToMenu($categories, $event->menuData);
    }

    #[EventListener('PRE_RENDER', priority: 150)]
    public function handlePreRender(RenderEvent $event): void
    {
        if (!empty($event->extra['bypass_category_defer'])) {
            return;
        }

        $filePath = $event->filePath;
        if ($filePath === '') {
            return;
        }

        $slug = pathinfo($filePath, PATHINFO_FILENAME);
        $category = $this->categoryService->getCategory($slug);

        if ($category) {
            $this->pageService->deferFile($filePath, $category->metadata, $this->applicationContainer);
            $event->skipFile = true;
        }
    }

    #[EventListener('POST_RENDER', priority: 150)]
    public function collectCategoryFiles(RenderEvent $event): void
    {
        $this->categoryService->collectFile($event);
    }

    #[EventListener('POST_LOOP', priority: 50)]
    public function processDeferredCategoryFiles(Event $event): void
    {
        $this->pageService->processDeferredFiles($this->applicationContainer);
    }
}
