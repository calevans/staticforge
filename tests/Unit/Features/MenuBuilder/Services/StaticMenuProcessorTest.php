<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\MenuBuilder\Services;

use EICC\StaticForge\Features\MenuBuilder\Services\MenuHtmlGenerator;
use EICC\StaticForge\Features\MenuBuilder\Services\StaticMenuProcessor;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use PHPUnit\Framework\MockObject\MockObject;

class StaticMenuProcessorTest extends UnitTestCase
{
    private StaticMenuProcessor $processor;
    private MenuHtmlGenerator $htmlGenerator;
    private Log&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->htmlGenerator = new MenuHtmlGenerator();
        $this->logger = $this->createMock(Log::class);
        $this->processor = new StaticMenuProcessor($this->htmlGenerator, $this->logger);
    }

    public function testProcessStaticMenusThrowsWhenSiteBaseUrlMissing(): void
    {
        // Use a bare container without SITE_BASE_URL set (the bootstrapped
        // container from UnitTestCase always provides one via .env.testing).
        $container = new \EICC\Utils\Container();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SITE_BASE_URL not set in container');

        $this->processor->processStaticMenus($container);
    }

    public function testProcessStaticMenusSkipsWhenNoMenuConfig(): void
    {
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test']]);

        $this->processor->processStaticMenus($this->container);

        $this->assertFalse($this->container->hasVariable('menu_top'));
    }

    public function testProcessStaticMenusSkipsWhenMenuConfigIsNotArray(): void
    {
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');
        $this->setContainerVariable('site_config', ['menu' => 'not-an-array']);

        $this->processor->processStaticMenus($this->container);

        $this->assertFalse($this->container->hasVariable('menu_top'));
    }

    public function testProcessStaticMenusSkipsNonArrayMenuDefinition(): void
    {
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');
        $this->setContainerVariable('site_config', [
            'menu' => [
                'top' => 'not-an-array',
            ],
        ]);

        $this->logger->expects($this->once())
            ->method('log')
            ->with('WARNING', $this->stringContains("Menu 'top'"));

        $this->processor->processStaticMenus($this->container);

        $this->assertFalse($this->container->hasVariable('menu_top'));
    }

    public function testProcessStaticMenusGeneratesHtmlForNamedMenu(): void
    {
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');
        $this->setContainerVariable('site_config', [
            'menu' => [
                'top' => [
                    'Home' => '/',
                    'About' => '/about/',
                ],
            ],
        ]);

        $this->processor->processStaticMenus($this->container);

        $this->assertTrue($this->container->hasVariable('menu_top'));
        $html = $this->container->getVariable('menu_top');
        // Relative URLs get the base URL prepended
        $this->assertStringContainsString('<a href="https://example.com/">Home</a>', $html);
        $this->assertStringContainsString('<a href="https://example.com/about/">About</a>', $html);
    }

    public function testProcessStaticMenusPrependsBaseUrlToRelativePaths(): void
    {
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com/site');
        $this->setContainerVariable('site_config', [
            'menu' => [
                'footer' => [
                    'Contact' => '/contact/',
                ],
            ],
        ]);

        $this->processor->processStaticMenus($this->container);

        $html = $this->container->getVariable('menu_footer');
        $this->assertStringContainsString('https://example.com/site/contact/', $html);
    }

    public function testProcessStaticMenusDoesNotDoublePrefixAbsoluteUrlAlreadyMatchingBase(): void
    {
        // The double-prefix guard compares against the full base URL (e.g.
        // "https://example.com/site"), so it only no-ops when the configured
        // menu URL is already absolute and matches the base URL -- a
        // relative path like "/site/contact/" is NOT recognized as already
        // prefixed and will still get the base URL prepended.
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com/site');
        $this->setContainerVariable('site_config', [
            'menu' => [
                'footer' => [
                    'Contact' => 'https://example.com/site/contact/',
                ],
            ],
        ]);

        $this->processor->processStaticMenus($this->container);

        $html = $this->container->getVariable('menu_footer');
        $this->assertStringNotContainsString('https://example.com/sitehttps://', $html);
        $this->assertStringContainsString('https://example.com/site/contact/', $html);
    }

    public function testProcessStaticMenusUpdatesExistingVariable(): void
    {
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');
        $this->setContainerVariable('site_config', [
            'menu' => [
                'top' => ['Home' => '/'],
            ],
        ]);
        $this->setContainerVariable('menu_top', 'old-value');

        $this->processor->processStaticMenus($this->container);

        $this->assertNotEquals('old-value', $this->container->getVariable('menu_top'));
    }
}
