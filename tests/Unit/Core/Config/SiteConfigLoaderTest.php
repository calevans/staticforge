<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Core\Config;

use EICC\StaticForge\Core\Config\SiteConfigLoader;
use PHPUnit\Framework\TestCase;

class SiteConfigLoaderTest extends TestCase
{
    private SiteConfigLoader $loader;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new SiteConfigLoader();
        $this->tempDir = sys_get_temp_dir() . '/staticforge_siteconfig_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testMissingBaseFileAndNoOverrideDirReturnsEmptyArray(): void
    {
        $result = $this->loader->load($this->tempDir . '/', $this->tempDir);

        $this->assertSame([], $result);
    }

    public function testBaseFileOnlyNoSiteconfigDMatchesTodaysBehaviour(): void
    {
        file_put_contents($this->tempDir . '/siteconfig.yaml', "site:\n  title: My Site\n  template: staticforce\n");

        $result = $this->loader->load($this->tempDir . '/', $this->tempDir);

        $this->assertSame(
            ['site' => ['title' => 'My Site', 'template' => 'staticforce']],
            $result
        );
    }

    public function testMissingSiteconfigDDirectoryIsIgnored(): void
    {
        file_put_contents($this->tempDir . '/siteconfig.yaml', "site:\n  title: My Site\n");

        $result = $this->loader->load($this->tempDir . '/', $this->tempDir);

        $this->assertSame(['site' => ['title' => 'My Site']], $result);
    }

    public function testMapKeysDeepMergeRecursively(): void
    {
        file_put_contents(
            $this->tempDir . '/siteconfig.yaml',
            "site:\n  title: Base Title\n  description: Base Description\n"
        );
        $overrideDir = $this->tempDir . '/siteconfig.d';
        mkdir($overrideDir);
        file_put_contents($overrideDir . '/Site.yaml', "site:\n  title: Override Title\n");

        $result = $this->loader->load($this->tempDir . '/', $this->tempDir);

        $this->assertSame(
            ['site' => ['title' => 'Override Title', 'description' => 'Base Description']],
            $result
        );
    }

    public function testListsAreReplacedWholesaleNotMerged(): void
    {
        file_put_contents(
            $this->tempDir . '/siteconfig.yaml',
            "forms:\n  contact:\n    fields:\n      - name\n      - email\n"
        );
        $overrideDir = $this->tempDir . '/siteconfig.d';
        mkdir($overrideDir);
        file_put_contents(
            $overrideDir . '/Forms.yaml',
            "forms:\n  contact:\n    fields:\n      - phone\n"
        );

        $result = $this->loader->load($this->tempDir . '/', $this->tempDir);

        $this->assertSame(['forms' => ['contact' => ['fields' => ['phone']]]], $result);
    }

    public function testScalarOverridesAreLastWriterWins(): void
    {
        file_put_contents($this->tempDir . '/siteconfig.yaml', "chapter_nav: off\n");
        $overrideDir = $this->tempDir . '/siteconfig.d';
        mkdir($overrideDir);
        file_put_contents($overrideDir . '/A.yaml', "chapter_nav: on\n");

        $result = $this->loader->load($this->tempDir . '/', $this->tempDir);

        $this->assertSame(['chapter_nav' => 'on'], $result);
    }

    public function testMultipleOverrideFilesAppliedInAlphabeticalOrder(): void
    {
        $overrideDir = $this->tempDir . '/siteconfig.d';
        mkdir($overrideDir);
        file_put_contents($overrideDir . '/B.yaml', "value: from-b\n");
        file_put_contents($overrideDir . '/A.yaml', "value: from-a\n");

        $result = $this->loader->load($this->tempDir . '/', $this->tempDir);

        $this->assertSame(['value' => 'from-b'], $result);
    }

    public function testSiteconfigYamlAbsentButSiteconfigDPresentStartsFromEmptyBase(): void
    {
        $overrideDir = $this->tempDir . '/siteconfig.d';
        mkdir($overrideDir);
        file_put_contents($overrideDir . '/Site.yaml', "site:\n  title: Only From D\n");

        $result = $this->loader->load($this->tempDir . '/', $this->tempDir);

        $this->assertSame(['site' => ['title' => 'Only From D']], $result);
    }

    public function testNonArrayTopLevelYamlInOverrideFileCoercesToEmptyArray(): void
    {
        file_put_contents($this->tempDir . '/siteconfig.yaml', "site:\n  title: Kept\n");
        $overrideDir = $this->tempDir . '/siteconfig.d';
        mkdir($overrideDir);
        file_put_contents($overrideDir . '/Bad.yaml', "just a bare string\n");

        $result = $this->loader->load($this->tempDir . '/', $this->tempDir);

        $this->assertSame(['site' => ['title' => 'Kept']], $result);
    }

    public function testMalformedYamlInOverrideFileThrowsAndNamesTheFile(): void
    {
        file_put_contents($this->tempDir . '/siteconfig.yaml', "site:\n  title: Kept\n");
        $overrideDir = $this->tempDir . '/siteconfig.d';
        mkdir($overrideDir);
        $badFile = $overrideDir . '/Broken.yaml';
        file_put_contents($badFile, "site: [unclosed\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($badFile, '/') . '/');

        $this->loader->load($this->tempDir . '/', $this->tempDir);
    }

    public function testMalformedYamlInBaseFileStillThrows(): void
    {
        $badFile = $this->tempDir . '/siteconfig.yaml';
        file_put_contents($badFile, "site: [unclosed\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($badFile, '/') . '/');

        $this->loader->load($this->tempDir . '/', $this->tempDir);
    }

    public function testCwdSiteconfigYamlTakesPrecedenceOverAppRoot(): void
    {
        $appRootDir = $this->tempDir . '/approot';
        $cwdDir = $this->tempDir . '/cwd';
        mkdir($appRootDir, 0777, true);
        mkdir($cwdDir, 0777, true);
        file_put_contents($appRootDir . '/siteconfig.yaml', "site:\n  title: From AppRoot\n");
        file_put_contents($cwdDir . '/siteconfig.yaml', "site:\n  title: From Cwd\n");

        $result = $this->loader->load($appRootDir . '/', $cwdDir);

        $this->assertSame(['site' => ['title' => 'From Cwd']], $result);
    }

    public function testCwdSiteconfigDTakesPrecedenceOverAppRootSiteconfigD(): void
    {
        $appRootDir = $this->tempDir . '/approot';
        $cwdDir = $this->tempDir . '/cwd';
        mkdir($appRootDir . '/siteconfig.d', 0777, true);
        mkdir($cwdDir . '/siteconfig.d', 0777, true);
        file_put_contents($appRootDir . '/siteconfig.d/A.yaml', "value: from-approot\n");
        file_put_contents($cwdDir . '/siteconfig.d/A.yaml', "value: from-cwd\n");

        $result = $this->loader->load($appRootDir . '/', $cwdDir);

        $this->assertSame(['value' => 'from-cwd'], $result);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
