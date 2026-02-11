<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\CategoryIndex\Services;

use EICC\StaticForge\Features\CategoryIndex\Models\Category;
use EICC\StaticForge\Features\CategoryIndex\Services\MenuService;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;

class MenuServiceTest extends UnitTestCase
{
    public function testCategoryMenuUsesTrailingSlashUrl(): void
    {
        $logger = $this->createMock(Log::class);
        $service = new MenuService($logger);

        $category = new Category('tech', ['title' => 'Tech', 'menu' => '1.2']);

        $menuData = $service->addCategoriesToMenu([$category], []);

        $this->assertSame('/tech/', $menuData[1][2]['url']);
    }
}
