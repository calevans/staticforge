<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Core\AssetManager;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class AssetManagerTest extends UnitTestCase
{
    private AssetManager $assetManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assetManager = new AssetManager();
    }

    public function testAddScriptAndGetScriptsInFooter(): void
    {
        $this->assetManager->addScript('app', '/js/app.js');

        $html = $this->assetManager->getScripts(true);

        $this->assertStringContainsString('<script src="/js/app.js"></script>', $html);
    }

    public function testGetScriptsExcludesHeaderScriptsFromFooter(): void
    {
        $this->assetManager->addScript('header-script', '/js/header.js', [], false);

        $footerHtml = $this->assetManager->getScripts(true);
        $headerHtml = $this->assetManager->getScripts(false);

        $this->assertStringNotContainsString('/js/header.js', $footerHtml);
        $this->assertStringContainsString('/js/header.js', $headerHtml);
    }

    public function testGetScriptsWithNoScriptsReturnsEmptyString(): void
    {
        $this->assertSame('', $this->assetManager->getScripts());
    }

    public function testAddStyleAndGetStyles(): void
    {
        $this->assetManager->addStyle('main', '/css/main.css');

        $html = $this->assetManager->getStyles();

        $this->assertStringContainsString('<link rel="stylesheet" href="/css/main.css">', $html);
    }

    public function testGetStylesWithNoStylesReturnsEmptyString(): void
    {
        $this->assertSame('', $this->assetManager->getStyles());
    }

    public function testScriptDependencyOrdering(): void
    {
        // Register child before parent dependency to verify topological sort reorders them
        $this->assetManager->addScript('child', '/js/child.js', ['parent']);
        $this->assetManager->addScript('parent', '/js/parent.js');

        $html = $this->assetManager->getScripts(true);

        $parentPos = strpos($html, '/js/parent.js');
        $childPos = strpos($html, '/js/child.js');

        $this->assertNotFalse($parentPos);
        $this->assertNotFalse($childPos);
        $this->assertLessThan($childPos, $parentPos, 'Dependency must be output before the dependent script');
    }

    public function testStyleDependencyOrdering(): void
    {
        $this->assetManager->addStyle('child', '/css/child.css', ['parent']);
        $this->assetManager->addStyle('parent', '/css/parent.css');

        $html = $this->assetManager->getStyles();

        $parentPos = strpos($html, '/css/parent.css');
        $childPos = strpos($html, '/css/child.css');

        $this->assertNotFalse($parentPos);
        $this->assertNotFalse($childPos);
        $this->assertLessThan($childPos, $parentPos, 'Dependency must be output before the dependent style');
    }

    public function testDependencyOnUnregisteredHandleIsIgnored(): void
    {
        // Depending on a handle that was never registered should not error,
        // it should simply be skipped during resolution.
        $this->assetManager->addScript('app', '/js/app.js', ['nonexistent-handle']);

        $html = $this->assetManager->getScripts(true);

        $this->assertStringContainsString('/js/app.js', $html);
    }

    public function testCircularDependencyThrowsException(): void
    {
        $this->assetManager->addScript('a', '/js/a.js', ['b']);
        $this->assetManager->addScript('b', '/js/b.js', ['a']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Circular dependency detected/');

        $this->assetManager->getScripts();
    }

    public function testSelfReferentialDependencyThrowsException(): void
    {
        $this->assetManager->addScript('self', '/js/self.js', ['self']);

        $this->expectException(\RuntimeException::class);

        $this->assetManager->getScripts();
    }

    public function testStyleCircularDependencyThrowsException(): void
    {
        $this->assetManager->addStyle('a', '/css/a.css', ['b']);
        $this->assetManager->addStyle('b', '/css/b.css', ['a']);

        $this->expectException(\RuntimeException::class);

        $this->assetManager->getStyles();
    }

    public function testReRegisteringHandleOverwritesPrevious(): void
    {
        $this->assetManager->addScript('app', '/js/old.js');
        $this->assetManager->addScript('app', '/js/new.js');

        $html = $this->assetManager->getScripts(true);

        $this->assertStringNotContainsString('/js/old.js', $html);
        $this->assertStringContainsString('/js/new.js', $html);
    }
}
