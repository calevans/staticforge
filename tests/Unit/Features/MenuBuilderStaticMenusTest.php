<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features;

use EICC\StaticForge\Features\MenuBuilder\Feature;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use PHPUnit\Framework\TestCase;

class MenuBuilderStaticMenusTest extends TestCase
{
    private Container $container;
    private EventManager $eventManager;
    private Feature $menuBuilder;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->eventManager = new EventManager($this->container);

        // Create mock logger
        $logger = $this->createMock(\EICC\Utils\Log::class);
        $this->container->stuff('logger', fn() => $logger);

        $this->menuBuilder = new Feature();
        $this->menuBuilder->register($this->eventManager, $this->container);

        // Set default SITE_BASE_URL for tests
        $this->container->setVariable('SITE_BASE_URL', '/');
    }

    public function testStaticMenusFromSiteConfig(): void
    {
        // Set up site config with menu definitions
        $siteConfig = [
            'menu' => [
                'top' => [
                    'Home' => '/',
                    'About' => '/about',
                    'Shop' => '/shop',
                ],
                'footer' => [
                    'Privacy' => '/privacy',
                    'Terms' => '/terms',
                ]
            ]
        ];

        $this->container->setVariable('site_config', $siteConfig);
        $this->container->setVariable('discovered_files', []);

        // Trigger POST_GLOB event
        $parameters = ['features' => []];
        $result = $this->menuBuilder->handlePostGlob($this->container, $parameters);

        // Verify menu_top was created
        $this->assertTrue($this->container->hasVariable('menu_top'));
        $menuTop = $this->container->getVariable('menu_top');
        $this->assertIsString($menuTop);
        $this->assertStringContainsString('<ul class="menu">', $menuTop);
        $this->assertStringContainsString('Home', $menuTop);
        $this->assertStringContainsString('About', $menuTop);
        $this->assertStringContainsString('Shop', $menuTop);
        $this->assertStringContainsString('href="/"', $menuTop);
        $this->assertStringContainsString('href="/about"', $menuTop);
        $this->assertStringContainsString('href="/shop"', $menuTop);

        // Verify menu_footer was created
        $this->assertTrue($this->container->hasVariable('menu_footer'));
        $menuFooter = $this->container->getVariable('menu_footer');
        $this->assertIsString($menuFooter);
        $this->assertStringContainsString('<ul class="menu">', $menuFooter);
        $this->assertStringContainsString('Privacy', $menuFooter);
        $this->assertStringContainsString('Terms', $menuFooter);
        $this->assertStringContainsString('href="/privacy"', $menuFooter);
        $this->assertStringContainsString('href="/terms"', $menuFooter);
    }

    public function testNoSiteConfigDoesNotCrash(): void
    {
        // No site_config set
        $this->container->setVariable('discovered_files', []);

        // Should not crash
        $parameters = ['features' => []];
        $result = $this->menuBuilder->handlePostGlob($this->container, $parameters);

        // Should not create static menus
        $this->assertFalse($this->container->hasVariable('menu_top'));
        $this->assertFalse($this->container->hasVariable('menu_footer'));
    }

    public function testEmptyMenusInConfig(): void
    {
        $siteConfig = [
            'menu' => []
        ];

        $this->container->setVariable('site_config', $siteConfig);
        $this->container->setVariable('discovered_files', []);

        $parameters = ['features' => []];
        $result = $this->menuBuilder->handlePostGlob($this->container, $parameters);

        // Should not create any menus
        $this->assertFalse($this->container->hasVariable('menu_top'));
    }

    public function testStaticMenusWithExternalLinks(): void
    {
        $siteConfig = [
            'menu' => [
                'top' => [
                    'Home' => '/',
                    'Shop' => 'https://shop.example.com',
                    'Blog' => 'https://blog.example.com',
                ]
            ]
        ];

        $this->container->setVariable('site_config', $siteConfig);
        $this->container->setVariable('discovered_files', []);

        $parameters = ['features' => []];
        $result = $this->menuBuilder->handlePostGlob($this->container, $parameters);

        $menuTop = $this->container->getVariable('menu_top');
        $this->assertStringContainsString('href="https://shop.example.com"', $menuTop);
        $this->assertStringContainsString('href="https://blog.example.com"', $menuTop);
    }
}
